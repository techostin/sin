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

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, ?string $status, ?string $q): array
    {
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
