<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Documents\CertificateSigner;
use PHPUnit\Framework\TestCase;

final class CertificateSignerTest extends TestCase
{
    public function testSignsAndVerifiesPayload(): void
    {
        $signer = new CertificateSigner('test-key');
        $payload = ['numero' => 'CERT-2026-000001', 'lote' => 'LOTE-ABC'];
        $signature = $signer->sign($payload, 'hash-do-arquivo');

        self::assertTrue($signer->verify($payload, 'hash-do-arquivo', $signature));
        self::assertFalse($signer->verify($payload, 'outro-hash', $signature));
    }
}
