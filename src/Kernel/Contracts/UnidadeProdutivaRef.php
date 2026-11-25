<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

/**
 * Referência de unidade produtiva (propriedade, agroindústria ou ambiente animal).
 */
interface UnidadeProdutivaRef
{
    public function type(): string;

    public function id(): int;

    public function uuid(): string;

    public function associadoId(): int;

    public function label(): string;

    public function municipalityId(): ?int;
}
