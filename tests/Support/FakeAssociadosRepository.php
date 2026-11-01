<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Associados\AssociadosRepositoryInterface;

final class FakeAssociadosRepository implements AssociadosRepositoryInterface
{
    public ?string $lastStatus = null;
    public ?string $lastQ = null;
    public int $lastPerPage = 0;

    /** @param list<array<string, mixed>> $items */
    public function __construct(private readonly array $items = [])
    {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(array $data): array
    {
        return array_merge([
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'nome' => $data['nome'] ?? '',
            'sobrenome' => $data['sobrenome'] ?? '',
            'cpf' => $data['cpf'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => 'pendente',
            'associacao_id' => $data['associacao_id'] ?? null,
            'microrregiao_id' => $data['microrregiao_id'] ?? null,
        ], $data);
    }

    public function approve(string $uuid): bool
    {
        return $uuid === '123e4567-e89b-12d3-a456-426614174000';
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(
        int $page,
        int $perPage,
        ?string $status,
        ?string $q,
        ?int $associadoId = null,
        array $associationIds = [],
    ): array {
        $this->lastStatus = $status;
        $this->lastQ = $q;
        $this->lastPerPage = $perPage;

        return ['items' => $this->items, 'total' => count($this->items)];
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        foreach ($this->items as $item) {
            if (($item['uuid'] ?? null) === $uuid) {
                return $item;
            }
        }

        return null;
    }
}
