<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Módulos de fiscalização do perfil técnico/gestor: planos, insumos aprovados,
 * visitas, roteiros, não conformidades, validação de insumos, relatórios e CNPO.
 */
final class SocialModulos
{
    /** @return array<string, array<string, mixed>> */
    public static function modulos(): array
    {
        return [
            'planos' => [
                'chave' => 'planos.analise',
                'titulo' => 'Planos de Manejo — Análise',
                'descricao' => 'Planos por safra, versão e situação.',
            ],
            'insumos_aprovados' => [
                'chave' => 'insumos.aprovados',
                'titulo' => 'Insumos Aprovados',
                'descricao' => 'Lista nacional de insumos permitidos (Portaria 52/2021).',
            ],
            'visitas' => [
                'chave' => 'visitas.inspecoes',
                'titulo' => 'Visitas e Inspeções',
                'descricao' => 'Agenda, roteiros, ciência e conformidade.',
            ],
            'roteiros' => [
                'chave' => 'roteiros.templates',
                'titulo' => 'Roteiros (Templates)',
                'descricao' => 'Modelos de roteiro, versão e itens de checklist.',
            ],
            'ncs' => [
                'chave' => 'nao.conformidades',
                'titulo' => 'Não Conformidades',
                'descricao' => 'NCs por visita, gravidade, prazo e situação.',
            ],
            'validacao' => [
                'chave' => 'validacao.insumos',
                'titulo' => 'Validação de Insumos',
                'descricao' => 'Conferência do status orgânico dos insumos em estoque.',
            ],
            'cnpo' => [
                'chave' => 'consulta.cnpo',
                'titulo' => 'Consulta CNPO',
                'descricao' => 'Consulta pública do Cadastro Nacional de Produtores Orgânicos.',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function modulo(string $chave): ?array
    {
        return self::modulos()[$chave] ?? null;
    }

    /** @param array<string, mixed> $identidade */
    public static function podeEscrever(array $identidade): bool
    {
        return Autorizacao::pode($identidade, 'fiscalizacao.write')
            || Autorizacao::pode($identidade, 'fiscalizacao.review')
            || Autorizacao::ehAdmin($identidade);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(string $modulo, string $busca = '', string $status = ''): array
    {
        $busca = trim($busca);
        $status = trim($status);
        $params = [];
        $where = '';
        $like = '%' . $busca . '%';

        $sql = match ($modulo) {
            'planos' => "SELECT p.id, p.ano_safra, p.titulo, p.status, p.versao, p.area_total_ha, p.created_at,
                                CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                                COALESCE(pr.propriedade, '—') AS propriedade
                           FROM plano_manejo p
                           LEFT JOIN associados a ON a.id = p.associado_id
                           LEFT JOIN propriedades pr ON pr.id = p.propriedade_id
                          WHERE p.excluido = 0",
            'insumos_aprovados' => "SELECT id, nome, nome_comercial, categoria, fabricante,
                                           COALESCE(NULLIF(principio_ativo, ''), NULLIF(composicao, ''), '—') AS principio,
                                           permitido, normativa
                                      FROM insumos_organicos_aprovados
                                     WHERE 1 = 1",
            'visitas' => "SELECT v.id, v.data_visita, v.hora_inicio, v.status, v.conformidade,
                                 v.ciente, v.ciente_recusada, v.pontuacao,
                                 COALESCE(p.propriedade, '—') AS propriedade,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                                 COALESCE(r.nome, '—') AS roteiro
                            FROM visitas_tecnicas v
                            LEFT JOIN associados a ON a.id = v.associado_id
                            LEFT JOIN propriedades p ON p.id = v.propriedade_id
                            LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                           WHERE 1 = 1",
            'roteiros' => "SELECT id, codigo, nome, tipo, versao, ativo,
                                  CASE WHEN checklist IS NOT NULL AND JSON_VALID(checklist)
                                       THEN JSON_LENGTH(checklist) ELSE 0 END AS itens
                             FROM roteiros_inspecao WHERE 1 = 1",
            'ncs' => "SELECT nc.id, nc.descricao, nc.gravidade, nc.status, nc.prazo_correcao, nc.acao_corretiva,
                             v.data_visita,
                             CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS produtor,
                             COALESCE(p.propriedade, '—') AS propriedade
                        FROM nao_conformidades nc
                        LEFT JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                        LEFT JOIN associados a ON a.id = v.associado_id
                        LEFT JOIN propriedades p ON p.id = v.propriedade_id
                       WHERE 1 = 1",
            'validacao' => "SELECT i.id, i.nome, i.quantidade_estoque, i.tipo_insumo, i.aprovado_organico,
                                   i.data_validade, i.certificacao_insumo,
                                   COALESCE(f.nome, '—') AS fornecedor
                              FROM insumos i
                              LEFT JOIN fornecedores f ON f.id = i.fornecedor_id
                             WHERE i.excluido = 0",
            'cnpo' => "SELECT id, nome_produtor, entidade, tipo_entidade, cidade, uf, escopo, situacao
                          FROM cnpo WHERE nome_produtor IS NOT NULL AND nome_produtor <> ''",
            default => '',
        };

        if ($sql === '') {
            return [];
        }

        $camposBusca = match ($modulo) {
            'planos' => ['p.ano_safra', 'p.titulo', 'a.nome', 'pr.propriedade'],
            'insumos_aprovados' => ['nome', 'nome_comercial', 'fabricante', 'principio_ativo'],
            'visitas' => ['p.propriedade', 'a.nome', 'r.nome'],
            'roteiros' => ['nome', 'codigo', 'tipo'],
            'ncs' => ['nc.descricao', 'a.nome', 'p.propriedade'],
            'validacao' => ['i.nome', 'f.nome'],
            'cnpo' => ['nome_produtor', 'entidade', 'cidade', 'uf'],
            default => [],
        };
        if ($busca !== '' && $camposBusca !== []) {
            $partes = [];
            foreach ($camposBusca as $indice => $campo) {
                $partes[] = $campo . ' LIKE :busca' . $indice;
                $params['busca' . $indice] = $like;
            }
            $where .= ' AND (' . implode(' OR ', $partes) . ')';
        }

        $campoStatus = match ($modulo) {
            'planos' => 'p.status',
            'visitas' => 'v.status',
            'ncs' => 'nc.status',
            default => '',
        };
        if ($status !== '' && $campoStatus !== '') {
            $where .= ' AND ' . $campoStatus . ' = :status';
            $params['status'] = $status;
        }

        $ordens = [
            'planos' => 'p.created_at DESC',
            'insumos_aprovados' => '1 ASC',
            'cnpo' => '1 ASC',
            'visitas' => 'v.data_visita DESC',
            'ncs' => 'v.data_visita DESC',
            'roteiros' => 'nome ASC',
            'validacao' => 'i.nome ASC',
        ];
        $ordem = $ordens[$modulo];
        $sql .= $where . ' ORDER BY ' . $ordem . ' LIMIT 500';

        try {
            $stmt = Identidades::pdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::listar - ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function visitasConfirmacao(): array
    {
        try {
            $stmt = Identidades::pdo()->query(
                "SELECT v.id, v.data_visita, v.hora_inicio, v.ciente, v.ciente_em,
                        v.ciente_recusada, v.ciente_recusada_motivo, v.ciente_recusada_em,
                        v.gestor_ciente, v.gestor_ciente_em,
                        CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado_nome,
                        COALESCE(p.propriedade, '—') AS propriedade_nome,
                        COALESCE(r.nome, 'Roteiro de inspeção') AS roteiro_nome,
                        TRIM(CONCAT(COALESCE(t.nome, ''), ' ', COALESCE(t.sobrenome, ''))) AS tecnico_nome
                   FROM visitas_tecnicas v
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                   LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                   LEFT JOIN associados t ON t.id = v.tecnico_responsavel_id
                  WHERE v.status = 'Agendada' AND v.data_visita >= CURDATE()
                  ORDER BY v.data_visita ASC, v.id ASC LIMIT 100"
            );

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::visitasConfirmacao - ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Registra a ciência individual do gestor sobre uma visita agendada.
     *
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function registrarCienteVisita(int $visitaId, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode registrar ciência de visitas.'];
        }
        if ($visitaId <= 0) {
            return ['ok' => false, 'erro' => 'Visita inválida.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "UPDATE visitas_tecnicas
                    SET gestor_ciente = 1, gestor_ciente_em = NOW(),
                        gestor_ciente_user_id = :usuario, gestor_ciente_user_table = :tabela
                  WHERE id = :id AND status = 'Agendada' AND data_visita >= CURDATE()"
            );
            $stmt->execute([
                'usuario' => (int) ($identidade['id'] ?? 0) ?: null,
                'tabela' => (string) ($identidade['origem'] ?? 'usuarios'),
                'id' => $visitaId,
            ]);

            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'erro' => 'Visita não encontrada, já realizada ou fora do prazo.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::registrarCienteVisita - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível registrar a ciência.'];
        }

        Auditoria::registrar('VISITA_GESTOR_CIENTE', (int) ($identidade['id'] ?? 0), 'visitas_tecnicas', $visitaId, 'ciencia do gestor', 'social');

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Reagenda uma visita agendada, limpando as confirmações anteriores.
     *
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function reagendarVisita(int $visitaId, string $data, string $hora, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode reagendar visitas.'];
        }

        $data = trim($data);
        $hora = trim($hora);
        if ($visitaId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return ['ok' => false, 'erro' => 'Informe a nova data da visita.'];
        }
        if ($data < date('Y-m-d')) {
            return ['ok' => false, 'erro' => 'A nova data deve ser hoje ou futura.'];
        }
        if ($hora !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            return ['ok' => false, 'erro' => 'Horário inválido.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "UPDATE visitas_tecnicas
                    SET data_visita = :data, hora_inicio = COALESCE(NULLIF(:hora, ''), hora_inicio),
                        ciente = 0, ciente_em = NULL, ciente_recusada = 0,
                        ciente_recusada_motivo = NULL, ciente_recusada_em = NULL,
                        gestor_ciente = 0, gestor_ciente_em = NULL,
                        gestor_ciente_user_id = NULL, gestor_ciente_user_table = NULL
                  WHERE id = :id AND status = 'Agendada'"
            );
            $stmt->execute(['data' => $data, 'hora' => $hora, 'id' => $visitaId]);

            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'erro' => 'Visita não encontrada ou já finalizada.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::reagendarVisita - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível reagendar a visita.'];
        }

        Auditoria::registrar('VISITA_REAGENDADA_GESTOR', (int) ($identidade['id'] ?? 0), 'visitas_tecnicas', $visitaId, 'nova data=' . $data . ' hora=' . $hora, 'social');

        return ['ok' => true, 'erro' => ''];
    }

    /** @return list<array<string, mixed>> */
    public static function visitasPendentesAgricultor(int $associadoId): array
    {
        if ($associadoId <= 0) {
            return [];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "SELECT v.id, v.data_visita, v.hora_inicio, v.ciente, v.ciente_recusada,
                        v.ciente_recusada_motivo,
                        COALESCE(p.propriedade, '—') AS propriedade_nome,
                        COALESCE(r.nome, 'Roteiro de inspeção') AS roteiro_nome,
                        TRIM(CONCAT(COALESCE(t.nome, ''), ' ', COALESCE(t.sobrenome, ''))) AS tecnico_nome
                   FROM visitas_tecnicas v
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                   LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                   LEFT JOIN associados t ON t.id = v.tecnico_responsavel_id
                  WHERE v.associado_id = :assoc
                    AND v.status = 'Agendada'
                    AND v.data_visita >= CURDATE()
                    AND v.ciente = 0 AND v.ciente_recusada = 0
                  ORDER BY v.data_visita ASC, v.id ASC LIMIT 50"
            );
            $stmt->execute(['assoc' => $associadoId]);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::visitasPendentesAgricultor - ' . $e->getMessage());

            return [];
        }
    }

    /** @return array{ok: bool, erro: string} */
    public static function confirmarVisitaAgricultor(int $visitaId, int $associadoId): array
    {
        if ($visitaId <= 0 || $associadoId <= 0) {
            return ['ok' => false, 'erro' => 'Visita inválida.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "UPDATE visitas_tecnicas
                    SET ciente = 1, ciente_em = NOW(), ciente_recusada = 0,
                        ciente_recusada_motivo = NULL, ciente_recusada_em = NULL
                  WHERE id = :id AND associado_id = :assoc AND status = 'Agendada'"
            );
            $stmt->execute(['id' => $visitaId, 'assoc' => $associadoId]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'erro' => 'Visita não encontrada ou já respondida.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::confirmarVisitaAgricultor - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível registrar a confirmação.'];
        }

        Auditoria::registrar('VISITA_CONFIRMADA_AGRICULTOR', null, 'visitas_tecnicas', $visitaId, 'confirmacao do agricultor', 'producao');

        return ['ok' => true, 'erro' => ''];
    }

    /** @return array{ok: bool, erro: string} */
    public static function recusarVisitaAgricultor(int $visitaId, int $associadoId, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($visitaId <= 0 || $associadoId <= 0) {
            return ['ok' => false, 'erro' => 'Visita inválida.'];
        }
        if ($motivo === '') {
            return ['ok' => false, 'erro' => 'Informe o motivo para solicitar o reagendamento.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "UPDATE visitas_tecnicas
                    SET ciente = 0, ciente_em = NULL, ciente_recusada = 1,
                        ciente_recusada_motivo = :motivo, ciente_recusada_em = NOW()
                  WHERE id = :id AND associado_id = :assoc AND status = 'Agendada'"
            );
            $stmt->execute(['motivo' => mb_substr($motivo, 0, 500), 'id' => $visitaId, 'assoc' => $associadoId]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'erro' => 'Visita não encontrada ou já respondida.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::recusarVisitaAgricultor - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível registrar a recusa.'];
        }

        Auditoria::registrar('VISITA_RECUSADA_AGRICULTOR', null, 'visitas_tecnicas', $visitaId, 'recusa do agricultor: ' . mb_substr($motivo, 0, 120), 'producao');

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Plano completo com agricultor, propriedade, cultivos e histórico de pareceres.
     *
     * @return array<string, mixed>|null
     */
    public static function planoCompleto(int $planoId): ?array
    {
        if ($planoId <= 0) {
            return null;
        }

        $pdo = Identidades::pdo();
        try {
            $stmt = $pdo->prepare(
                'SELECT p.*, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                        pr.propriedade AS propriedade_nome
                   FROM plano_manejo p
                   LEFT JOIN associados a ON a.id = p.associado_id
                   LEFT JOIN propriedades pr ON pr.id = p.propriedade_id
                  WHERE p.id = :id AND p.excluido = 0 LIMIT 1'
            );
            $stmt->execute(['id' => $planoId]);
            $plano = $stmt->fetch();
            if (!is_array($plano)) {
                return null;
            }

            $stmt = $pdo->prepare('SELECT * FROM plano_manejo_cultivos WHERE plano_manejo_id = :id ORDER BY id ASC');
            $stmt->execute(['id' => $planoId]);
            $plano['cultivos'] = $stmt->fetchAll();
            $plano['pareceres'] = self::pareceresPlano($planoId);

            return $plano;
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::planoCompleto - ' . $e->getMessage());

            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function pareceresPlano(int $planoId): array
    {
        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT id, usuario_id, usuario_tabela, status, parecer, sugestoes, created_at
                   FROM plano_manejo_pareceres
                  WHERE plano_manejo_id = :id
                  ORDER BY created_at ASC, id ASC'
            );
            $stmt->execute(['id' => $planoId]);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::pareceresPlano - ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Registra parecer/sugestões de ajuste e aplica a decisão de status.
     *
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function registrarParecer(
        int $planoId,
        string $status,
        string $parecer,
        string $sugestoes,
        array $identidade
    ): array {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode registrar parecer neste plano.'];
        }
        if ($planoId <= 0) {
            return ['ok' => false, 'erro' => 'Plano inválido.'];
        }

        $status = trim($status);
        $parecer = trim($parecer);
        $sugestoes = trim($sugestoes);
        $permitidos = ['Aprovado', 'Reprovado', 'Revisão Necessária', 'Sugestão'];
        if (!in_array($status, $permitidos, true)) {
            return ['ok' => false, 'erro' => 'Selecione uma decisão válida.'];
        }
        if ($parecer === '' && $sugestoes === '') {
            return ['ok' => false, 'erro' => 'Informe o parecer ou as sugestões de ajuste.'];
        }

        $pdo = Identidades::pdo();
        try {
            $stmt = $pdo->prepare('SELECT id, status FROM plano_manejo WHERE id = :id AND excluido = 0 LIMIT 1');
            $stmt->execute(['id' => $planoId]);
            if ($stmt->fetch() === false) {
                return ['ok' => false, 'erro' => 'Plano não encontrado.'];
            }

            if ($status !== 'Sugestão') {
                $campos = 'status = :status, motivo_reprovacao = :parecer, aprovado_por = :por';
                if ($status === 'Aprovado') {
                    $campos .= ', data_aprovacao = CURDATE()';
                } elseif ($status === 'Reprovado') {
                    $campos .= ', data_reprovacao = CURDATE()';
                }
                $stmt = $pdo->prepare(
                    'UPDATE plano_manejo SET ' . $campos . ', updated_at = NOW() WHERE id = :id'
                );
                $stmt->execute([
                    'status' => $status,
                    'parecer' => $parecer !== '' ? $parecer : $sugestoes,
                    'por' => (int) ($identidade['id'] ?? 0) ?: null,
                    'id' => $planoId,
                ]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO plano_manejo_pareceres
                    (plano_manejo_id, usuario_id, usuario_tabela, status, parecer, sugestoes, created_at)
                 VALUES (:plano, :usuario, :tabela, :status, :parecer, :sugestoes, NOW())'
            );
            $stmt->execute([
                'plano' => $planoId,
                'usuario' => (int) ($identidade['id'] ?? 0) ?: null,
                'tabela' => (string) ($identidade['origem'] ?? 'usuarios'),
                'status' => $status,
                'parecer' => $parecer !== '' ? $parecer : null,
                'sugestoes' => $sugestoes !== '' ? $sugestoes : null,
            ]);
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::registrarParecer - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível registrar o parecer.'];
        }

        Auditoria::registrar(
            'PLANO_PARECER',
            (int) ($identidade['id'] ?? 0),
            'plano_manejo',
            $planoId,
            'status=' . $status . ' parecer=' . mb_substr($parecer, 0, 120),
            'social'
        );

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * @return array{colunas: list<string>, linhas: list<array<string, mixed>>}
     */
    public static function relatorio(string $tipo, string $inicio, string $fim): array
    {
        $inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) ? $inicio : '';
        $fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim) ? $fim : '';
        $params = [];
        $where = 'WHERE 1 = 1';
        if ($inicio !== '') {
            $where .= ' AND :campo >= :inicio';
            $params['inicio'] = $inicio;
        }
        if ($fim !== '') {
            $where .= ' AND :campo <= :fim';
            $params['fim'] = $fim;
        }

        try {
            if ($tipo === 'visitas') {
                $campo = 'v.data_visita';
                $where = str_replace(':campo', $campo, $where);
                $sql = "SELECT v.id, v.data_visita, v.hora_inicio, v.status, v.conformidade, v.pontuacao,
                               COALESCE(p.propriedade, '—') AS propriedade,
                               CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                               COALESCE(r.nome, '—') AS roteiro
                          FROM visitas_tecnicas v
                          LEFT JOIN associados a ON a.id = v.associado_id
                          LEFT JOIN propriedades p ON p.id = v.propriedade_id
                          LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                          $where ORDER BY v.data_visita DESC LIMIT 1000";
                $colunas = ['Data', 'Horário', 'Propriedade', 'Agricultor', 'Roteiro', 'Status', 'Conformidade', 'Pontuação'];
                $linhas = self::linhasRelatorio($sql, $params, ['id', 'data_visita', 'hora_inicio', 'propriedade', 'agricultor', 'roteiro', 'status', 'conformidade', 'pontuacao']);

                return ['colunas' => $colunas, 'linhas' => $linhas];
            }

            if ($tipo === 'ncs') {
                $campo = 'v.data_visita';
                $where = str_replace(':campo', $campo, $where);
                $sql = "SELECT nc.id, v.data_visita, nc.descricao, nc.gravidade, nc.status, nc.prazo_correcao,
                               CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS produtor,
                               COALESCE(p.propriedade, '—') AS propriedade
                          FROM nao_conformidades nc
                          LEFT JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                          LEFT JOIN associados a ON a.id = v.associado_id
                          LEFT JOIN propriedades p ON p.id = v.propriedade_id
                          $where ORDER BY v.data_visita DESC LIMIT 1000";
                $colunas = ['Data visita', 'Produtor', 'Propriedade', 'Descrição', 'Gravidade', 'Prazo', 'Status'];
                $linhas = self::linhasRelatorio($sql, $params, ['id', 'data_visita', 'produtor', 'propriedade', 'descricao', 'gravidade', 'prazo_correcao', 'status']);

                return ['colunas' => $colunas, 'linhas' => $linhas];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::relatorio - ' . $e->getMessage());
        }

        return ['colunas' => [], 'linhas' => []];
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $colunas
     * @return list<array<string, mixed>>
     */
    private static function linhasRelatorio(string $sql, array $params, array $colunas): array
    {
        $stmt = Identidades::pdo()->prepare($sql);
        $stmt->execute($params);
        $todas = $stmt->fetchAll();
        $linhas = [];
        foreach ($todas as $linha) {
            $saida = [];
            foreach ($colunas as $coluna) {
                $saida[$coluna] = $linha[$coluna] ?? '';
            }
            $linhas[] = $saida;
        }

        return $linhas;
    }

    /** @return array<string, int> */
    public static function kpis(): array
    {
        $pdo = Identidades::pdo();
        $q = static function (string $sql) use ($pdo): int {
            try {
                return (int) $pdo->query($sql)->fetchColumn();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        return [
            'associados' => $q("SELECT COUNT(*) FROM associados WHERE status = 'ativo'"),
            'certificacoes' => $q("SELECT COUNT(*) FROM certificacao_participativa WHERE status = 'Ativa' AND excluido = 0"),
            'planos' => $q("SELECT COUNT(*) FROM plano_manejo WHERE status = 'Em Análise' AND excluido = 0"),
            'visitas' => $q("SELECT COUNT(*) FROM visitas_tecnicas WHERE status = 'Agendada'"),
            'ncs' => $q("SELECT COUNT(*) FROM nao_conformidades WHERE status IN ('Aberta','Em Correção')"),
            'insumos_baixo' => $q("SELECT COUNT(*) FROM insumos WHERE quantidade_estoque < 10 AND excluido = 0"),
            'propriedades' => $q('SELECT COUNT(*) FROM propriedades'),
            'atividades' => $q("SELECT COUNT(*) FROM atividades_diarias WHERE excluido = 0 AND YEARWEEK(data_atividade,1) = YEARWEEK(CURDATE(),1)"),
            'animais' => $q('SELECT COUNT(*) FROM animais WHERE excluido = 0'),
            'lotes_animais' => $q('SELECT COUNT(*) FROM animal_lotes WHERE excluido = 0'),
            'cnpo' => $q('SELECT COUNT(*) FROM cnpo'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function proximasVisitas(int $limite = 6): array
    {
        try {
            $stmt = Identidades::pdo()->prepare(
                "SELECT v.id, v.data_visita, v.hora_inicio, v.status, v.ciente, v.ciente_recusada,
                        COALESCE(p.propriedade, '—') AS propriedade,
                        CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor
                   FROM visitas_tecnicas v
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                  WHERE v.status = 'Agendada'
                  ORDER BY v.data_visita ASC, v.id DESC LIMIT " . max(1, min(20, $limite))
            );
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::proximasVisitas - ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public static function roteiro(int $id): ?array
    {
        try {
            $stmt = Identidades::pdo()->prepare('SELECT * FROM roteiros_inspecao WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $linha = $stmt->fetch();

            return is_array($linha) ? $linha : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string, id: int}
     */
    public static function salvarRoteiro(?int $id, array $post, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode gerenciar roteiros.', 'id' => 0];
        }

        $nome = trim((string) ($post['nome'] ?? ''));
        $codigo = trim((string) ($post['codigo'] ?? ''));
        $tipo = trim((string) ($post['tipo'] ?? ''));
        $tipos = ['Visita Inicial', 'Visita Regular', 'Visita de Renovação', 'Visita Surpresa', 'Auditoria Interna'];
        if ($nome === '' || !in_array($tipo, $tipos, true)) {
            return ['ok' => false, 'erro' => 'Informe o nome e um tipo de visita válido.', 'id' => 0];
        }

        $pontuacaoMinima = (int) ($post['pontuacao_minima'] ?? 70);
        if ($pontuacaoMinima < 0 || $pontuacaoMinima > 100) {
            $pontuacaoMinima = 70;
        }

        $checklist = [];
        $criticos = [];
        $raw = $post['checklist'] ?? [];
        if (is_array($raw)) {
            $indice = 1;
            foreach ($raw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $pergunta = trim((string) ($item['item'] ?? ''));
                if ($pergunta === '') {
                    continue;
                }
                $obrigatorio = !empty($item['obrigatorio']);
                $checklist[] = [
                    'id' => $indice,
                    'item' => $pergunta,
                    'categoria' => trim((string) ($item['categoria'] ?? 'Geral')) ?: 'Geral',
                    'peso' => max(1, (int) ($item['peso'] ?? 10)),
                    'obrigatorio' => $obrigatorio,
                ];
                if ($obrigatorio) {
                    $criticos[] = (string) $indice;
                }
                $indice++;
            }
        }
        if ($checklist === []) {
            return ['ok' => false, 'erro' => 'Adicione pelo menos um item ao checklist.', 'id' => 0];
        }

        $pdo = Identidades::pdo();
        try {
            if ($id !== null && $id > 0) {
                $existente = self::roteiro($id);
                if ($existente === null) {
                    return ['ok' => false, 'erro' => 'Roteiro não encontrado.', 'id' => 0];
                }
                $versao = (int) $existente['versao'] + 1;
                $pdo->prepare(
                    'UPDATE roteiros_inspecao
                        SET nome = :nome, codigo = :codigo, tipo = :tipo, descricao = :descricao,
                            objetivo = :objetivo, checklist = :checklist, itens_criticos = :criticos,
                            pontuacao_minima = :pontuacao, versao = :versao, updated_at = NOW()
                      WHERE id = :id'
                )->execute([
                    'nome' => $nome,
                    'codigo' => $codigo !== '' ? $codigo : null,
                    'tipo' => $tipo,
                    'descricao' => trim((string) ($post['descricao'] ?? '')),
                    'objetivo' => trim((string) ($post['objetivo'] ?? '')),
                    'checklist' => json_encode($checklist, JSON_UNESCAPED_UNICODE),
                    'criticos' => json_encode($criticos, JSON_UNESCAPED_UNICODE),
                    'pontuacao' => $pontuacaoMinima,
                    'versao' => $versao,
                    'id' => $id,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO roteiros_inspecao
                        (nome, codigo, tipo, descricao, objetivo, checklist, itens_criticos, pontuacao_minima, ativo, versao, created_by, created_at, updated_at)
                     VALUES (:nome, :codigo, :tipo, :descricao, :objetivo, :checklist, :criticos, :pontuacao, 1, 1, :created_by, NOW(), NOW())'
                )->execute([
                    'nome' => $nome,
                    'codigo' => $codigo !== '' ? $codigo : null,
                    'tipo' => $tipo,
                    'descricao' => trim((string) ($post['descricao'] ?? '')),
                    'objetivo' => trim((string) ($post['objetivo'] ?? '')),
                    'checklist' => json_encode($checklist, JSON_UNESCAPED_UNICODE),
                    'criticos' => json_encode($criticos, JSON_UNESCAPED_UNICODE),
                    'pontuacao' => $pontuacaoMinima,
                    'created_by' => (int) ($identidade['id'] ?? 0),
                ]);
                $id = (int) $pdo->lastInsertId();
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::salvarRoteiro - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar o roteiro.', 'id' => 0];
        }

        Auditoria::registrar('ROTEIRO_SALVO', (int) ($identidade['id'] ?? 0), 'roteiros_inspecao', (int) $id, 'nome=' . $nome . ' tipo=' . $tipo, 'social');

        return ['ok' => true, 'erro' => '', 'id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function excluirRoteiro(int $id, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode excluir roteiros.'];
        }
        try {
            $stmt = Identidades::pdo()->prepare('DELETE FROM roteiros_inspecao WHERE id = :id');
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() === 0) {
                return ['ok' => false, 'erro' => 'Roteiro não encontrado.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::excluirRoteiro - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível excluir o roteiro.'];
        }

        Auditoria::registrar('ROTEIRO_EXCLUIDO', (int) ($identidade['id'] ?? 0), 'roteiros_inspecao', $id, 'exclusao de roteiro', 'social');

        return ['ok' => true, 'erro' => ''];
    }

    /** @return array<string, mixed>|null */
    public static function visitaCompleta(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT v.*, r.nome AS roteiro_nome, r.checklist, r.itens_criticos,
                        p.propriedade AS propriedade_nome, p.cidade, p.estado AS uf,
                        CONCAT(COALESCE(a.nome, ""), " ", COALESCE(a.sobrenome, "")) AS associado_nome,
                        TRIM(CONCAT(COALESCE(t.nome, ""), " ", COALESCE(t.sobrenome, ""))) AS tecnico_nome
                   FROM visitas_tecnicas v
                   LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN associados t ON t.id = v.tecnico_responsavel_id
                  WHERE v.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $visita = $stmt->fetch();
            if (!is_array($visita)) {
                return null;
            }
            $visita['resultado'] = self::jsonDecode((string) ($visita['resultado'] ?? ''), []);
            $visita['evidencias'] = self::jsonDecode((string) ($visita['evidencias_fotos'] ?? ''), []);

            $stmt = Identidades::pdo()->prepare(
                'SELECT nc.*, v.data_visita, CONCAT(COALESCE(a.nome, ""), " ", COALESCE(a.sobrenome, "")) AS produtor,
                        p.propriedade AS propriedade_nome
                   FROM nao_conformidades nc
                   LEFT JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                  WHERE nc.visita_tecnica_id = :id
                  ORDER BY nc.id ASC'
            );
            $stmt->execute(['id' => $id]);
            $visita['ncs'] = $stmt->fetchAll();

            return $visita;
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::visitaCompleta - ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Contexto para a análise do técnico durante a realização da inspeção:
     * dados do produtor, propriedade, plano de manejo, certificações, insumos
     * e NCs anteriores do mesmo associado.
     *
     * @return array<string, mixed>
     */
    public static function contextoInspecao(int $visitaId): array
    {
        $visita = self::visitaCompleta($visitaId);
        if ($visita === null) {
            return [];
        }
        $associadoId = (int) ($visita['associado_id'] ?? 0);
        $propriedadeId = (int) ($visita['propriedade_id'] ?? 0);
        $pdo = Identidades::pdo();

        $propriedade = null;
        if ($propriedadeId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM propriedades WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $propriedadeId]);
            $propriedade = $stmt->fetch() ?: null;
        }

        $associado = null;
        if ($associadoId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM associados WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $associadoId]);
            $associado = $stmt->fetch() ?: null;
            if (is_array($associado)) {
                unset($associado['senha']);
            }
        }

        $planos = [];
        if ($associadoId > 0) {
            $stmt = $pdo->prepare(
                'SELECT id, ano_safra, titulo, status, versao, area_total_ha, area_organica_ha,
                        area_conversao_ha, topografia, descricao_solo, fonte_agua, clima,
                        manejo_pragas, manejo_doencas, praticas_conservacao, rotacao_culturas,
                        compostagem, biofertilizantes, adubacao_verde
                   FROM plano_manejo
                  WHERE associado_id = :assoc AND excluido = 0
                  ORDER BY id DESC LIMIT 3'
            );
            $stmt->execute(['assoc' => $associadoId]);
            $planos = $stmt->fetchAll();
        }

        $certificacoes = [];
        if ($associadoId > 0) {
            $stmt = $pdo->prepare(
                'SELECT id, tipo_certificacao, numero_certificado, status, data_emissao, data_validade,
                        organismo_certificador, nome_grupo
                   FROM certificacao_participativa
                  WHERE associado_id = :assoc AND excluido = 0
                  ORDER BY data_validade DESC LIMIT 5'
            );
            $stmt->execute(['assoc' => $associadoId]);
            $certificacoes = $stmt->fetchAll();
        }

        $insumos = [];
        if ($associadoId > 0) {
            $stmt = $pdo->prepare(
                'SELECT id, nome, quantidade_estoque, tipo_insumo, aprovado_organico, data_validade, certificacao_insumo
                   FROM insumos
                  WHERE associado_id = :assoc AND excluido = 0
                  ORDER BY nome ASC LIMIT 20'
            );
            $stmt->execute(['assoc' => $associadoId]);
            $insumos = $stmt->fetchAll();
        }

        $ncsAnteriores = [];
        if ($associadoId > 0) {
            $stmt = $pdo->prepare(
                'SELECT nc.id, nc.descricao, nc.gravidade, nc.status, v.data_visita
                   FROM nao_conformidades nc
                   JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                  WHERE v.associado_id = :assoc AND v.id <> :visita
                  ORDER BY v.data_visita DESC LIMIT 10'
            );
            $stmt->execute(['assoc' => $associadoId, 'visita' => $visitaId]);
            $ncsAnteriores = $stmt->fetchAll();
        }

        $animais = [];
        if ($associadoId > 0) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, nome, especie, raca, sexo, status
                       FROM animais
                      WHERE associado_id = :assoc
                      ORDER BY nome ASC LIMIT 20'
                );
                $stmt->execute(['assoc' => $associadoId]);
                $animais = $stmt->fetchAll();
            } catch (\Throwable) {
                $animais = [];
            }
        }

        return [
            'propriedade' => is_array($propriedade) ? $propriedade : null,
            'associado' => is_array($associado) ? $associado : null,
            'planos' => $planos,
            'certificacoes' => $certificacoes,
            'insumos' => $insumos,
            'ncs_anteriores' => $ncsAnteriores,
            'animais' => $animais,
        ];
    }

    /**
     * @param array<string, mixed> $visita
     * @return list<array<string, mixed>>
     */
    public static function checklistVisita(array $visita): array
    {
        $roteiro = json_decode((string) ($visita['checklist'] ?? ''), true);
        $itens = is_array($roteiro) ? $roteiro : [];
        $criticos = json_decode((string) ($visita['itens_criticos'] ?? ''), true);
        $criticos = array_map('strval', is_array($criticos) ? $criticos : []);

        require_once dirname(__DIR__, 2) . '/classes/ChecklistPadrao.php';
        if (class_exists('ChecklistPadrao')) {
            $itens = \ChecklistPadrao::mesclar($itens);
        }

        return array_map(static function ($item, int $indice) use ($criticos): array {
            $id = $item['id'] ?? ($indice + 1);
            $obrigatorio = !empty($item['obrigatorio']) || in_array(strval($id), $criticos, true);

            return [
                'id' => $id,
                'item' => $item['item'] ?? $item['pergunta'] ?? 'Item sem descrição',
                'categoria' => $item['categoria'] ?? 'Geral',
                'obrigatorio' => $obrigatorio,
            ];
        }, $itens, array_keys($itens));
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string, visita_id: int}
     */
    public static function realizarVisita(int $visitaId, array $post, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode realizar inspeções.', 'visita_id' => $visitaId];
        }
        if ($visitaId <= 0) {
            return ['ok' => false, 'erro' => 'Visita inválida.', 'visita_id' => 0];
        }

        $pdo = Identidades::pdo();
        try {
            $stmt = $pdo->prepare(
                'SELECT v.*, r.checklist, r.itens_criticos, r.pontuacao_minima
                   FROM visitas_tecnicas v
                   LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                  WHERE v.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $visitaId]);
            $visita = $stmt->fetch();
            if (!is_array($visita)) {
                return ['ok' => false, 'erro' => 'Visita não encontrada.', 'visita_id' => 0];
            }
            if ($visita['status'] !== 'Agendada') {
                return ['ok' => false, 'erro' => 'A visita já não está agendada.', 'visita_id' => $visitaId];
            }

            $horaInicio = trim((string) ($post['hora_inicio'] ?? ''));
            $horaFim = trim((string) ($post['hora_fim'] ?? ''));
            $clima = trim((string) ($post['condicoes_clima'] ?? ''));
            if ($horaInicio === '' || $horaFim === '' || $clima === '') {
                return ['ok' => false, 'erro' => 'Informe hora de início, hora de fim e condições do tempo.', 'visita_id' => $visitaId];
            }
            if ($horaFim <= $horaInicio) {
                return ['ok' => false, 'erro' => 'A hora de fim deve ser posterior à hora de início.', 'visita_id' => $visitaId];
            }

            $checklist = self::checklistVisita($visita);
            if ($checklist === []) {
                return ['ok' => false, 'erro' => 'O roteiro não possui checklist para a inspeção.', 'visita_id' => $visitaId];
            }

            $respostas = $post['item'] ?? [];
            $resultado = [];
            $ncs = [];
            $conformes = 0;
            $na = 0;
            foreach ($checklist as $item) {
                $idItem = strval($item['id']);
                $statusItem = trim((string) (($respostas[$idItem]['status'] ?? '')));
                $observacao = trim((string) (($respostas[$idItem]['obs'] ?? '')));
                if (!in_array($statusItem, ['Conforme', 'Não Conforme', 'N/A'], true)) {
                    return ['ok' => false, 'erro' => 'Responda todos os itens do checklist.', 'visita_id' => $visitaId];
                }
                if ($statusItem === 'Não Conforme' && $observacao === '') {
                    return ['ok' => false, 'erro' => 'Todo item Não Conforme exige uma observação.', 'visita_id' => $visitaId];
                }

                $conforme = $statusItem === 'Conforme';
                $naItem = $statusItem === 'N/A';
                if ($conforme) {
                    $conformes++;
                } elseif ($naItem) {
                    $na++;
                }
                $resultado[$idItem] = ['conforme' => $conforme, 'na' => $naItem, 'observacao' => $observacao];

                if ($statusItem === 'Não Conforme') {
                    $ncs[] = [
                        'item_checklist_id' => (int) $item['id'],
                        'descricao' => $observacao !== '' ? $observacao : $item['item'],
                        'gravidade' => $item['obrigatorio'] ? 'Crítica' : 'Alta',
                        'categoria' => $item['categoria'],
                    ];
                }
            }

            $considerados = count($checklist) - $na;
            $pontuacao = $considerados > 0 ? (int) round(100 * $conformes / $considerados) : 0;
            $pontuacaoMinima = (int) ($visita['pontuacao_minima'] ?? 70);
            $temCritica = false;
            foreach ($ncs as $nc) {
                if ($nc['gravidade'] === 'Crítica') {
                    $temCritica = true;
                    break;
                }
            }
            if ($ncs === []) {
                $conformidade = 'Conforme';
            } elseif ($temCritica || $pontuacao < $pontuacaoMinima) {
                $conformidade = 'Não Conforme';
            } else {
                $conformidade = 'Parcialmente Conforme';
            }

            $assinaturaProdutor = self::salvarAssinatura((string) ($post['assinatura_produtor_url'] ?? ''), 'produtor', $visitaId);
            $assinaturaTecnico = self::salvarAssinatura((string) ($post['assinatura_tecnico_url'] ?? ''), 'tecnico', $visitaId);
            $evidencias = array_values(array_filter((array) ($post['evidencias_fotos'] ?? []), 'is_string'));

            $pdo->prepare(
                "UPDATE visitas_tecnicas
                    SET status = 'Realizada', hora_inicio = :hora_inicio, hora_fim = :hora_fim,
                        condicoes_clima = :clima, observacoes = :observacoes, resultado = :resultado,
                        pontuacao = :pontuacao, pontuacao_maxima = 100, conformidade = :conformidade,
                        evidencias_fotos = :evidencias, assinatura_produtor_url = :ap,
                        assinatura_tecnico_url = :at, updated_at = NOW()
                  WHERE id = :id"
            )->execute([
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'clima' => $clima,
                'observacoes' => trim((string) ($post['observacoes'] ?? '')),
                'resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE),
                'pontuacao' => $pontuacao,
                'conformidade' => $conformidade,
                'evidencias' => $evidencias === [] ? null : json_encode($evidencias, JSON_UNESCAPED_UNICODE),
                'ap' => $assinaturaProdutor !== '' ? $assinaturaProdutor : null,
                'at' => $assinaturaTecnico !== '' ? $assinaturaTecnico : null,
                'id' => $visitaId,
            ]);

            if ($ncs !== []) {
                $stmtNc = $pdo->prepare(
                    'INSERT INTO nao_conformidades
                        (visita_tecnica_id, item_checklist_id, descricao, gravidade, categoria, status, created_at, updated_at)
                     VALUES (:visita, :item, :descricao, :gravidade, :categoria, "Aberta", NOW(), NOW())'
                );
                foreach ($ncs as $nc) {
                    $stmtNc->execute([
                        'visita' => $visitaId,
                        'item' => $nc['item_checklist_id'],
                        'descricao' => mb_substr($nc['descricao'], 0, 1000),
                        'gravidade' => $nc['gravidade'],
                        'categoria' => mb_substr((string) $nc['categoria'], 0, 100),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::realizarVisita - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível finalizar a inspeção.', 'visita_id' => $visitaId];
        }

        Auditoria::registrar('VISITA_REALIZADA', (int) ($identidade['id'] ?? 0), 'visitas_tecnicas', $visitaId, 'pontuacao=' . $pontuacao . ' conformidade=' . $conformidade, 'social');

        return ['ok' => true, 'erro' => '', 'visita_id' => $visitaId];
    }

    /** @return array<string, mixed>|null */
    public static function ncCompleta(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT nc.*, v.data_visita, v.id AS visita_id,
                        CONCAT(COALESCE(a.nome, ""), " ", COALESCE(a.sobrenome, "")) AS produtor,
                        p.propriedade AS propriedade_nome, r.nome AS roteiro_nome
                   FROM nao_conformidades nc
                   LEFT JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                   LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                  WHERE nc.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);

            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::ncCompleta - ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function atualizarNc(int $id, array $post, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode alterar não conformidades.'];
        }
        $status = trim((string) ($post['status'] ?? ''));
        $permitidos = ['Aberta', 'Em Correção', 'Resolvida', 'Não Resolvida', 'Cancelada'];
        if (!in_array($status, $permitidos, true)) {
            return ['ok' => false, 'erro' => 'Status inválido.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'UPDATE nao_conformidades
                    SET status = :status, gravidade = :gravidade, prazo_correcao = :prazo,
                        responsavel_correcao = :responsavel, acao_corretiva = :acao,
                        observacoes = :obs,
                        data_resolucao = ' . ($status === 'Resolvida' ? 'CURDATE()' : 'NULL') . ',
                        verificado_por = ' . ($status === 'Resolvida' ? ':verificador' : 'NULL') . ',
                        updated_at = NOW()
                  WHERE id = :id'
            );
            $stmt->execute([
                'status' => $status,
                'gravidade' => in_array((string) ($post['gravidade'] ?? ''), ['Baixa', 'Média', 'Alta', 'Crítica'], true) ? (string) $post['gravidade'] : 'Média',
                'prazo' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($post['prazo_correcao'] ?? '')) ? (string) $post['prazo_correcao'] : null,
                'responsavel' => trim((string) ($post['responsavel_correcao'] ?? '')),
                'acao' => trim((string) ($post['acao_corretiva'] ?? '')),
                'obs' => trim((string) ($post['observacoes'] ?? '')),
                'verificador' => (int) ($identidade['id'] ?? 0),
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::atualizarNc - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível atualizar a não conformidade.'];
        }

        Auditoria::registrar('NC_ATUALIZADA', (int) ($identidade['id'] ?? 0), 'nao_conformidades', $id, 'status=' . $status, 'social');

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * @param array<string, mixed> $arquivo
     * @return array{ok: bool, url: string, erro: string}
     */
    public static function uploadEvidenciaVisita(array $arquivo): array
    {
        return self::salvarImagem($arquivo, 'visitas_tecnicas', 'visita_evidencia');
    }

    /**
     * @param array<string, mixed> $arquivo
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, url: string, erro: string}
     */
    public static function uploadEvidenciaNc(int $ncId, array $arquivo, array $identidade): array
    {
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'url' => '', 'erro' => 'Sem permissão para anexar evidência.'];
        }
        $resultado = self::salvarImagem($arquivo, 'nao_conformidades', 'nc_evidencia');
        if ($resultado['ok']) {
            Identidades::pdo()->prepare('UPDATE nao_conformidades SET evidencia_correcao_url = :url, updated_at = NOW() WHERE id = :id')
                ->execute(['url' => $resultado['url'], 'id' => $ncId]);
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $arquivo
     * @return array{ok: bool, url: string, erro: string}
     */
    private static function salvarImagem(array $arquivo, string $pasta, string $prefixo): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'url' => '', 'erro' => 'Selecione uma imagem.'];
        }
        if ((int) ($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'url' => '', 'erro' => 'A imagem deve ter no máximo 5 MB.'];
        }
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : (string) ($arquivo['type'] ?? '');
        $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensoes[$mime]) || !is_file($tmp)) {
            return ['ok' => false, 'url' => '', 'erro' => 'Envie JPG, PNG ou WebP.'];
        }

        $dir = dirname(__DIR__, 2) . '/uploads/' . $pasta;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'url' => '', 'erro' => 'Não foi possível criar a pasta de uploads.'];
        }
        $nome = $prefixo . '_' . bin2hex(random_bytes(4)) . '_' . time() . '.' . $extensoes[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $nome)) {
            return ['ok' => false, 'url' => '', 'erro' => 'Não foi possível salvar a imagem.'];
        }

        return ['ok' => true, 'url' => Urls::base() . '/uploads/' . $pasta . '/' . $nome, 'erro' => ''];
    }

    private static function salvarAssinatura(string $dataUrl, string $tipo, int $visitaId): string
    {
        if ($dataUrl === '' || !str_starts_with($dataUrl, 'data:image/')) {
            return '';
        }
        $partes = explode(',', $dataUrl, 2);
        if (count($partes) !== 2) {
            return '';
        }
        $binario = base64_decode($partes[1], true);
        if ($binario === false || $binario === '') {
            return '';
        }
        $dir = dirname(__DIR__, 2) . '/uploads/visitas_tecnicas';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return '';
        }
        $nome = 'assinatura_' . $tipo . '_' . $visitaId . '_' . time() . '.png';
        if (file_put_contents($dir . '/' . $nome, $binario) === false) {
            return '';
        }

        return Urls::base() . '/uploads/visitas_tecnicas/' . $nome;
    }

    /**
     * @param array<string, mixed> $padrao
     * @return array<string, mixed>
     */
    private static function jsonDecode(string $json, array $padrao): array
    {
        $decodificado = json_decode($json, true);

        return is_array($decodificado) ? $decodificado : $padrao;
    }

    /**
     * Atualiza status/ações permitidas de um módulo.
     *
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function atualizar(string $modulo, int $id, string $acao, array $identidade): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'erro' => 'Registro inválido.'];
        }
        if (!self::podeEscrever($identidade)) {
            return ['ok' => false, 'erro' => 'Seu perfil não pode alterar este registro.'];
        }

        $pdo = Identidades::pdo();
        try {
            switch ($modulo) {
                case 'planos':
                    $status = match ($acao) {
                        'aprovar' => 'Aprovado',
                        'reprovar' => 'Reprovado',
                        'revisao' => 'Revisão Necessária',
                        default => null,
                    };
                    if ($status === null) {
                        return ['ok' => false, 'erro' => 'Ação inválida para o plano.'];
                    }
                    $sql = $status === 'Aprovado'
                        ? 'UPDATE plano_manejo SET status = :status, aprovado_por = :por, data_aprovacao = CURDATE() WHERE id = :id AND excluido = 0'
                        : 'UPDATE plano_manejo SET status = :status, aprovado_por = :por' . ($status === 'Reprovado' ? ', data_reprovacao = CURDATE()' : '') . ' WHERE id = :id AND excluido = 0';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['status' => $status, 'por' => (int) ($identidade['id'] ?? 0), 'id' => $id]);
                    $tabela = 'plano_manejo';
                    break;

                case 'visitas':
                    $status = match ($acao) {
                        'realizada' => 'Realizada',
                        'cancelar' => 'Cancelada',
                        'reagendar' => 'Reagendada',
                        default => null,
                    };
                    if ($status === null) {
                        return ['ok' => false, 'erro' => 'Ação inválida para a visita.'];
                    }
                    $stmt = $pdo->prepare('UPDATE visitas_tecnicas SET status = :status WHERE id = :id');
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    $tabela = 'visitas_tecnicas';
                    break;

                case 'ncs':
                    $status = match ($acao) {
                        'abrir' => 'Aberta',
                        'corrigir' => 'Em Correção',
                        'resolver' => 'Resolvida',
                        'cancelar' => 'Cancelada',
                        default => null,
                    };
                    if ($status === null) {
                        return ['ok' => false, 'erro' => 'Ação inválida para a NC.'];
                    }
                    $stmt = $pdo->prepare(
                        'UPDATE nao_conformidades SET status = :status, data_resolucao = ' . ($status === 'Resolvida' ? 'CURDATE()' : 'NULL') . ' WHERE id = :id'
                    );
                    $stmt->execute(['status' => $status, 'id' => $id]);
                    $tabela = 'nao_conformidades';
                    break;

                case 'validacao':
                    if (!in_array($acao, ['aprovar', 'reprovar', 'pendente'], true)) {
                        return ['ok' => false, 'erro' => 'Ação inválida para a validação.'];
                    }
                    $valor = $acao === 'aprovar' ? 1 : ($acao === 'reprovar' ? 0 : null);
                    $stmt = $pdo->prepare('UPDATE insumos SET aprovado_organico = :valor WHERE id = :id AND excluido = 0');
                    $stmt->execute(['valor' => $valor, 'id' => $id]);
                    $tabela = 'insumos';
                    break;

                default:
                    return ['ok' => false, 'erro' => 'Módulo sem ações.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG SocialModulos::atualizar - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível atualizar o registro.'];
        }

        Auditoria::registrar('FISCALIZACAO_ATUALIZADA', (int) ($identidade['id'] ?? 0), $tabela, $id, 'modulo=' . $modulo . ' acao=' . $acao, 'social');

        return ['ok' => true, 'erro' => ''];
    }
}
