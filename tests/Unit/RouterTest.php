<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesHealthRoute(): void
    {
        $router = new Router();
        $router->get('/health', static function (Request $request): Response {
            return Response::json(['status' => 'ok'], 200, ['requestId' => $request->requestId]);
        });

        $response = $router->dispatch(Request::create('GET', '/health'));

        self::assertSame(200, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        self::assertStringContainsString('"status":"ok"', $response->body);
    }

    public function testReturnsNotFoundForUnknownPath(): void
    {
        $router = new Router();

        $response = $router->dispatch(Request::create('GET', '/nao-existe'));

        self::assertSame(404, $response->status);
        self::assertStringContainsString('NAO_ENCONTRADO', $response->body);
    }

    public function testReturnsMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/health', static fn (Request $request): Response => Response::json(['ok' => true]));

        $response = $router->dispatch(Request::create('POST', '/health'));

        self::assertSame(405, $response->status);
        self::assertStringContainsString('METODO_NAO_PERMITIDO', $response->body);
    }

    public function testExtractsRouteParameter(): void
    {
        $captured = null;
        $router = new Router();
        $router->get(
            '/associados/{uuid}',
            static function (Request $request, array $params) use (&$captured): Response {
                $captured = $params['uuid'] ?? null;

                return Response::json(['uuid' => $captured]);
            }
        );
        $response = $router->dispatch(Request::create('GET', '/associados/abc-123'));

        self::assertSame(200, $response->status);
        self::assertSame('abc-123', $captured);
        self::assertStringContainsString('"uuid":"abc-123"', $response->body);
    }
}
