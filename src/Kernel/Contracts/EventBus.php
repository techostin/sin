<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

interface EventBus
{
    /** @param array<string, mixed> $payload */
    public function publish(string $type, array $payload): void;
}
