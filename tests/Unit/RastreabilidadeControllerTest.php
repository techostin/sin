<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Rastreabilidade\RastreabilidadeController;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeRastreabilidadeRepository;
use PHPUnit\Framework\TestCase;

final class RastreabilidadeControllerTest extends TestCase
{
    public function testCreateVegetalLot(): void
    {
        $repository = new FakeRastreabilidadeRepository();
        $controller = new RastreabilidadeController($repository);
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->createVegetal(
            Request::create('POST', '/caderno/lotes', [], [], null, json_encode([
                'nome_lote' => 'Lote Tomate 01',
                'produto_id' => 10,
                'data_producao' => '2026-11-01',
                'quantidade' => 120,
                'unidade_medida' => 'kg',
            ], JSON_THROW_ON_ERROR)),
            $context
        );

        self::assertSame(201, $response->status);
        self::assertStringContainsString('LOTE-ABC123', $response->body);
        self::assertSame(7, $repository->lastData['associado_id']);
    }

    public function testCreateVegetalRequiresFields(): void
    {
        $controller = new RastreabilidadeController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->createVegetal(
            Request::create('POST', '/caderno/lotes', [], [], null, '{}'),
            $context
        );

        self::assertSame(400, $response->status);
    }

    public function testCreateInputLot(): void
    {
        $controller = new RastreabilidadeController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->createInsumo(
            Request::create('POST', '/insumos/lotes', [], [], null, '{"nome":"Composto organico"}'),
            $context
        );

        self::assertSame(201, $response->status);
        self::assertStringContainsString('LOTE-DEF456', $response->body);
    }

    public function testVerifyPublicCode(): void
    {
        $controller = new RastreabilidadeController(new FakeRastreabilidadeRepository());

        self::assertSame(200, $controller->verify(Request::create('GET', '/x'), 'LOTE-ABC123')->status);
        self::assertSame(404, $controller->verify(Request::create('GET', '/x'), 'LOTE-ZZZ999')->status);
    }
}
