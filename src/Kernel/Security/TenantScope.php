<?php

declare(strict_types=1);

namespace App\Kernel\Security;

/**
 * Escopo de visibilidade do usuário. Certificadora nula = visão global autorizada.
 */
final class TenantScope
{
    /** @param list<int> $associationIds */
    public function __construct(
        private readonly ?int $certificadoraId,
        private readonly array $associationIds = [],
    ) {
    }

    public function allowsCertificadora(?int $certificadoraId): bool
    {
        if ($this->certificadoraId === null) {
            return true;
        }

        return $certificadoraId !== null && $certificadoraId === $this->certificadoraId;
    }

    public function allowsAssociation(?int $associationId): bool
    {
        if ($this->certificadoraId === null) {
            return true;
        }

        return $associationId !== null && in_array($associationId, $this->associationIds, true);
    }

    /** @return list<int> */
    public function associationIds(): array
    {
        return $this->associationIds;
    }
}
