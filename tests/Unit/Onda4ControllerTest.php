<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Agroindustria\AgroindustriaController;
use App\Modules\Animal\AnimalController;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeRastreabilidadeRepository;
use PHPUnit\Framework\TestCase;

final class Onda4ControllerTest extends TestCase
{
    public function testCreatesAnimalLot(): void
    {
        $controller = new AnimalController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->create(
            Request::create(
                'POST',
                '/animal/lotes',
                [],
                [],
                null,
                '{"nome_lote":"Lote Animal 01","especie":"Bovino","quantidade":12}'
            ),
            $context
        );

        self::assertSame(201, $response->status);
        self::assertStringContainsString('LOTE-ANI123', $response->body);
    }

    public function testAnimalLotRequiresName(): void
    {
        $controller = new AnimalController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->create(
            Request::create('POST', '/animal/lotes', [], [], null, '{}'),
            $context
        );
        self::assertSame(400, $response->status);
    }

    public function testCreatesAgroTransformation(): void
    {
        $controller = new AgroindustriaController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['agroindustria'], null, null, [], [], 'Agro', ['agroindustria.write']);

        $response = $controller->transform(
            Request::create('POST', '/agroindustria/transformacoes', [], [], null, json_encode([
                'origem_uuid' => '11111111-1111-4111-8111-111111111111',
                'destino_uuid' => '22222222-2222-4222-8222-222222222222',
                'quantidade' => 5,
                'unidade' => 'kg',
            ], JSON_THROW_ON_ERROR)),
            $context
        );

        self::assertSame(201, $response->status);
        self::assertStringContainsString('transformacao', $response->body);
    }

    public function testAgroTransformationRejectsInvalidUuid(): void
    {
        $controller = new AgroindustriaController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['agroindustria'], null, null, [], [], 'Agro', ['agroindustria.write']);

        $response = $controller->transform(
            Request::create('POST', '/x', [], [], null, '{}'),
            $context
        );
        self::assertSame(400, $response->status);
    }
}
