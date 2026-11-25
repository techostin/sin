<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel\Contracts\AuditLogger;

final class FakeAuditLogger implements AuditLogger
{
    /** @var list<array{action: string, resource: string, resourceId: ?string, data: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $data */
    public function record(string $action, string $resource, ?string $resourceId = null, array $data = []): void
    {
        $this->records[] = [
            'action' => $action,
            'resource' => $resource,
            'resourceId' => $resourceId,
            'data' => $data,
        ];
    }
}
