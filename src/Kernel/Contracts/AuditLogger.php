<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

interface AuditLogger
{
    /** @param array<string, mixed> $data */
    public function record(string $action, string $resource, ?string $resourceId = null, array $data = []): void;
}
