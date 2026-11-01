<?php

declare(strict_types=1);

namespace App\Modules\Associados;

interface AssociadosRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    public function approve(string $uuid): bool;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, ?string $status, ?string $q): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array;
}
