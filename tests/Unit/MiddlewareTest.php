<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Middleware\SecurityHeadersMiddleware;
use App\Kernel\Http\Pipeline;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use PHPUnit\Framework\TestCase;

final class MiddlewareTest extends TestCase
{
    public function testSecurityHeadersAreApplied(): void
    {
        $pipeline = new Pipeline([new SecurityHeadersMiddleware()]);

        $response = $pipeline->process(
            Request::create('GET', '/health'),
            static fn (Request $request): Response => Response::json(['ok' => true])
        );

        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('SAMEORIGIN', $response->headers['X-Frame-Options']);
        self::assertSame('strict-origin-when-cross-origin', $response->headers['Referrer-Policy']);
        self::assertStringContainsString('"ok":true', $response->body);
    }
}
