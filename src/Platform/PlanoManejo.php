<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Plano de Manejo do agricultor: obrigatório para TODAS as áreas de produção
 * (vegetal, animal e agroindústria), com cultivos, práticas, croqui e envio
 * para análise técnica.
 */
final class PlanoManejo
{
    public const STATUS_EDITAVEIS = ['Rascunho', 'Revisão Necessária'];

    /** @param array<string, mixed> $identidade */
    public static function associadoId(array $identidade): int
    {
        return ($identidade['origem'] ?? '') === 'associados'
            ? (int) ($identidade['id'] ?? 0)
            : (int) ($identidade['associado_id'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public static function listar(int $associadoId): array
    {
        if ($associadoId <= 0) {
            return [];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "SELECT p.*, pr.propriedade AS propriedade_nome
                   FROM plano_manejo p
                   LEFT JOIN propriedades pr ON pr.id = p.propriedade_id
                  WHERE p.associado_id = :assoc AND p.excluido = 0
                  ORDER BY p.ano_safra DESC, p.versao DESC, p.id DESC"
            );
            $stmt->execute(['assoc' => $associadoId]);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::listar - ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id, int $associadoId): ?array
    {
        if ($id <= 0 || $associadoId <= 0) {
            return null;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT p.*, pr.propriedade AS propriedade_nome
                   FROM plano_manejo p
                   LEFT JOIN propriedades pr ON pr.id = p.propriedade_id
                  WHERE p.id = :id AND p.associado_id = :assoc AND p.excluido = 0 LIMIT 1'
            );
            $stmt->execute(['id' => $id, 'assoc' => $associadoId]);
            $plano = $stmt->fetch();
            if (!is_array($plano)) {
                return null;
            }

            $stmt = Identidades::pdo()->prepare('SELECT * FROM plano_manejo_cultivos WHERE plano_manejo_id = :id ORDER BY id ASC');
            $stmt->execute(['id' => $id]);
            $plano['cultivos'] = $stmt->fetchAll();

            return $plano;
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::porId - ' . $e->getMessage());

            return null;
        }
    }

    /** @return array{ok: bool, erro: string} */
    public static function excluir(int $id, int $associadoId): array
    {
        $plano = self::porId($id, $associadoId);
        if ($plano === null) {
            return ['ok' => false, 'erro' => 'Plano não encontrado.'];
        }
        if (!self::podeEditar($plano)) {
            return ['ok' => false, 'erro' => 'Só é possível excluir planos em rascunho ou em revisão.'];
        }

        try {
            Identidades::pdo()->prepare('UPDATE plano_manejo SET excluido = 1, updated_at = NOW() WHERE id = :id')
                ->execute(['id' => $id]);
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::excluir - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível excluir o plano.'];
        }

        Auditoria::registrar('PLANO_EXCLUIDO', null, 'plano_manejo', $id, 'app: producao', 'producao');

        return ['ok' => true, 'erro' => ''];
    }

    /** @return array<string, mixed>|null */
    public static function ultimo(int $associadoId): ?array
    {
        if ($associadoId <= 0) {
            return null;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT * FROM plano_manejo WHERE associado_id = :assoc AND excluido = 0 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['assoc' => $associadoId]);
            $plano = $stmt->fetch();
            if (!is_array($plano)) {
                return null;
            }

            $stmt = Identidades::pdo()->prepare(
                'SELECT * FROM plano_manejo_cultivos WHERE plano_manejo_id = :id ORDER BY id ASC'
            );
            $stmt->execute(['id' => (int) $plano['id']]);
            $plano['cultivos'] = $stmt->fetchAll();

            return $plano;
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::ultimo - ' . $e->getMessage());

            return null;
        }
    }

    /** @return array{ok: bool, status: string, plano: array<string, mixed>|null} */
    public static function situacao(int $associadoId): array
    {
        $plano = self::ultimo($associadoId);
        $status = $plano === null ? 'sem_plano' : (string) ($plano['status'] ?? 'Rascunho');
        $ok = in_array($status, ['Em Análise', 'Aprovado'], true);

        return ['ok' => $ok, 'status' => $status, 'plano' => $plano];
    }

    /** @return list<array<string, mixed>> */
    public static function propriedades(int $associadoId): array
    {
        if ($associadoId <= 0) {
            return [];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT MIN(p.id) AS id, p.propriedade, MAX(p.area_total) AS area_total
                   FROM propriedades p
                  WHERE p.associado_id = :assoc
                     OR p.id IN (SELECT propriedade_id FROM propriedade_membros WHERE associado_id = :membro AND excluido = 0)
                  GROUP BY p.propriedade
                  ORDER BY p.propriedade ASC'
            );
            $stmt->execute(['assoc' => $associadoId, 'membro' => $associadoId]);

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::propriedades - ' . $e->getMessage());

            return [];
        }
    }

    /** @param array<string, mixed> $plano */
    public static function podeEditar(array $plano): bool
    {
        return in_array((string) ($plano['status'] ?? ''), self::STATUS_EDITAVEIS, true);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string, plano_id: int}
     */
    public static function salvar(int $associadoId, array $post, bool $submeter): array
    {
        if ($associadoId <= 0) {
            return ['ok' => false, 'erro' => 'Vincule seu cadastro de associado antes de criar o plano.', 'plano_id' => 0];
        }

        $propriedadeId = (int) ($post['propriedade_id'] ?? 0);
        if ($propriedadeId <= 0 || !self::propriedadePertence($propriedadeId, $associadoId)) {
            return ['ok' => false, 'erro' => 'Selecione uma propriedade sua.', 'plano_id' => 0];
        }

        $safra = trim((string) ($post['ano_safra'] ?? ''));
        if ($safra === '') {
            $safra = date('Y') . '/' . (date('Y') + 1);
        }
        $titulo = trim((string) ($post['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = 'Plano de Manejo ' . $safra;
        }

        $campos = [
            'area_total_ha' => self::decimal($post['area_total_ha'] ?? null),
            'area_organica_ha' => self::decimal($post['area_organica_ha'] ?? null),
            'area_conversao_ha' => self::decimal($post['area_conversao_ha'] ?? null),
            'area_convencional_ha' => self::decimal($post['area_convencional_ha'] ?? null),
            'descricao_solo' => self::texto($post['descricao_solo'] ?? ''),
            'topografia' => self::topografia((string) ($post['topografia'] ?? '')),
            'fonte_agua' => self::texto($post['fonte_agua'] ?? ''),
            'clima' => self::texto($post['clima'] ?? ''),
            'praticas_conservacao' => self::texto($post['praticas_conservacao'] ?? ''),
            'manejo_pragas' => self::texto($post['manejo_pragas'] ?? ''),
            'manejo_doencas' => self::texto($post['manejo_doencas'] ?? ''),
            'adubacao_verde' => self::texto($post['adubacao_verde'] ?? ''),
            'rotacao_culturas' => self::texto($post['rotacao_culturas'] ?? ''),
            'compostagem' => self::texto($post['compostagem'] ?? ''),
            'biofertilizantes' => self::texto($post['biofertilizantes'] ?? ''),
            'agroindustria_dados' => self::agroindustriaJson($post),
            'observacoes' => self::texto($post['observacoes'] ?? ''),
        ];

        $cultivos = [];
        $lista = $post['cultivos'] ?? [];
        if (is_array($lista)) {
            foreach ($lista as $cultivo) {
                if (!is_array($cultivo)) {
                    continue;
                }
                $cultura = trim((string) ($cultivo['cultura'] ?? ''));
                if ($cultura === '') {
                    continue;
                }
                $cultivos[] = [
                    'cultura' => mb_substr($cultura, 0, 100),
                    'variedade' => mb_substr(trim((string) ($cultivo['variedade'] ?? '')), 0, 100),
                    'area_cultivada_ha' => self::decimal($cultivo['area_cultivada_ha'] ?? null),
                    'produção_estimada_kg' => self::decimal($cultivo['producao_estimada_kg'] ?? null),
                    'tipo_semente' => in_array((string) ($cultivo['tipo_semente'] ?? ''), ['Orgânica Certificada', 'Convencional Não Tratada', 'Crioula', 'Própria'], true)
                        ? (string) $cultivo['tipo_semente']
                        : 'Orgânica Certificada',
                    'observacoes' => self::texto($cultivo['observacoes'] ?? ''),
                ];
            }
        }

        $pdo = Identidades::pdo();
        try {
            $pdo->beginTransaction();
            $id = (int) ($post['id'] ?? 0);
            $existente = null;
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT * FROM plano_manejo WHERE id = :id AND associado_id = :assoc AND excluido = 0 LIMIT 1');
                $stmt->execute(['id' => $id, 'assoc' => $associadoId]);
                $existente = $stmt->fetch() ?: null;
                if (!is_array($existente)) {
                    $pdo->rollBack();

                    return ['ok' => false, 'erro' => 'Plano não encontrado.', 'plano_id' => 0];
                }
                if (!self::podeEditar($existente)) {
                    $pdo->rollBack();

                    return ['ok' => false, 'erro' => 'Este plano não pode mais ser editado no status atual.', 'plano_id' => $id];
                }
            }

            $status = $submeter ? 'Em Análise' : 'Rascunho';
            if ($id > 0) {
                $sets = ['propriedade_id = :propriedade', 'ano_safra = :safra', 'titulo = :titulo', 'status = :status'];
                foreach (array_keys($campos) as $campo) {
                    $sets[] = '`' . $campo . '` = :' . $campo;
                }
                $params = $campos + [
                    'propriedade' => $propriedadeId,
                    'safra' => $safra,
                    'titulo' => $titulo,
                    'status' => $status,
                    'id' => $id,
                ];
                $stmt = $pdo->prepare('UPDATE plano_manejo SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id');
                $stmt->execute($params);
                $planoId = $id;
            } else {
                $colunas = array_keys($campos);
                $marcadores = array_map(static fn(string $c): string => ':' . $c, $colunas);
                $stmt = $pdo->prepare(
                    'INSERT INTO plano_manejo
                        (propriedade_id, associado_id, ano_safra, titulo, versao, status,
                         `' . implode('`, `', $colunas) . '`, created_at, updated_at)
                     VALUES (:propriedade, :associado, :safra, :titulo, 1, :status,
                         ' . implode(', ', $marcadores) . ', NOW(), NOW())'
                );
                $stmt->execute($campos + [
                    'propriedade' => $propriedadeId,
                    'associado' => $associadoId,
                    'safra' => $safra,
                    'titulo' => $titulo,
                    'status' => $status,
                ]);
                $planoId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('DELETE FROM plano_manejo_cultivos WHERE plano_manejo_id = :id');
            $stmt->execute(['id' => $planoId]);
            if ($cultivos !== []) {
                $stmt = $pdo->prepare(
                    'INSERT INTO plano_manejo_cultivos
                        (plano_manejo_id, cultura, variedade, area_cultivada_ha, producao_estimada_kg, tipo_semente, observacoes)
                     VALUES (:plano, :cultura, :variedade, :area, :producao, :semente, :observacoes)'
                );
                foreach ($cultivos as $cultivo) {
                    $stmt->execute([
                        'plano' => $planoId,
                        'cultura' => $cultivo['cultura'],
                        'variedade' => $cultivo['variedade'] !== '' ? $cultivo['variedade'] : null,
                        'area' => $cultivo['area_cultivada_ha'],
                        'producao' => $cultivo['produção_estimada_kg'],
                        'semente' => $cultivo['tipo_semente'],
                        'observacoes' => $cultivo['observacoes'] !== '' ? $cultivo['observacoes'] : null,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SINPORG PlanoManejo::salvar - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar o plano de manejo.', 'plano_id' => 0];
        }

        Auditoria::registrar(
            $submeter ? 'PLANO_ENVIADO' : 'PLANO_RASCUNHO',
            null,
            'plano_manejo',
            $planoId,
            'app: producao',
            'producao'
        );

        return ['ok' => true, 'erro' => '', 'plano_id' => $planoId];
    }

    /**
     * @param array<string, mixed> $arquivo
     * @return array{ok: bool, erro: string}
     */
    public static function uploadCroqui(int $associadoId, int $planoId, array $arquivo): array
    {
        if ($associadoId <= 0 || $planoId <= 0) {
            return ['ok' => false, 'erro' => 'Plano inválido.'];
        }
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'erro' => 'Selecione uma imagem ou PDF do croqui.'];
        }
        if ((int) ($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'erro' => 'O arquivo deve ter no máximo 5 MB.'];
        }

        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : (string) ($arquivo['type'] ?? '');
        $extensoes = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'application/pdf' => 'pdf',
        ];
        if (!isset($extensoes[$mime]) || !is_file($tmp)) {
            return ['ok' => false, 'erro' => 'Envie JPG, PNG, GIF, WebP ou PDF.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT id FROM plano_manejo WHERE id = :id AND associado_id = :assoc AND excluido = 0 LIMIT 1'
            );
            $stmt->execute(['id' => $planoId, 'assoc' => $associadoId]);
            if ($stmt->fetch() === false) {
                return ['ok' => false, 'erro' => 'Plano não encontrado.'];
            }

            $dir = dirname(__DIR__, 2) . '/uploads/planos_manejo';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['ok' => false, 'erro' => 'Não foi possível criar a pasta de uploads.'];
            }

            $nome = 'croqui_' . $planoId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensoes[$mime];
            if (!move_uploaded_file($tmp, $dir . '/' . $nome)) {
                return ['ok' => false, 'erro' => 'Não foi possível salvar o arquivo enviado.'];
            }

            $url = Urls::base() . '/uploads/planos_manejo/' . $nome;
            Identidades::pdo()->prepare('UPDATE plano_manejo SET croqui_propriedade_url = :url, updated_at = NOW() WHERE id = :id')
                ->execute(['url' => $url, 'id' => $planoId]);
        } catch (\Throwable $e) {
            error_log('SINPORG PlanoManejo::uploadCroqui - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível enviar o croqui.'];
        }

        Auditoria::registrar('PLANO_CROQUI', null, 'plano_manejo', $planoId, 'app: producao', 'producao');

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * @param array<string, mixed> $plano
     * @return list<array{0: string, 1: string}>
     */
    public static function resumo(array $plano): array
    {
        return [
            ['Status', (string) ($plano['status'] ?? 'Rascunho')],
            ['Safra', (string) ($plano['ano_safra'] ?? '—')],
            ['Título', (string) ($plano['titulo'] ?? '—')],
            ['Versão', (string) ($plano['versao'] ?? 1)],
            ['Atualizado em', (string) ($plano['updated_at'] ?? '')],
        ];
    }

    private static function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }

    /** @param array<string, mixed> $post */
    private static function agroindustriaJson(array $post): ?string
    {
        $agro = $post['agroindustria'] ?? null;
        if (!is_array($agro)) {
            return null;
        }
        $campos = [
            'nome_agroindustria', 'produtos_processados', 'boas_praticas_fabricacao',
            'higienizacao', 'controle_pragas_unidade', 'rastreabilidade_lotes',
            'insumos_processamento', 'agua_processamento', 'gestao_residuos',
        ];
        $dados = [];
        foreach ($campos as $campo) {
            $valor = trim((string) ($agro[$campo] ?? ''));
            if ($valor !== '') {
                $dados[$campo] = $valor;
            }
        }

        return $dados === [] ? null : json_encode($dados, JSON_UNESCAPED_UNICODE);
    }

    private static function propriedadePertence(int $propriedadeId, int $associadoId): bool
    {
        try {
            $stmt = Identidades::pdo()->prepare(
                'SELECT 1 FROM propriedades p
                  WHERE p.id = :id
                    AND (p.associado_id = :assoc
                         OR p.id IN (SELECT propriedade_id FROM propriedade_membros WHERE associado_id = :membro AND excluido = 0))
                  LIMIT 1'
            );
            $stmt->execute(['id' => $propriedadeId, 'assoc' => $associadoId, 'membro' => $associadoId]);

            return $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function decimal(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        $normalizado = str_contains($texto, ',') && !str_contains($texto, '.')
            ? str_replace(',', '.', $texto)
            : $texto;

        return is_numeric($normalizado) ? $normalizado : null;
    }

    private static function topografia(string $valor): string
    {
        return in_array($valor, ['Plana', 'Ondulada', 'Montanhosa', 'Irregular'], true) ? $valor : 'Plana';
    }
}
