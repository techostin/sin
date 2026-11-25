<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Áreas de produção do agricultor: vegetal, animal e agroindústria.
 *
 * Reproduz a regra do legado (`User::areaProducao()`/`temAgroindustria()`):
 * admin/gestão veem todas; agricultor vê as áreas do cadastro + agroindústria
 * quando possui o módulo; sem área cadastrada o legado libera todas.
 */
final class ProducaoAreas
{
    public const TODAS = ['animal', 'vegetal', 'agroindustria'];

    /** @var array<string, list<string>> */
    private static array $cache = [];

    /** @param array<string, mixed> $identidade */
    public static function gerencia(array $identidade): bool
    {
        if (Autorizacao::ehAdmin($identidade)) {
            return true;
        }

        $papeis = array_map('strval', (array) ($identidade['papeis'] ?? []));

        return array_intersect($papeis, ['gestor_associacao', 'diretoria', 'diretor']) !== [];
    }

    /**
     * @param array<string, mixed> $identidade
     * @return list<string>
     */
    public static function doUsuario(array $identidade): array
    {
        if (self::gerencia($identidade)) {
            return self::TODAS;
        }

        $chaveCache = (string) ($identidade['origem'] ?? '') . ':' . (int) ($identidade['id'] ?? 0)
            . ':' . (string) ($identidade['area_producao'] ?? '')
            . ':' . (int) ($identidade['tem_agroindustria'] ?? 0)
            . ':' . (string) ($identidade['perfil_solicitado'] ?? '')
            . ':' . implode(',', array_map('strval', (array) ($identidade['papeis'] ?? [])));
        if (isset(self::$cache[$chaveCache])) {
            return self::$cache[$chaveCache];
        }

        $areas = array_map('strtolower', array_map('trim', explode(',', (string) ($identidade['area_producao'] ?? ''))));
        $areas = array_values(array_intersect($areas, self::TODAS));

        // Sem área gravada, tenta derivar do perfil escolhido no primeiro acesso.
        if ($areas === []) {
            $areas = self::areasDoPerfil((string) ($identidade['perfil_solicitado'] ?? ''));
        }

        $tipo = strtolower((string) ($identidade['tipo_usuario'] ?? ''));
        $papeis = array_map('strval', (array) ($identidade['papeis'] ?? []));
        $temAgro = !empty($identidade['tem_agroindustria'])
            || in_array($tipo, ['produtor_agroindustria', 'agricultor_agroindustria', 'agroindustria'], true)
            || in_array('agroindustria', $papeis, true);

        // Legado também considera agroindústria quando já existe unidade cadastrada.
        $associadoId = ($identidade['origem'] ?? '') === 'associados'
            ? (int) ($identidade['id'] ?? 0)
            : (int) ($identidade['associado_id'] ?? 0);
        if (!$temAgro && $associadoId > 0) {
            try {
                $stmt = Identidades::pdo()->prepare(
                    'SELECT 1 FROM agroindustrias WHERE associado_id = :id AND excluido = 0 LIMIT 1'
                );
                $stmt->execute(['id' => $associadoId]);
                $temAgro = $stmt->fetchColumn() !== false;
            } catch (\Throwable $e) {
                error_log('SINPORG ProducaoAreas::doUsuario - ' . $e->getMessage());
            }
        }

        if ($temAgro && !in_array('agroindustria', $areas, true)) {
            $areas[] = 'agroindustria';
        }

        if ($areas === []) {
            // Sem qualquer informação de área, não libera tudo: mantém o mínimo
            // (vegetal) para não expor módulos de outras áreas indevidamente.
            $areas = ['vegetal'];
        }

        $areas = array_values(array_unique($areas));
        self::$cache[$chaveCache] = $areas;

        return $areas;
    }

    /**
     * @return list<string>
     */
    private static function areasDoPerfil(string $perfil): array
    {
        return match (strtolower($perfil)) {
            'vegetal' => ['vegetal'],
            'animal' => ['animal'],
            'agroindustria' => ['agroindustria'],
            'vegetal_agro' => ['vegetal', 'agroindustria'],
            'animal_agro' => ['animal', 'agroindustria'],
            'mista' => ['animal', 'vegetal', 'agroindustria'],
            default => [],
        };
    }

    /** @param array<string, mixed> $identidade */
    public static function pode(array $identidade, string $area): bool
    {
        return in_array($area, self::doUsuario($identidade), true);
    }
}
