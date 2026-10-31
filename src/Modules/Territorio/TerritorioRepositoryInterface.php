<?php

declare(strict_types=1);

namespace App\Modules\Territorio;

interface TerritorioRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function estados(): array;

    /** @return list<array<string, mixed>> */
    public function cidades(?int $estadoId, ?string $uf): array;

    /** @return list<array<string, mixed>> */
    public function microrregioes(?int $mesorregiaoId): array;

    /**
     * @param array{termo?: string, uf?: string, cidade?: string, tipo_entidade?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function cnpo(array $filters, int $limit): array;
}
