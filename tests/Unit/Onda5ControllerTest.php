<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Identidade\CapabilityService;
use App\Modules\Portal\PortalController;
use App\Modules\Relatorios\RelatoriosController;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeRelatoriosRepository;
use PHPUnit\Framework\TestCase;

final class Onda5ControllerTest extends TestCase
{
    public function testRelatoriosSummary(): void
    {
        $controller = new RelatoriosController(new FakeRelatoriosRepository());
        $context = new ContextoUsuarioFake(['admin'], null, null, [], [], 'Admin', ['*']);

        $response = $controller->summary(Request::create('GET', '/relatorios/resumo'), $context);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"associados"', $response->body);
    }

    public function testPortalDashboardIsPersonaAware(): void
    {
        $controller = new PortalController(new FakeRelatoriosRepository(), new CapabilityService());
        $fiscal = new ContextoUsuarioFake(
            ['fiscal_campo'],
            7,
            null,
            [],
            [],
            'Fiscal',
            ['portal.read', 'fiscalizacao.read']
        );

        $response = $controller->dashboard(Request::create('GET', '/portal/dashboard'), $fiscal);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"key":"fiscalizacao"', $response->body);
        self::assertStringNotContainsString('"key":"financeiro"', $response->body);
    }
}
