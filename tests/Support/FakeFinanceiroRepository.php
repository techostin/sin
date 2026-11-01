<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Financeiro\FinanceiroRepositoryInterface;

final class FakeFinanceiroRepository implements FinanceiroRepositoryInterface
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createMensalidade(array $data): array
    {
        return ['id' => 1, 'associado_id' => $data['associado_id'] ?? 0, 'status_mensalidade' => 'pendente'];
    }

    /** @param list<int> $associationIds @return list<array<string, mixed>> */
    public function listMensalidades(?int $associadoId, array $associationIds): array
    {
        return [['id' => 1, 'associado_id' => 7, 'status_mensalidade' => 'pendente']];
    }

    /** @return array<string, mixed>|null */
    public function findMensalidade(int $id): ?array
    {
        return $id === 1 ? ['id' => 1, 'associado_id' => 7, 'associacao_id' => 1] : null;
    }

    public function pay(int $id): bool
    {
        return $id === 1;
    }
}
