<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Regras da certificação participativa (SPG/OCS).
 *
 * Porta a lógica de `api/certificacao/controller.php` e das telas
 * `modulos/certificacao/*` do sistema atual: filtros da listagem,
 * criação, edição, renovação, histórico, detalhes e exclusão.
 */
final class Certificacoes
{
    /** Tipos aceitos pela listagem/API atuais. */
    public const TIPOS = ['SPG', 'OCS', 'Rede Ecovida', 'Outro'];

    /** Status usados pela listagem atual. */
    public const STATUS = ['Ativa', 'Em Conversão', 'Vencida', 'Suspensa', 'Cancelada'];

    /**
     * Lê os filtros da query string no mesmo formato da tela atual.
     *
     * @return array{tipo: string, status: string, validade: string, q: string, associado_id: int}
     */
    public static function filtrosDaQuery(): array
    {
        $tipo = trim((string) ($_GET['tipo'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $validade = trim((string) ($_GET['validade'] ?? ''));

        return [
            'tipo' => in_array($tipo, self::TIPOS, true) ? $tipo : '',
            'status' => in_array($status, self::STATUS, true) ? $status : '',
            'validade' => in_array($validade, ['proximas', 'vencidas'], true) ? $validade : '',
            'q' => trim((string) ($_GET['q'] ?? '')),
            'associado_id' => (int) ($_GET['associado_id'] ?? 0),
        ];
    }

    /**
     * @param array{tipo: string, status: string, validade: string, q: string, associado_id: int} $filtros
     * @return list<array<string, mixed>>
     */
    public static function listar(array $filtros): array
    {
        [$where, $params] = self::condicoes($filtros);

        $sql = "SELECT c.*, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                       a.cpf AS associado_cpf, cert.nome AS certificadora_nome
                  FROM certificacao_participativa c
                  LEFT JOIN associados a ON a.id = c.associado_id
                  LEFT JOIN certificadoras cert ON cert.id = c.certificadora_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY c.data_validade DESC, c.id DESC
                 LIMIT 1000";

        $stmt = Identidades::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @param array{tipo: string, status: string, validade: string, q: string, associado_id: int} $filtros
     * @return array{total: int, ativas: int, vencidas: int, proximas: int}
     */
    public static function resumo(array $filtros): array
    {
        [$where, $params] = self::condicoes($filtros);
        $base = 'FROM certificacao_participativa c LEFT JOIN associados a ON a.id = c.associado_id WHERE ' . implode(' AND ', $where);

        $pdo = Identidades::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) ' . $base);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) " . $base . " AND c.status = 'Ativa'");
        $stmt->execute($params);
        $ativas = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) " . $base . " AND c.data_validade < CURDATE()");
        $stmt->execute($params);
        $vencidas = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) " . $base . " AND c.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $stmt->execute($params);
        $proximas = (int) $stmt->fetchColumn();

        return ['total' => $total, 'ativas' => $ativas, 'vencidas' => $vencidas, 'proximas' => $proximas];
    }

    /**
     * @param array{tipo: string, status: string, validade: string, q: string, associado_id: int} $filtros
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private static function condicoes(array $filtros): array
    {
        $where = ['c.excluido = 0'];
        $params = [];

        if ($filtros['tipo'] !== '') {
            $where[] = 'c.tipo_certificacao = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if ($filtros['status'] !== '') {
            $where[] = 'c.status = :status';
            $params['status'] = $filtros['status'];
        }
        if ($filtros['validade'] === 'vencidas') {
            $where[] = 'c.data_validade < CURDATE()';
        } elseif ($filtros['validade'] === 'proximas') {
            $where[] = 'c.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        }
        if ($filtros['q'] !== '') {
            $where[] = "(a.nome LIKE :q OR a.sobrenome LIKE :q OR c.numero_certificado LIKE :q)";
            $params['q'] = '%' . $filtros['q'] . '%';
        }
        if ($filtros['associado_id'] > 0) {
            $where[] = 'c.associado_id = :associado_id';
            $params['associado_id'] = $filtros['associado_id'];
        }

        return [$where, $params];
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        $stmt = Identidades::pdo()->prepare(
            "SELECT c.*, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                    a.cpf AS associado_cpf, a.cidade AS associado_cidade, a.estado AS associado_estado,
                    cert.nome AS certificadora_nome
               FROM certificacao_participativa c
               LEFT JOIN associados a ON a.id = c.associado_id
               LEFT JOIN certificadoras cert ON cert.id = c.certificadora_id
              WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** @return list<array<string, mixed>> */
    public static function historico(int $associadoId): array
    {
        $stmt = Identidades::pdo()->prepare(
            "SELECT c.*, cert.nome AS certificadora_nome
               FROM certificacao_participativa c
               LEFT JOIN certificadoras cert ON cert.id = c.certificadora_id
              WHERE c.associado_id = :associado_id
              ORDER BY c.data_emissao DESC, c.id DESC"
        );
        $stmt->execute(['associado_id' => $associadoId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function associados(): array
    {
        return Identidades::pdo()
            ->query("SELECT id, nome, sobrenome FROM associados WHERE excluido_em IS NULL ORDER BY nome")
            ->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function certificadoras(): array
    {
        return Identidades::pdo()
            ->query('SELECT id, nome, sigla FROM certificadoras ORDER BY nome')
            ->fetchAll();
    }

    /**
     * Valida os dados do formulário (criação/edição/renovação).
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string, dados: array<string, mixed>}
     */
    public static function validar(array $post, bool $renovacao = false): array
    {
        $dados = [
            'associado_id' => (int) ($post['associado_id'] ?? 0),
            'certificadora_id' => (int) ($post['certificadora_id'] ?? 0),
            'tipo_certificacao' => trim((string) ($post['tipo_certificacao'] ?? '')),
            'numero_certificado' => trim((string) ($post['numero_certificado'] ?? '')),
            'data_emissao' => trim((string) ($post['data_emissao'] ?? '')),
            'data_validade' => trim((string) ($post['data_validade'] ?? '')),
            'status' => trim((string) ($post['status'] ?? '')),
            'organismo_certificador' => trim((string) ($post['organismo_certificador'] ?? '')),
            'nome_grupo' => trim((string) ($post['nome_grupo'] ?? '')),
            'documento_url' => trim((string) ($post['documento_url'] ?? '')),
            'declaracao_compromisso_url' => trim((string) ($post['declaracao_compromisso_url'] ?? '')),
            'observacoes' => trim((string) ($post['observacoes'] ?? '')),
        ];

        if ($renovacao) {
            if ($dados['data_emissao'] === '' || strtotime($dados['data_emissao']) === false) {
                return ['ok' => false, 'erro' => 'Informe a nova data de emissão.', 'dados' => $dados];
            }
            if ($dados['data_validade'] === '' || strtotime($dados['data_validade']) === false) {
                return ['ok' => false, 'erro' => 'Informe a nova data de validade.', 'dados' => $dados];
            }
            if ($dados['data_validade'] < $dados['data_emissao']) {
                return ['ok' => false, 'erro' => 'A validade não pode ser anterior à emissão.', 'dados' => $dados];
            }

            return ['ok' => true, 'erro' => '', 'dados' => $dados];
        }

        if ($dados['associado_id'] <= 0) {
            return ['ok' => false, 'erro' => 'Selecione o associado.', 'dados' => $dados];
        }
        if (!in_array($dados['tipo_certificacao'], self::TIPOS, true)) {
            return ['ok' => false, 'erro' => 'Selecione um tipo de certificação válido.', 'dados' => $dados];
        }
        if (!in_array($dados['status'], self::STATUS, true)) {
            return ['ok' => false, 'erro' => 'Selecione um status válido.', 'dados' => $dados];
        }
        if ($dados['data_emissao'] === '' || strtotime($dados['data_emissao']) === false) {
            return ['ok' => false, 'erro' => 'Informe a data de emissão.', 'dados' => $dados];
        }
        if ($dados['data_validade'] === '' || strtotime($dados['data_validade']) === false) {
            return ['ok' => false, 'erro' => 'Informe a data de validade.', 'dados' => $dados];
        }
        if ($dados['data_validade'] < $dados['data_emissao']) {
            return ['ok' => false, 'erro' => 'A validade não pode ser anterior à emissão.', 'dados' => $dados];
        }
        if ($dados['numero_certificado'] === '') {
            return ['ok' => false, 'erro' => 'Informe o número do certificado.', 'dados' => $dados];
        }
        foreach (['documento_url', 'declaracao_compromisso_url'] as $campoUrl) {
            if ($dados[$campoUrl] !== '' && filter_var($dados[$campoUrl], FILTER_VALIDATE_URL) === false) {
                return ['ok' => false, 'erro' => 'Informe uma URL válida para os documentos.', 'dados' => $dados];
            }
        }

        return ['ok' => true, 'erro' => '', 'dados' => $dados];
    }

    /** @param array<string, mixed> $dados */
    public static function criar(array $dados): int
    {
        $pdo = Identidades::pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO certificacao_participativa
                (associado_id, certificadora_id, tipo_certificacao, numero_certificado,
                 data_emissao, data_validade, status, organismo_certificador, nome_grupo,
                 documento_url, declaracao_compromisso_url, observacoes, excluido, created_at, updated_at)
             VALUES
                (:associado_id, :certificadora_id, :tipo_certificacao, :numero_certificado,
                 :data_emissao, :data_validade, :status, :organismo_certificador, :nome_grupo,
                 :documento_url, :declaracao_compromisso_url, :observacoes, 0, NOW(), NOW())"
        );
        $stmt->execute($dados);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $dados */
    public static function atualizar(int $id, array $dados): void
    {
        $stmt = Identidades::pdo()->prepare(
            "UPDATE certificacao_participativa SET
                associado_id = :associado_id,
                certificadora_id = :certificadora_id,
                tipo_certificacao = :tipo_certificacao,
                numero_certificado = :numero_certificado,
                data_emissao = :data_emissao,
                data_validade = :data_validade,
                status = :status,
                organismo_certificador = :organismo_certificador,
                nome_grupo = :nome_grupo,
                documento_url = :documento_url,
                declaracao_compromisso_url = :declaracao_compromisso_url,
                observacoes = :observacoes,
                updated_at = NOW()
              WHERE id = :id AND excluido = 0"
        );

        $stmt->execute($dados + ['id' => $id]);
    }

    /** @param array<string, mixed> $dados */
    public static function renovar(int $id, array $dados): void
    {
        $stmt = Identidades::pdo()->prepare(
            "UPDATE certificacao_participativa SET
                numero_certificado = :numero_certificado,
                data_emissao = :data_emissao,
                data_validade = :data_validade,
                status = 'Ativa',
                data_renovacao = CURDATE(),
                observacoes = CONCAT(IFNULL(observacoes, ''), :observacoes),
                updated_at = NOW()
              WHERE id = :id AND excluido = 0"
        );

        $observacao = "\n\n[Renovado em " . date('d/m/Y H:i') . '] ' . (string) $dados['observacoes'];
        $stmt->execute([
            'numero_certificado' => $dados['numero_certificado'],
            'data_emissao' => $dados['data_emissao'],
            'data_validade' => $dados['data_validade'],
            'observacoes' => $observacao,
            'id' => $id,
        ]);
    }

    public static function excluir(int $id): void
    {
        Identidades::pdo()
            ->prepare('UPDATE certificacao_participativa SET excluido = 1, updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function propriedades(int $associadoId): array
    {
        $stmt = Identidades::pdo()->prepare(
            'SELECT propriedade, area_total, cidade, estado FROM propriedades WHERE associado_id = :id ORDER BY propriedade'
        );
        $stmt->execute(['id' => $associadoId]);

        return $stmt->fetchAll();
    }
}
