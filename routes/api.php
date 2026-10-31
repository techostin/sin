<?php

declare(strict_types=1);

use App\Kernel\Container;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;
use App\Kernel\Support\Logger;
use App\Modules\Health\HealthController;
use RuntimeException;

/**
 * Registro de rotas da API v1. Cada módulo adiciona seu próprio arquivo em routes/.
 *
 * @return callable(Router, Container): void
 */
return static function (Router $router, Container $container): void {
    $router->get('/health', static function (Request $request) use ($container): Response {
        $logger = $container->get(Logger::class);
        if (!$logger instanceof Logger) {
            throw new RuntimeException('Logger invalido.');
        }

        return (new HealthController($logger))($request);
    });
};
