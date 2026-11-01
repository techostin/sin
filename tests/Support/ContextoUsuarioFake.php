<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel\Contracts\ContextoUsuario;

final class ContextoUsuarioFake implements ContextoUsuario
{
    /**
     * @param list<string> $roles
     * @param list<int> $associations
     * @param list<string> $areas
     */
    public function __construct(
        private readonly array $roles = ['admin'],
        private readonly ?int $associadoId = null,
        private readonly ?int $certificadoraId = null,
        private readonly array $associations = [],
        private readonly array $areas = [],
        private readonly string $name = 'Usuario Teste',
        private readonly array $capabilities = [],
    ) {
    }

    public function userId(): string
    {
        return 'test-user';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function origin(): string
    {
        return 'test';
    }

    public function associadoId(): ?int
    {
        return $this->associadoId;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function certificadoraId(): ?int
    {
        return $this->certificadoraId;
    }

    /** @return list<int> */
    public function visibleAssociations(): array
    {
        return $this->associations;
    }

    /** @return list<string> */
    public function productionAreas(): array
    {
        return $this->areas;
    }

    /** @return list<string> */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function can(string $resource, string $action): bool
    {
        if (in_array('*', $this->capabilities, true)) {
            return true;
        }

        return in_array($resource . '.' . $action, $this->capabilities, true)
            || in_array($resource . '.*', $this->capabilities, true);
    }
}
