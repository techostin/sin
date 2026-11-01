<?php

declare(strict_types=1);

namespace App\Modules\Fiscalizacao;

interface FiscalizacaoRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVisit(array $data, int $userId): array;

    /**
     * @param list<int> $associationIds
     * @return list<array<string, mixed>>
     */
    public function listVisits(?int $associadoId, array $associationIds): array;

    /** @return array<string, mixed>|null */
    public function findVisit(string $uuid): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function addNonConformity(string $uuid, array $data, int $userId): ?array;

    public function review(string $uuid, string $decision, ?string $notes, int $userId): bool;

    public function updateStatus(string $uuid, string $status, int $userId): bool;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function issueDocument(array $data): array;

    /** @return array<string, mixed>|null */
    public function findDocumentByNumber(string $number): ?array;
}
