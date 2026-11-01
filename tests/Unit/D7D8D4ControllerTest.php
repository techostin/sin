<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Financeiro\FinanceiroController;
use App\Modules\Governanca\GovernancaController;
use App\Modules\Insumos\InsumoController;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeFinanceiroRepository;
use App\Tests\Support\FakeGovernancaRepository;
use App\Tests\Support\FakeRastreabilidadeRepository;
use PHPUnit\Framework\TestCase;

final class D7D8D4ControllerTest extends TestCase
{
    public function testCreatesLegacyInsumo(): void
    {
        $controller = new InsumoController(new FakeRastreabilidadeRepository());
        $context = new ContextoUsuarioFake(['produtor'], 7, null, [], [], 'Produtor', ['caderno.write']);

        $response = $controller->create(
            Request::create('POST', '/insumos', [], [], null, '{"nome":"Composto"}'),
            $context
        );

        self::assertSame(201, $response->status);
        self::assertStringContainsString('LOTE-INS999', $response->body);
    }

    public function testGovernancaEventAndAssembly(): void
    {
        $controller = new GovernancaController(new FakeGovernancaRepository());
        $context = new ContextoUsuarioFake(['gestor_associacao'], null, null, [1], [], 'Gestor', ['governanca.write']);

        self::assertSame(201, $controller->createEvent(
            Request::create('POST', '/x', [], [], null, '{"nome":"Dia de Campo"}'),
            $context
        )->status);
        self::assertSame(201, $controller->createAssembly(
            Request::create('POST', '/x', [], [], null, '{"titulo":"Assembleia Geral"}'),
            $context
        )->status);
    }

    public function testFinanceiroCreateAndPay(): void
    {
        $controller = new FinanceiroController(new FakeFinanceiroRepository());
        $context = new ContextoUsuarioFake(['gestor_associacao'], null, null, [1], [], 'Gestor', ['financeiro.write']);

        $created = $controller->create(
            Request::create('POST', '/x', [], [], null, '{"associado_id":7,"valor_cobrado":50}'),
            $context
        );
        self::assertSame(201, $created->status);

        self::assertSame(200, $controller->pay(Request::create('POST', '/x'), 1, $context)->status);
        self::assertSame(404, $controller->pay(Request::create('POST', '/x'), 9, $context)->status);
    }
}
