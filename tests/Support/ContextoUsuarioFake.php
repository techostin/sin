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
    ) {
    }

    public function userId(): string
    {
        return 'test-user';
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

    public function can(string $resource, string $action): bool
    {
        return true;
    }
}
