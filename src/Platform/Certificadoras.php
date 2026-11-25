<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Certificadoras / OPACs / OCSs.
 *
 * Porta `certificadoras_list.php`, `certificadoras_cadastrar.php` e
 * `certificadoras_editar.php` do sistema atual (campos nome, cidade, UF,
 * contato, site, logo e vínculo com associados).
 */
final class Certificadoras
{
    /**
     * @param array{q: string, uf: string} $filtros
     * @return list<array<string, mixed>>
     */
    public static function listar(array $filtros): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($filtros['q'] !== '') {
            $where[] = '(nome LIKE :q OR contato LIKE :q OR email LIKE :q OR sigla LIKE :q)';
            $params['q'] = '%' . $filtros['q'] . '%';
        }
        if ($filtros['uf'] !== '') {
            $where[] = '(uf = :uf OR estado = :uf)';
            $params['uf'] = $filtros['uf'];
        }

        $stmt = Identidades::pdo()->prepare(
            'SELECT id, sigla, nome, cnpj, tipo, registro_mapa, cidade, uf, estado, contato,
                    email, telefone, site, logo_filename, logo_url
               FROM certificadoras
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY nome
              LIMIT 500'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        $stmt = Identidades::pdo()->prepare('SELECT * FROM certificadoras WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** @return list<array<string, mixed>> */
    public static function membros(int $id): array
    {
        $stmt = Identidades::pdo()->prepare(
            "SELECT CONCAT(a.nome, ' ', a.sobrenome) AS nome, a.email, a.telefone,
                    CONCAT(COALESCE(a.cidade, '—'), '/', COALESCE(a.estado, '—')) AS municipio,
                    COALESCE(a.status_associado, a.status) AS status
               FROM associados a
              WHERE a.certificadora_id = :id AND a.excluido_em IS NULL
              ORDER BY a.nome"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function ufs(): array
    {
        return Identidades::pdo()
            ->query("SELECT DISTINCT uf FROM certificadoras WHERE uf IS NOT NULL AND uf <> '' ORDER BY uf")
            ->fetchAll();
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $arquivo
     * @return array{ok: bool, erro: string, id: int}
     */
    public static function salvar(array $post, ?array $arquivo = null, int $id = 0): array
    {
        $nome = trim((string) ($post['nome'] ?? ''));
        $cidade = trim((string) ($post['cidade'] ?? ''));
        $uf = strtoupper(trim((string) ($post['uf'] ?? '')));
        $contato = trim((string) ($post['contato'] ?? ''));
        $site = trim((string) ($post['site'] ?? ''));
        $logoUrl = trim((string) ($post['logo_url'] ?? ''));

        if ($nome === '') {
            return ['ok' => false, 'erro' => 'O nome é obrigatório.', 'id' => $id];
        }
        if ($uf !== '' && strlen($uf) !== 2) {
            return ['ok' => false, 'erro' => 'A UF deve ter 2 letras.', 'id' => $id];
        }
        if ($site !== '' && filter_var($site, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'erro' => 'Informe uma URL válida para o site.', 'id' => $id];
        }
        if ($logoUrl !== '' && filter_var($logoUrl, FILTER_VALIDATE_URL) === false && !str_starts_with($logoUrl, '/')) {
            return ['ok' => false, 'erro' => 'Informe uma URL válida para a logo.', 'id' => $id];
        }

        $dados = [
            'sigla' => trim((string) ($post['sigla'] ?? '')) !== '' ? trim((string) $post['sigla']) : null,
            'nome' => $nome,
            'cidade' => $cidade !== '' ? $cidade : null,
            'uf' => $uf !== '' ? $uf : null,
            'contato' => $contato !== '' ? $contato : null,
            'site' => $site !== '' ? $site : null,
            'logo_url' => $logoUrl !== '' ? $logoUrl : null,
        ];

        $atual = $id > 0 ? self::porId($id) : null;
        if ($atual === null && $id > 0) {
            return ['ok' => false, 'erro' => 'Certificadora não encontrada.', 'id' => $id];
        }

        $logo = self::processarUpload($arquivo);
        if ($logo['erro'] !== '') {
            return ['ok' => false, 'erro' => $logo['erro'], 'id' => $id];
        }
        if ($logo['filename'] !== null) {
            $dados['logo_filename'] = $logo['filename'];
        }

        try {
            $pdo = Identidades::pdo();
            if ($id > 0) {
                $sets = [];
                foreach (array_keys($dados) as $campo) {
                    $sets[] = $campo . ' = :' . $campo;
                }
                $sets[] = 'updated_at = NOW()';
                $stmt = $pdo->prepare('UPDATE certificadoras SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $stmt->execute($dados + ['id' => $id]);
            } else {
                $dados['created_at'] = date('Y-m-d H:i:s');
                $sets = array_keys($dados);
                $stmt = $pdo->prepare(
                    'INSERT INTO certificadoras (' . implode(', ', $sets) . ')
                     VALUES (:' . implode(', :', $sets) . ')'
                );
                $stmt->execute($dados);
                $id = (int) $pdo->lastInsertId();
            }
        } catch (\Throwable $e) {
            error_log('SINPORG Certificadoras::salvar - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar a certificadora.', 'id' => $id];
        }

        return ['ok' => true, 'erro' => '', 'id' => $id];
    }

    /**
     * @param array<string, mixed>|null $arquivo
     * @return array{filename: ?string, erro: string}
     */
    private static function processarUpload(?array $arquivo): array
    {
        if ($arquivo === null || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['filename' => null, 'erro' => ''];
        }
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['filename' => null, 'erro' => 'Falha no upload da logo.'];
        }
        if ((int) ($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['filename' => null, 'erro' => 'A logo deve ter no máximo 2 MB.'];
        }

        $permitidos = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmp) : '';
        if (!isset($permitidos[$mime])) {
            return ['filename' => null, 'erro' => 'Envie uma imagem PNG, JPG ou WEBP.'];
        }

        $diretorio = dirname(__DIR__, 2) . '/uploads/certificadoras';
        if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
            return ['filename' => null, 'erro' => 'Não foi possível criar a pasta de logos.'];
        }
        $nomeArquivo = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
        if (!move_uploaded_file($tmp, $diretorio . '/' . $nomeArquivo)) {
            return ['filename' => null, 'erro' => 'Não foi possível salvar a logo enviada.'];
        }

        return ['filename' => $nomeArquivo, 'erro' => ''];
    }

    /** @param array<string, mixed> $linha */
    public static function logo(?array $linha): ?string
    {
        if (!is_array($linha)) {
            return null;
        }
        $url = trim((string) ($linha['logo_url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
        $arquivo = trim((string) ($linha['logo_filename'] ?? ''));

        return $arquivo !== '' ? Urls::base() . '/uploads/certificadoras/' . rawurlencode($arquivo) : null;
    }
}
