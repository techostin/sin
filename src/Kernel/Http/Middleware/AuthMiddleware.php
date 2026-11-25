<?php

declare(strict_types=1);

namespace App\Kernel\Http\Middleware;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\MiddlewareInterface;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

/**
 * Exige contexto autenticado. A resolução do contexto (sessão/token) é da K1.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ?ContextoUsuario $context)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->context === null) {
            return Response::error('NAO_AUTENTICADO', 'Autenticacao necessaria.', 401, $request->requestId);
        }

        return $next($request);
    }
}
