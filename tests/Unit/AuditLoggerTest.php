<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Audit\DatabaseAuditLogger;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    public function testRecordWritesAuditRow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                acao TEXT NOT NULL,
                tabela TEXT NULL,
                registro_id INTEGER NULL,
                descricao TEXT NULL,
                ip TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $_SESSION['user_id'] = 7;
        try {
            (new DatabaseAuditLogger($pdo))->record('emitir', 'certificado', '123', ['status' => 'emitido']);
        } finally {
            unset($_SESSION['user_id']);
        }

        $row = $pdo->query('SELECT * FROM audit_log')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(7, (int) $row['user_id']);
        self::assertSame('emitir', $row['acao']);
        self::assertSame('certificado', $row['tabela']);
        self::assertSame(123, (int) $row['registro_id']);
        self::assertStringContainsString('emitido', (string) $row['descricao']);
    }
}
