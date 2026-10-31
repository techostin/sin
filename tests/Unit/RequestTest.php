<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testCreateNormalizesMethodAndPath(): void
    {
        $request = Request::create('post', '/associados/');

        self::assertSame('POST', $request->method);
        self::assertSame('/associados', $request->path);
    }

    public function testQueryAndHeaderAccess(): void
    {
        $request = Request::create(
            'GET',
            '/busca',
            ['q' => 'terra'],
            ['X-Request-Id' => 'req-12345678', 'X-Custom' => 'valor']
        );

        self::assertSame('terra', $request->query('q'));
        self::assertSame('fallback', $request->query('ausente', 'fallback'));
        self::assertSame('valor', $request->header('x-custom'));
        self::assertSame('fallback', $request->header('ausente', 'fallback'));
        self::assertSame('req-12345678', $request->requestId);
    }

    public function testGeneratesRequestIdWhenAbsent(): void
    {
        $request = Request::create('GET', '/health');

        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $request->requestId);
    }
}
