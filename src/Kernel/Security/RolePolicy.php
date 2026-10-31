<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\Contracts\ContextoUsuario;

/**
 * Matriz de autorização por papel. Ações desconhecidas são negadas por padrão.
 */
final class RolePolicy implements Policy
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'admin' => ['*'],
        'gestor' => ['associados.*', 'territorios.*', 'relatorios.*', 'financeiro.*'],
        'tecnico' => ['associados.read', 'associados.write', 'territorios.read', 'caderno.*'],
        'diretoria' => ['associados.read', 'territorios.read', 'relatorios.*', 'financeiro.read'],
        'agricultor' => ['meu-perfil.*', 'caderno.*'],
        'agroindustria' => ['agroindustria.*', 'meu-perfil.read'],
    ];

    public function allows(ContextoUsuario $context, string $resource, string $action): bool
    {
        $roles = $context->roles();

        if (in_array('admin', $roles, true)) {
            return true;
        }

        foreach ($roles as $role) {
            $permissions = self::PERMISSIONS[$role] ?? [];
            foreach ($permissions as $permission) {
                if (
                    $permission === '*'
                    || $permission === $resource
                    || $permission === $resource . '.*'
                    || $permission === $resource . '.' . $action
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
