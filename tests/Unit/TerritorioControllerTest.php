<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Territorio\TerritorioController;
use App\Tests\Support\FakeTerritorioRepository;
use PHPUnit\Framework\TestCase;

final class TerritorioControllerTest extends TestCase
{
    public function testEstadosReturnsDataAndCount(): void
    {
        $repository = new FakeTerritorioRepository();
        $response = (new TerritorioController($repository))->estados(Request::create('GET', '/territorios/estados'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"uf":"RS"', $response->body);
        self::assertStringContainsString('"count":1', $response->body);
    }

    public function testCidadesRequiresUfOrEstado(): void
    {
        $response = (new TerritorioController(new FakeTerritorioRepository()))
            ->cidades(Request::create('GET', '/territorios/cidades'));

        self::assertSame(400, $response->status);
        self::assertStringContainsString('VALIDACAO', $response->body);
    }

    public function testCidadesRejectsInvalidUf(): void
    {
        $response = (new TerritorioController(new FakeTerritorioRepository()))
            ->cidades(Request::create('GET', '/territorios/cidades', ['uf' => 'RIO']));

        self::assertSame(400, $response->status);
    }

    public function testCidadesFiltersByUf(): void
    {
        $repository = new FakeTerritorioRepository();
        $response = (new TerritorioController($repository))
            ->cidades(Request::create('GET', '/territorios/cidades', ['uf' => 'rs']));

        self::assertSame(200, $response->status);
        self::assertSame('RS', $repository->lastUf);
        self::assertNull($repository->lastEstadoId);
    }

    public function testCnpoRequiresTerm(): void
    {
        $response = (new TerritorioController(new FakeTerritorioRepository()))
            ->cnpo(Request::create('GET', '/territorios/cnpo', ['termo' => 'a']));

        self::assertSame(400, $response->status);
    }

    public function testCnpoCapsLimitAndPassesFilters(): void
    {
        $repository = new FakeTerritorioRepository();
        $response = (new TerritorioController($repository))
            ->cnpo(Request::create('GET', '/territorios/cnpo', [
                'termo' => 'terra',
                'uf' => 'rs',
                'limite' => '999',
                'tipo_entidade' => 'produtor',
            ]));

        self::assertSame(200, $response->status);
        self::assertSame(100, $repository->lastLimit);
        self::assertSame('RS', $repository->lastFilters['uf'] ?? null);
        self::assertSame('produtor', $repository->lastFilters['tipo_entidade'] ?? null);
    }

    public function testMicrorregioesRejectsInvalidId(): void
    {
        $response = (new TerritorioController(new FakeTerritorioRepository()))
            ->microrregioes(Request::create('GET', '/territorios/microrregioes', ['mesorregiao_id' => 'x']));

        self::assertSame(400, $response->status);
    }
}
