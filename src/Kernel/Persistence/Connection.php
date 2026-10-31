<?php

declare(strict_types=1);

namespace App\Kernel\Persistence;

use PDO;

/**
 * Conexão PDO única do processo. O Kernel é o único que conhece as credenciais.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = defined('DB_HOST') ? (string) constant('DB_HOST') : 'localhost';
        $name = defined('DB_NAME') ? (string) constant('DB_NAME') : 'sisagro';
        $user = defined('DB_USER') ? (string) constant('DB_USER') : '';
        $pass = defined('DB_PASS') ? (string) constant('DB_PASS') : '';

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
