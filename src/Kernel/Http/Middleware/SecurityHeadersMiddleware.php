<?php

declare(strict_types=1);

namespace App\Kernel\Http\Middleware;

use App\Kernel\Http\MiddlewareInterface;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

/**
 * Cabeçalhos de segurança da API. O Apache também os aplica; aqui garantimos
 * que respostas diretas do front controller não dependam só do .htaccess.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
