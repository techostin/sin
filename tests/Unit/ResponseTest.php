<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonEnvelopeContainsDataAndMeta(): void
    {
        $response = Response::json(['status' => 'ok'], 201, ['requestId' => 'req-12345678']);

        self::assertSame(201, $response->status);
        self::assertStringContainsString('"data":{"status":"ok"}', $response->body);
        self::assertStringContainsString('"meta":{"requestId":"req-12345678"}', $response->body);
    }

    public function testErrorEnvelope(): void
    {
        $response = Response::error('VALIDACAO', 'Entrada invalida.', 400, 'req-12345678');

        self::assertSame(400, $response->status);
        self::assertStringContainsString('"error"', $response->body);
        self::assertStringContainsString('"code":"VALIDACAO"', $response->body);
        self::assertStringContainsString('"requestId":"req-12345678"', $response->body);
    }

    public function testWithHeaderPreservesBodyAndStatus(): void
    {
        $response = Response::json(['ok' => true], 200)->withHeader('X-Test', '1');

        self::assertSame(200, $response->status);
        self::assertSame('1', $response->headers['X-Test']);
        self::assertStringContainsString('"ok":true', $response->body);
    }
}
