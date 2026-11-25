<?php

declare(strict_types=1);

use App\Kernel\Bootstrap;
use App\Kernel\Http\Middleware\SecurityHeadersMiddleware;
use App\Kernel\Http\Pipeline;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;

require_once __DIR__ . '/../../vendor/autoload.php';

$request = null;
$response = null;

try {
    $container = Bootstrap::api();

    $request = $container->get(Request::class);
    if (!$request instanceof Request) {
        throw new RuntimeException('Request invalido.');
    }

    $router = new Router();
    $register = require __DIR__ . '/../../routes/api.php';
    if (!is_callable($register)) {
        throw new RuntimeException('Registro de rotas invalido.');
    }
    $register($router, $container);

    $pipeline = new Pipeline([new SecurityHeadersMiddleware()]);
    $response = $pipeline->process($request, static fn (Request $current): Response => $router->dispatch($current));
} catch (Throwable $exception) {
    error_log('api.v1: ' . $exception::class . ' - ' . $exception->getMessage());
    $requestId = $request instanceof Request ? $request->requestId : null;
    $response = Response::error('ERRO_INTERNO', 'Erro interno.', 500, $requestId);
}

if (!headers_sent() && $request instanceof Request) {
    header('X-Request-Id: ' . $request->requestId);
}

$response->send();
