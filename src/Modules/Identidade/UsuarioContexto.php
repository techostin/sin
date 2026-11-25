<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Contracts\ContextoUsuario;

final class UsuarioContexto implements ContextoUsuario
{
    /**
     * @param list<array{code: string, scope_type: string, scope_id: int}> $roles
     * @param list<string> $permissions
     * @param list<string> $areas
     * @param list<int> $visibleAssociationIds
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly ?int $associadoId,
        private readonly ?int $certificadoraId,
        private readonly array $roles,
        private readonly array $permissions,
        private readonly array $areas = [],
        private readonly array $visibleAssociationIds = [],
    ) {
    }

    public function userId(): string
    {
        return (string) $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function origin(): string
    {
        return 'session';
    }

    public function associadoId(): ?int
    {
        return $this->associadoId;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_values(array_map(static fn (array $role): string => $role['code'], $this->roles));
    }

    public function certificadoraId(): ?int
    {
        return $this->certificadoraId;
    }

    /** @return list<int> */
    public function visibleAssociations(): array
    {
        $ids = $this->visibleAssociationIds;
        foreach ($this->roles as $role) {
            if ($role['scope_type'] === 'associacao' && $role['scope_id'] > 0) {
                $ids[] = $role['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    public function productionAreas(): array
    {
        return $this->areas;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->permissions;
    }

    public function can(string $resource, string $action): bool
    {
        if (in_array('*', $this->permissions, true)) {
            return true;
        }

        $needed = [$resource, $resource . '.' . $action];
        foreach ($this->permissions as $permission) {
            if (in_array($permission, $needed, true) || $permission === $resource . '.*') {
                return true;
            }
        }

        return false;
    }
}
