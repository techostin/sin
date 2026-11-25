<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Documents\DocumentNumberGenerator;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class DocumentNumberGeneratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE document_sequences (
                tipo TEXT NOT NULL,
                ano INTEGER NOT NULL,
                ultimo_numero INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tipo, ano)
            )'
        );
    }

    public function testGeneratesSequentialNumbers(): void
    {
        $generator = new DocumentNumberGenerator($this->pdo);

        self::assertSame('CERT-2026-000001', $generator->next('CERT', 2026));
        self::assertSame('CERT-2026-000002', $generator->next('CERT', 2026));
        self::assertSame('CERT-2027-000001', $generator->next('CERT', 2027));
    }

    public function testRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DocumentNumberGenerator($this->pdo))->next('certificado invalido', 2026);
    }
}
