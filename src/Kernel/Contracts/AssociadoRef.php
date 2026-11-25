<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

/**
 * Referência pública e mínima do associado/agricultor (dado comum do sistema).
 * Consumida por todos os módulos; CPF mascarado por padrão (LGPD).
 */
interface AssociadoRef
{
    public function id(): int;

    public function uuid(): string;

    public function displayName(): string;

    public function maskedCpf(): ?string;

    public function status(): string;

    public function cnpoRegistration(): ?string;

    public function mainAssociationId(): ?int;

    public function microRegionId(): ?int;

    public function updatedAt(): string;
}
