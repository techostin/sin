<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Contracts\ContextoUsuario;

final class CapabilityService
{
    private readonly NavigationCatalog $catalog;

    public function __construct(?NavigationCatalog $catalog = null)
    {
        $this->catalog = $catalog ?? new NavigationCatalog();
    }

    /**
     * @param list<array{code: string, scope_type: string, scope_id: int}> $roles
     * @param list<string> $permissions
     * @return list<string>
     */
    public function effectivePermissions(array $roles, array $permissions): array
    {
        if ($permissions !== []) {
            $unique = array_values(array_unique($permissions));
            sort($unique);

            return $unique;
        }

        $effective = [];
        foreach ($roles as $role) {
            foreach ($this->fallbackPermissions($role['code']) as $permission) {
                $effective[] = $permission;
            }
        }

        $effective = array_values(array_unique($effective));
        sort($effective);

        return $effective;
    }

    /**
     * @return array{
     *   capabilities: list<string>,
     *   navigation: list<array{key: string, label: string, module: string, permission: string, surface: list<string>}>,
     *   surfaces: list<string>,
     *   personas: list<string>
     * }
     */
    public function forContext(ContextoUsuario $context): array
    {
        $capabilities = $context->capabilities();
        $roles = $context->roles();
        $surfaces = [];
        if (
            in_array('admin', $roles, true)
            || in_array('gestor_associacao', $roles, true)
            || in_array('gestor_fiscalizacao', $roles, true)
            || in_array('certificador', $roles, true)
            || in_array('diretoria', $roles, true)
        ) {
            $surfaces[] = 'web';
        }
        if (in_array('fiscal_campo', $roles, true) || in_array('produtor', $roles, true)) {
            $surfaces[] = 'pwa';
        }
        if ($surfaces === []) {
            $surfaces[] = 'web';
        }

        return [
            'capabilities' => $capabilities,
            'navigation' => $this->catalog->forCapabilities($capabilities),
            'surfaces' => array_values(array_unique($surfaces)),
            'personas' => $roles,
        ];
    }

    /** @return list<string> */
    private function fallbackPermissions(string $role): array
    {
        return match ($role) {
            'admin' => ['*'],
            'gestor_associacao' => [
                'associados.read', 'associados.write', 'associados.approve',
                'financeiro.read', 'financeiro.write', 'governanca.read', 'governanca.write',
                'relatorios.read', 'portal.read',
            ],
            'gestor_fiscalizacao' => [
                'associados.read', 'fiscalizacao.read', 'fiscalizacao.write',
                'fiscalizacao.review', 'fiscalizacao.certificate', 'caderno.read',
                'relatorios.read', 'portal.read',
            ],
            'fiscal_campo' => [
                'associados.read', 'fiscalizacao.read', 'fiscalizacao.write',
                'caderno.read', 'portal.read',
            ],
            'certificador' => [
                'associados.read', 'fiscalizacao.read', 'fiscalizacao.review',
                'fiscalizacao.certificate', 'relatorios.read', 'portal.read',
            ],
            'produtor' => [
                'associados.read', 'fiscalizacao.read', 'caderno.read',
                'caderno.write', 'relatorios.read', 'portal.read',
            ],
            'diretoria' => [
                'associados.read', 'fiscalizacao.read', 'financeiro.read',
                'governanca.read', 'relatorios.read', 'portal.read',
            ],
            'agroindustria' => [
                'agroindustria.read', 'agroindustria.write', 'relatorios.read', 'portal.read',
            ],
            default => [],
        };
    }
}
