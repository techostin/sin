<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Modules\Identidade\CapabilityService;
use App\Tests\Support\ContextoUsuarioFake;
use PHPUnit\Framework\TestCase;

final class CapabilityServiceTest extends TestCase
{
    public function testFiscalHasFiscalizacaoAndPwaButNotFinanceiro(): void
    {
        $service = new CapabilityService();
        $context = new ContextoUsuarioFake(
            ['fiscal_campo'],
            null,
            null,
            [],
            [],
            'Fiscal Teste',
            ['fiscalizacao.read', 'fiscalizacao.write', 'caderno.read', 'portal.read']
        );

        $result = $service->forContext($context);
        $keys = array_column($result['navigation'], 'key');

        self::assertContains('fiscalizacao', $keys);
        self::assertContains('caderno', $keys);
        self::assertNotContains('financeiro', $keys);
        self::assertSame(['pwa'], $result['surfaces']);
        self::assertContains('fiscalizacao.write', $result['capabilities']);
    }

    public function testAdminSeesEverything(): void
    {
        $service = new CapabilityService();
        $context = new ContextoUsuarioFake(['admin'], null, null, [], [], 'Admin', ['*']);

        $result = $service->forContext($context);

        self::assertCount(9, $result['navigation']);
        self::assertSame(['web'], $result['surfaces']);
    }

    public function testEffectivePermissionsUsesFallbackWhenDatabaseHasNoPermissions(): void
    {
        $service = new CapabilityService();
        $permissions = $service->effectivePermissions(
            [['code' => 'produtor', 'scope_type' => 'global', 'scope_id' => 0]],
            []
        );

        self::assertContains('caderno.write', $permissions);
        self::assertContains('associados.read', $permissions);
    }

    public function testAdminFallbackIsWildcard(): void
    {
        $service = new CapabilityService();
        $permissions = $service->effectivePermissions(
            [['code' => 'admin', 'scope_type' => 'global', 'scope_id' => 0]],
            []
        );

        self::assertSame(['*'], $permissions);
    }
}
