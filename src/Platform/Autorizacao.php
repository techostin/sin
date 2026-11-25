<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Regras de autorização do framework.
 *
 * Ordem: o perfil `admin` (ou `permissoes` contendo `*`) tem acesso total;
 * caso contrário, decide-se por PERMISSÃO (o que o perfil pode fazer), com
 * fallback por PAPEL (quais perfis o sistema aceita).
 */
final class Autorizacao
{
    /** Papéis com escopo amplo (não são "somente membro"). */
    private const PAPEIS_ELEVADOS = [
        'admin', 'diretor', 'gestor_associacao', 'tesoureiro', 'secretario',
        'gestor_fiscalizacao', 'fiscal_campo', 'certificador', 'diretoria',
    ];

    /** Permissões máximas de quem é somente membro associado/produtor. */
    private const PERMISSOES_MEMBRO = [
        'caderno.read', 'caderno.write', 'portal.read',
        'agroindustria.read', 'agroindustria.write',
        'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
    ];

    /** @param array<string, mixed> $identidade */
    public static function ehAdmin(array $identidade): bool
    {
        $permissoes = $identidade['permissoes'] ?? [];

        return (bool) ($identidade['admin'] ?? false)
            || in_array('*', is_array($permissoes) ? $permissoes : [], true)
            || in_array('admin', $identidade['papeis'] ?? [], true);
    }

    /**
     * Conta somente membro: nenhum papel elevado. Mesmo que o banco tenha
     * permissões antigas/globalizadas, o acesso fica limitado ao próprio dado.
     *
     * @param array<string, mixed> $identidade
     */
    private static function somenteMembro(array $identidade): bool
    {
        $papeis = $identidade['papeis'] ?? [];
        if (!is_array($papeis) || $papeis === []) {
            return false;
        }

        return array_intersect(array_map('strval', $papeis), self::PAPEIS_ELEVADOS) === [];
    }

    /**
     * O usuário tem a permissão? (`associados.write`, `fiscalizacao.review`, ...)
     *
     * @param array<string, mixed> $identidade
     */
    public static function pode(array $identidade, string $permissao): bool
    {
        if (self::ehAdmin($identidade)) {
            return true;
        }

        if (($identidade['ficha_pendente'] ?? false)
            && in_array($permissao, ['associados.self', 'portal.read'], true)) {
            return true;
        }

        // Trava do membro: nunca herda quadro completo, financeiro global,
        // relatórios gerais nem fiscalização por permissões residuais do banco.
        if (self::somenteMembro($identidade) && !in_array($permissao, self::PERMISSOES_MEMBRO, true)) {
            return false;
        }

        $permissoes = $identidade['permissoes'] ?? [];

        return in_array($permissao, is_array($permissoes) ? $permissoes : [], true);
    }

    /**
     * O perfil pode entrar no sistema informado?
     *
     * @param array<string, mixed> $identidade
     */
    public static function podeAcessar(array $identidade, string $slug): bool
    {
        if (self::ehAdmin($identidade)) {
            return true;
        }

        $app = Aplicacoes::obter($slug);

        foreach ($app['permissoes'] as $permissao) {
            if (self::pode($identidade, (string) $permissao)) {
                return true;
            }
        }

        $papeis = $identidade['papeis'] ?? [];

        return array_intersect($papeis, $app['papeis']) !== [];
    }

    /**
     * Funções do sistema que o perfil atual pode ver.
     *
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function funcoesVisiveis(array $identidade, string $slug): array
    {
        // Sem acesso ao sistema, nenhuma função deve ser exposta (nem em
        // consultas de diagnóstico ou menus de outros módulos).
        if (!self::podeAcessar($identidade, $slug)) {
            return [];
        }

        $app = Aplicacoes::obter($slug);
        $visiveis = [];

        foreach ($app['funcoes'] as $funcao) {
            // Recorte por área de produção: vegetal, animal e agroindústria
            // aparecem apenas para quem atua naquela área.
            $areas = (array) ($funcao['areas'] ?? []);
            if ($areas === [] && $slug === 'producao') {
                $areas = match ((string) ($funcao['grupo'] ?? '')) {
                    'Produção Vegetal' => ['vegetal'],
                    'Produção Animal' => ['animal'],
                    'Agroindústria' => ['agroindustria'],
                    default => [],
                };
            }
            if ($slug === 'producao' && $areas !== []) {
                $permitida = false;
                foreach ($areas as $area) {
                    if (ProducaoAreas::pode($identidade, (string) $area)) {
                        $permitida = true;
                        break;
                    }
                }
                if (!$permitida) {
                    continue;
                }
            }

            if (self::pode($identidade, (string) $funcao['permissao'])) {
                $visiveis[] = $funcao;
            }
        }

        return $visiveis;
    }

    /**
     * Sistemas que o perfil pode acessar.
     *
     * @param array<string, mixed> $identidade
     * @return list<string>
     */
    public static function sistemasPermitidos(array $identidade): array
    {
        $slugs = [];
        foreach (array_keys(Aplicacoes::todos()) as $slug) {
            if (self::podeAcessar($identidade, $slug)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }
}
