<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Request;
use App\Modules\Identidade\CapabilityService;
use App\Modules\Identidade\MeController;
use App\Tests\Support\ContextoUsuarioFake;
use App\Tests\Support\FakeContextoUsuarioProvider;
use PHPUnit\Framework\TestCase;

final class MeControllerTest extends TestCase
{
    public function testAnonymousGets401(): void
    {
        $controller = new MeController(
            new FakeContextoUsuarioProvider(null),
            new CapabilityService()
        );

        $response = $controller->me(Request::create('GET', '/me'));

        self::assertSame(401, $response->status);
        self::assertStringContainsString('NAO_AUTENTICADO', $response->body);
    }

    public function testMeReturnsUserData(): void
    {
        $context = new ContextoUsuarioFake(['admin'], 7, null, [], [], 'Edmilson Santos', ['*']);
        $controller = new MeController(
            new FakeContextoUsuarioProvider($context),
            new CapabilityService()
        );

        $response = $controller->me(Request::create('GET', '/me'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"name":"Edmilson Santos"', $response->body);
        self::assertStringContainsString('"roles":["admin"]', $response->body);
    }

    public function testCapabilitiesIncludeNavigationAndSurfaces(): void
    {
        $context = new ContextoUsuarioFake(
            ['gestor_fiscalizacao'],
            null,
            1,
            [10],
            ['vegetal'],
            'Gestor Fiscal',
            ['fiscalizacao.read', 'fiscalizacao.review', 'associados.read', 'portal.read']
        );
        $controller = new MeController(
            new FakeContextoUsuarioProvider($context),
            new CapabilityService()
        );

        $response = $controller->capabilities(Request::create('GET', '/me/capabilities'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('"navigation"', $response->body);
        self::assertStringContainsString('"key":"fiscalizacao"', $response->body);
        self::assertStringContainsString('"surfaces":["web"]', $response->body);
    }
}
