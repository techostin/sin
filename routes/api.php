<?php

declare(strict_types=1);

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Documents\CertificateSigner;
use App\Kernel\Documents\DocumentNumberGenerator;
use App\Kernel\Documents\MpdfCertificateGenerator;
use App\Kernel\Audit\DatabaseAuditLogger;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Http\Router;
use App\Kernel\Persistence\Connection;
use App\Kernel\Support\Logger;
use App\Modules\Agroindustria\AgroindustriaController;
use App\Modules\Animal\AnimalController;
use App\Modules\Associados\AssociadosController;
use App\Modules\Associados\DatabaseAssociadosRepository;
use App\Modules\Fiscalizacao\DatabaseFiscalizacaoRepository;
use App\Modules\Fiscalizacao\FiscalizacaoController;
use App\Modules\Fiscalizacao\FiscalizacaoService;
use App\Modules\Financeiro\DatabaseFinanceiroRepository;
use App\Modules\Financeiro\FinanceiroController;
use App\Modules\Governanca\DatabaseGovernancaRepository;
use App\Modules\Governanca\GovernancaController;
use App\Modules\Health\HealthController;
use App\Modules\Identidade\CapabilityService;
use App\Modules\Identidade\DatabaseIdentityRepository;
use App\Modules\Identidade\MeController;
use App\Modules\Identidade\SessionContextProvider;
use App\Modules\Rastreabilidade\DatabaseRastreabilidadeRepository;
use App\Modules\Rastreabilidade\RastreabilidadeController;
use App\Modules\Territorio\DatabaseTerritorioRepository;
use App\Modules\Territorio\TerritorioController;
use App\Kernel\Container;

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
        return $context instanceof Response ? $context : $associados()->list($request, $context);
    });
    $router->get('/associados/pendentes', static function (Request $request) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'read');
        return $context instanceof Response ? $context : $associados()->pending($request, $context);
    });
    $router->get('/associados/{uuid}', static function (Request $request, array $params) use ($guard, $associados): Response {
        $context = $guard($request, 'associados', 'read');
        return $context instanceof Response ? $context : $associados()->show($request, $params['uuid'] ?? '', $context);
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
    $router->post('/insumos', static function (Request $request) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'write');
        if ($context instanceof Response) {
            return $context;
        }
        $insumo = new \App\Modules\Insumos\InsumoController(
            new DatabaseRastreabilidadeRepository(Connection::pdo())
        );
        return $insumo->create($request, $context);
    });
    $router->get('/rastreabilidade/lotes/{uuid}', static function (Request $request, array $params) use ($guard, $rastreabilidade): Response {
        $context = $guard($request, 'caderno', 'read');
        return $context instanceof Response ? $context : $rastreabilidade()->show($request, $params['uuid'] ?? '', $context);
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

    $fiscalizacao = static function () use ($container): FiscalizacaoController {
        $root = dirname(__DIR__);

        return new FiscalizacaoController(
            new FiscalizacaoService(
                new DatabaseFiscalizacaoRepository(Connection::pdo()),
                new DatabaseRastreabilidadeRepository(Connection::pdo()),
                new DocumentNumberGenerator(Connection::pdo()),
                new DatabaseAuditLogger(Connection::pdo()),
                new MpdfCertificateGenerator(
                    $root . '/.certificados',
                    $root . '/.pdf-tmp',
                    $root . '/resources/certificados/certificado.php'
                ),
                new CertificateSigner()
            )
        );
    };

    $router->get('/fiscalizacao/visitas', static function (Request $request) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'read');
        return $context instanceof Response ? $context : $fiscalizacao()->list($request, $context);
    });
    $router->post('/fiscalizacao/visitas', static function (Request $request) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'write');
        return $context instanceof Response ? $context : $fiscalizacao()->create($request, $context);
    });
    $router->get('/fiscalizacao/visitas/{uuid}', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'read');
        return $context instanceof Response ? $context : $fiscalizacao()->show($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/fiscalizacao/visitas/{uuid}/nao-conformidades', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'write');
        return $context instanceof Response ? $context : $fiscalizacao()->addNonConformity($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/fiscalizacao/visitas/{uuid}/status', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'write');
        return $context instanceof Response ? $context : $fiscalizacao()->status($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/fiscalizacao/visitas/{uuid}/evidencias', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'write');
        return $context instanceof Response ? $context : $fiscalizacao()->evidence($request, $params['uuid'] ?? '', $context);
    });
    $router->get('/fiscalizacao/visitas/{uuid}/evidencias', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'read');
        return $context instanceof Response ? $context : $fiscalizacao()->evidenceList($request, $params['uuid'] ?? '', $context);
    });
    $router->get('/fiscalizacao/evidencias/{uuid}', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'read');
        return $context instanceof Response ? $context : $fiscalizacao()->evidenceDownload($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/fiscalizacao/visitas/{uuid}/revisar', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'review');
        return $context instanceof Response ? $context : $fiscalizacao()->review($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/fiscalizacao/visitas/{uuid}/certificado', static function (Request $request, array $params) use ($guard, $fiscalizacao): Response {
        $context = $guard($request, 'fiscalizacao', 'certificate');
        return $context instanceof Response ? $context : $fiscalizacao()->certificate($request, $params['uuid'] ?? '', $context);
    });
    $router->get('/documentos/verificar/{numero}', static function (Request $request, array $params) use ($fiscalizacao): Response {
        return $fiscalizacao()->verify($request, $params['numero'] ?? '');
    });
    $router->get('/documentos/{numero}/pdf', static function (Request $request, array $params) use ($fiscalizacao): Response {
        return $fiscalizacao()->pdf($request, $params['numero'] ?? '');
    });

    $animal = static fn (): AnimalController => new AnimalController(
        new DatabaseRastreabilidadeRepository(Connection::pdo())
    );
    $agroindustria = static fn (): AgroindustriaController => new AgroindustriaController(
        new DatabaseRastreabilidadeRepository(Connection::pdo())
    );

    $router->post('/animal/lotes', static function (Request $request) use ($guard, $animal): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $animal()->create($request, $context);
    });
    $router->post('/animal/lotes/{uuid}/animais', static function (Request $request, array $params) use ($guard, $animal): Response {
        $context = $guard($request, 'caderno', 'write');
        return $context instanceof Response ? $context : $animal()->addAnimal($request, $params['uuid'] ?? '', $context);
    });
    $router->post('/animal/lotes/{uuid}/animais/{animalId}/sanidade', static function (Request $request, array $params) use ($guard, $animal): Response {
        $context = $guard($request, 'caderno', 'write');
        $animalId = isset($params['animalId']) && is_numeric($params['animalId']) ? (int) $params['animalId'] : 0;
        return $context instanceof Response ? $context : $animal()->sanitary($request, $params['uuid'] ?? '', $animalId, $context);
    });
    $router->post('/animal/lotes/{uuid}/animais/{animalId}/reproducao', static function (Request $request, array $params) use ($guard, $animal): Response {
        $context = $guard($request, 'caderno', 'write');
        $animalId = isset($params['animalId']) && is_numeric($params['animalId']) ? (int) $params['animalId'] : 0;
        return $context instanceof Response ? $context : $animal()->reproduction($request, $params['uuid'] ?? '', $animalId, $context);
    });
    $router->post('/animal/lotes/{uuid}/animais/{animalId}/movimentacao', static function (Request $request, array $params) use ($guard, $animal): Response {
        $context = $guard($request, 'caderno', 'write');
        $animalId = isset($params['animalId']) && is_numeric($params['animalId']) ? (int) $params['animalId'] : 0;
        return $context instanceof Response ? $context : $animal()->movement($request, $params['uuid'] ?? '', $animalId, $context);
    });
    $router->post('/agroindustria/transformacoes', static function (Request $request) use ($guard, $agroindustria): Response {
        $context = $guard($request, 'agroindustria', 'write');
        return $context instanceof Response ? $context : $agroindustria()->transform($request, $context);
    });
    $router->post('/agroindustria/receitas', static function (Request $request) use ($guard, $agroindustria): Response {
        $context = $guard($request, 'agroindustria', 'write');
        return $context instanceof Response ? $context : $agroindustria()->recipe($request, $context);
    });
    $router->post('/agroindustria/ordens', static function (Request $request) use ($guard, $agroindustria): Response {
        $context = $guard($request, 'agroindustria', 'write');
        return $context instanceof Response ? $context : $agroindustria()->order($request, $context);
    });
    $router->post('/agroindustria/ordens/{uuid}/higienizacao', static function (Request $request, array $params) use ($guard, $agroindustria): Response {
        $context = $guard($request, 'agroindustria', 'write');
        return $context instanceof Response ? $context : $agroindustria()->hygiene($request, $params['uuid'] ?? '', $context);
    });

    $governanca = static fn (): GovernancaController => new GovernancaController(
        new DatabaseGovernancaRepository(Connection::pdo())
    );
    $router->get('/governanca/eventos', static function (Request $request) use ($guard, $governanca): Response {
        $context = $guard($request, 'governanca', 'read');
        return $context instanceof Response ? $context : $governanca()->events($request);
    });
    $router->post('/governanca/eventos', static function (Request $request) use ($guard, $governanca): Response {
        $context = $guard($request, 'governanca', 'write');
        return $context instanceof Response ? $context : $governanca()->createEvent($request, $context);
    });
    $router->get('/governanca/assembleias', static function (Request $request) use ($guard, $governanca): Response {
        $context = $guard($request, 'governanca', 'read');
        return $context instanceof Response ? $context : $governanca()->assemblies($request);
    });
    $router->post('/governanca/assembleias', static function (Request $request) use ($guard, $governanca): Response {
        $context = $guard($request, 'governanca', 'write');
        return $context instanceof Response ? $context : $governanca()->createAssembly($request, $context);
    });

    $financeiro = static fn (): FinanceiroController => new FinanceiroController(
        new DatabaseFinanceiroRepository(Connection::pdo())
    );
    $router->get('/financeiro/mensalidades', static function (Request $request) use ($guard, $financeiro): Response {
        $context = $guard($request, 'financeiro', 'read');
        return $context instanceof Response ? $context : $financeiro()->list($request, $context);
    });
    $router->post('/financeiro/mensalidades', static function (Request $request) use ($guard, $financeiro): Response {
        $context = $guard($request, 'financeiro', 'write');
        return $context instanceof Response ? $context : $financeiro()->create($request, $context);
    });
    $router->post('/financeiro/mensalidades/{id}/pagar', static function (Request $request, array $params) use ($guard, $financeiro): Response {
        $context = $guard($request, 'financeiro', 'write');
        $id = isset($params['id']) && is_numeric($params['id']) ? (int) $params['id'] : 0;
        return $context instanceof Response ? $context : $financeiro()->pay($request, $id, $context);
    });
};
