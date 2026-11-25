<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel\Http\Middleware\AuthMiddleware;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\CsrfToken;
use App\Kernel\Security\RolePolicy;
use App\Kernel\Security\TenantScope;
use App\Kernel\Security\UploadValidator;
use App\Tests\Support\ContextoUsuarioFake;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testAdminPolicyAllowsEverything(): void
    {
        $policy = new RolePolicy();

        self::assertTrue($policy->allows(new ContextoUsuarioFake(['admin']), 'financeiro', 'write'));
    }

    public function testTechnicianPolicyIsNarrow(): void
    {
        $policy = new RolePolicy();
        $context = new ContextoUsuarioFake(['tecnico']);

        self::assertTrue($policy->allows($context, 'associados', 'read'));
        self::assertTrue($policy->allows($context, 'associados', 'write'));
        self::assertFalse($policy->allows($context, 'financeiro', 'write'));
        self::assertFalse($policy->allows($context, 'relatorios', 'export'));
    }

    public function testUnknownRoleIsDenied(): void
    {
        self::assertFalse((new RolePolicy())->allows(new ContextoUsuarioFake(['visitante']), 'associados', 'read'));
    }

    public function testCapabilitiesTakePrecedenceOverLegacyRoles(): void
    {
        $policy = new RolePolicy();
        $context = new ContextoUsuarioFake(['tecnico'], null, null, [], [], 'Tecnico', ['financeiro.write']);

        self::assertTrue($policy->allows($context, 'financeiro', 'write'));
        self::assertFalse($policy->allows($context, 'associados', 'read'));
    }

    public function testAdminScopeIsGlobalEvenWithLegacyIds(): void
    {
        $context = new ContextoUsuarioFake(['admin'], 13, 13, [], [], 'Admin', ['*']);
        $scope = \App\Kernel\Security\AccessScope::fromContext($context);

        self::assertTrue($scope->isGlobal());
        self::assertTrue($scope->allowsAssociado(7, 1));
    }

    public function testTenantScope(): void
    {
        $global = new TenantScope(null);
        self::assertTrue($global->allowsCertificadora(99));
        self::assertTrue($global->allowsAssociation(99));

        $scoped = new TenantScope(7, [10, 11]);
        self::assertTrue($scoped->allowsCertificadora(7));
        self::assertFalse($scoped->allowsCertificadora(8));
        self::assertTrue($scoped->allowsAssociation(10));
        self::assertFalse($scoped->allowsAssociation(12));
    }

    public function testCsrfToken(): void
    {
        $token = CsrfToken::generate();

        self::assertTrue(CsrfToken::isValid($token, $token));
        self::assertFalse(CsrfToken::isValid($token, 'outro'));
        self::assertFalse(CsrfToken::isValid($token, null));
    }

    public function testUploadAcceptsAllowedMime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sinporg_up_');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'conteudo de teste');

        try {
            (new UploadValidator(1024, ['text/plain'], false))->validate([
                'error' => UPLOAD_ERR_OK,
                'size' => 17,
                'tmp_name' => $tmp,
            ]);
            self::assertTrue(true);
        } finally {
            @unlink($tmp);
        }
    }

    public function testUploadRejectsMimeAndSize(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sinporg_up_');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'conteudo de teste');

        try {
            $this->expectException(InvalidArgumentException::class);
            (new UploadValidator(10, ['image/png'], false))->validate([
                'error' => UPLOAD_ERR_OK,
                'size' => 17,
                'tmp_name' => $tmp,
            ]);
        } finally {
            @unlink($tmp);
        }
    }

    public function testAuthMiddlewareRejectsAnonymousAndAcceptsContext(): void
    {
        $anonymous = new AuthMiddleware(null);
        $response = $anonymous->process(
            Request::create('GET', '/associados'),
            static fn (Request $request): Response => Response::json(['ok' => true])
        );
        self::assertSame(401, $response->status);

        $authenticated = new AuthMiddleware(new ContextoUsuarioFake(['tecnico']));
        $response = $authenticated->process(
            Request::create('GET', '/associados'),
            static fn (Request $request): Response => Response::json(['ok' => true])
        );
        self::assertSame(200, $response->status);
    }
}
