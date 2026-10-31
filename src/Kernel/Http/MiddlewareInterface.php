<?php

declare(strict_types=1);

namespace App\Kernel\Http;

/**
 * Middleware do pipeline HTTP. Recebe a requisição e o próximo passo.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
