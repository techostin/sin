<?php

declare(strict_types=1);

namespace App\Modules\Associados;

interface AssociadosRepositoryInterface
{
    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, ?string $status, ?string $q): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array;
}
