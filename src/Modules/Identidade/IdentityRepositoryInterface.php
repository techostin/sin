<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

interface IdentityRepositoryInterface
{
    /**
     * @return array{
     *   user: array<string, mixed>,
     *   roles: list<array{code: string, scope_type: string, scope_id: int}>,
     *   permissions: list<string>
     * }|null
     */
    public function load(int $userId): ?array;
}
