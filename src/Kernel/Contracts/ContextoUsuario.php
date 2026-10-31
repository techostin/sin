<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

/**
 * Contexto autenticado da requisição (Kernel K1).
 * Resolvido uma única vez por requisição; nunca lido de $_SESSION pelos módulos.
 */
interface ContextoUsuario
{
    public function userId(): string;

    public function origin(): string;

    public function associadoId(): ?int;

    /** @return list<string> */
    public function roles(): array;

    public function certificadoraId(): ?int;

    /** @return list<int> */
    public function visibleAssociations(): array;

    /** @return list<string> */
    public function productionAreas(): array;

    public function can(string $resource, string $action): bool;
}
