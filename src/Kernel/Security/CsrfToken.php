<?php

declare(strict_types=1);

namespace App\Kernel\Security;

/**
 * Token CSRF com comparação em tempo constante. O armazenamento (sessão) é do chamador.
 */
final class CsrfToken
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function isValid(string $expected, ?string $provided): bool
    {
        if ($provided === null || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
