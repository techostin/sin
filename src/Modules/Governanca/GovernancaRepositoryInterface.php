<?php

declare(strict_types=1);

namespace App\Modules\Governanca;

interface GovernancaRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createEvent(array $data, int $userId): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createAssembly(array $data, int $userId): array;

    /** @return list<array<string, mixed>> */
    public function listEvents(): array;

    /** @return list<array<string, mixed>> */
    public function listAssemblies(): array;
}
