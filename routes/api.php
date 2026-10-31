<?php

declare(strict_types=1);

use App\Kernel\Container;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;
use App\Kernel\Persistence\Connection;
use App\Kernel\Support\Logger;
use App\Modules\Health\HealthController;
use App\Modules\Territorio\DatabaseTerritorioRepository;
use App\Modules\Territorio\TerritorioController;

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

    $territorio = static fn (): TerritorioController => new TerritorioController(
        new DatabaseTerritorioRepository(Connection::pdo())
    );

    $router->get('/territorios/estados', static fn (Request $request): Response => $territorio()->estados($request));
    $router->get('/territorios/cidades', static fn (Request $request): Response => $territorio()->cidades($request));
    $router->get(
        '/territorios/microrregioes',
        static fn (Request $request): Response => $territorio()->microrregioes($request)
    );
    $router->get('/territorios/cnpo', static fn (Request $request): Response => $territorio()->cnpo($request));
};
