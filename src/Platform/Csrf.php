<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Token CSRF por sessão de sistema.
 */
final class Csrf
{
    private const CHAVE = 'csrf_token';

    public static function token(): string
    {
        $atual = $_SESSION[self::CHAVE] ?? null;
        if (is_string($atual) && strlen($atual) === 64) {
            return $atual;
        }

        $novo = bin2hex(random_bytes(32));
        $_SESSION[self::CHAVE] = $novo;

        return $novo;
    }

    public static function validar(mixed $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $atual = $_SESSION[self::CHAVE] ?? null;

        return is_string($atual) && hash_equals($atual, $token);
    }

    public static function rotacionar(): void
    {
        $_SESSION[self::CHAVE] = bin2hex(random_bytes(32));
    }
}
