<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\Contracts\ContextoUsuario;

/**
 * Escopo row-level derivado do contexto. Sem restrições = visão global autorizada.
 */
final class AccessScope
{
    /** @param list<int> $associationIds */
    public function __construct(
        private readonly ?int $associadoId,
        private readonly ?int $certificadoraId,
        private readonly array $associationIds,
    ) {
    }

    public static function fromContext(ContextoUsuario $context): self
    {
        return new self($context->associadoId(), $context->certificadoraId(), $context->visibleAssociations());
    }

    public function isGlobal(): bool
    {
        return $this->associadoId === null
            && $this->certificadoraId === null
            && $this->associationIds === [];
    }

    public function associadoId(): ?int
    {
        return $this->associadoId;
    }

    /** @return list<int> */
    public function associationIds(): array
    {
        return $this->associationIds;
    }

    public function allowsAssociado(?int $associadoId, ?int $associationId = null): bool
    {
        if ($this->isGlobal()) {
            return true;
        }

        if ($this->associadoId !== null) {
            return $associadoId === $this->associadoId;
        }

        return $associationId !== null
            && in_array($associationId, $this->associationIds, true);
    }
}
