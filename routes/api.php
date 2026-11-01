<?php

declare(strict_types=1);

use App\Kernel\Container;
use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;
use App\Kernel\Persistence\Connection;
use App\Kernel\Support\Logger;
use App\Modules\Associados\AssociadosController;
use App\Modules\Associados\DatabaseAssociadosRepository;
use App\Modules\Health\HealthController;
use App\Modules\Identidade\CapabilityService;
use App\Modules\Identidade\DatabaseIdentityRepository;
use App\Modules\Identidade\MeController;
use App\Modules\Identidade\SessionContextProvider;
use App\Modules\Rastreabilidade\DatabaseRastreabilidadeRepository;
use App\Modules\Rastreabilidade\RastreabilidadeController;
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

    $capabilities = new CapabilityService();
    $contextProvider = new SessionContextProvider(
        new DatabaseIdentityRepository(Connection::pdo()),
        $capabilities
    );

    $me = static fn (): MeController => new MeController($contextProvider, $capabilities);

    $router->get('/me', static fn (Request $request): Response => $me()->me($request));
    $router->get('/me/capabilities', static fn (Request $request): Response => $me()->capabilities($request));

    $guard = static function (Request $request, string $resource, string $action) use ($contextProvider): ContextoUsuario|Response {
        $context = $contextProvider->current();
        if ($context === null) {
            return Response::error('NAO_AUTENTICADO', 'Autenticacao necessaria.', 401, $request->requestId);
        }
        if (!$context->can($resource, $action)) {
            return Response::error('SEM_PERMISSAO', 'Acesso negado.', 403, $request->requestId);
        }

        return $context;
    };

    $associados = static fn (): AssociadosController => new AssociadosController(
        new DatabaseAssociadosRepository(Connection::pdo())
    );

    $router->get('/associados', static function (Request $request) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'read');
        return $context instanceof Response ? $context : $associados()->list($request);
    });
    $router->get('/associados/pendentes', static function (Request $request) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'read');
        return $context instanceof Response ? $context : $associados()->pending($request);
    });
    $router->get('/associados/{uuid}', static function (Request $request, array $params) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'read');
        return $context instanceof Response ? $context : $associados()->show($request, $params['uuid'] ?? '');
    });
    $router->post('/associados', static function (Request $request) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'write');
        return $context instanceof Response ? $context : $associados()->store($request);
    });
    $router->post('/associados/{uuid}/aprovar', static function (Request $request, array $params) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'approve');
        return $context instanceof Response ? $context : $associados()->approve($request, $params['uuid'] ?? '');
    });

    $rastreabilidade = static fn (): RastreabilidadeController => new RastreabilidadeController(
        new DatabaseRastreabilidadeRepository(Connection::pdo())
    );

    $router->post('/caderno/lotes', static function (Request $request) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $rastreabilidade()->createVegetal($request, $context);
    });
    $router->post('/insumos/lotes', static function (Request $request) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $rastreabilidade()->createInsumo($request, $context);
    });
    $router->get('/rastreabilidade/lotes/{uuid}', static function (Request $request, array $params) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'read');
        return $context instanceof Response ? $context : $rastreabilidade()->show($request, $params['uuid'] ?? '');
    });
    $router->post('/rastreabilidade/lotes/{uuid}/eventos', static function (Request $request, array $params) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $rastreabilidade()->addEvent($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/rastreabilidade/lotes/{uuid}/vinculos', static function (Request $request, array $params) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $rastreabilidade()->addLink($request, $params['uuid'] ?? '', $context);
    });
    $router->get('/rastreabilidade/verificar/{codigo}', static function (Request $request, array $params) use ($rastreabilidade): Response {
        return $rastreabilidade()->verify($request, $params['codigo'] ?? '');
    });
};
