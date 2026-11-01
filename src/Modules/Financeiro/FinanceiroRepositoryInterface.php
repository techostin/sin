<?php

declare(strict_types=1);

namespace App\Modules\Financeiro;

interface FinanceiroRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createMensalidade(array $data): array;

    /**
     * @param list<int> $associationIds
     * @return list<array<string, mixed>>
     */
    public function listMensalidades(?int $associadoId, array $associationIds): array;

    /** @return array<string, mixed>|null */
    public function findMensalidade(int $id): ?array;

    public function pay(int $id): bool;
}
