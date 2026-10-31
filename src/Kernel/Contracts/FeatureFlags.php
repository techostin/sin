<?php

declare(strict_types=1);

namespace App\Kernel\Contracts;

/**
 * Feature flags de liberação gradual (tabela feature_flags).
 */
interface FeatureFlags
{
    public function isEnabled(string $key): bool;

    /** @return array<string, bool> */
    public function all(): array;
}
