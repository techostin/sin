<?php

declare(strict_types=1);

namespace App\Kernel\Http;

final class Pipeline
{
    /** @param list<MiddlewareInterface> $middlewares */
    public function __construct(private readonly array $middlewares = [])
    {
    }

    /**
     * @param callable(Request): Response $destination
     */
    public function process(Request $request, callable $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = static fn (Request $current): Response => $middleware->process($current, $next);
        }

        return $next($request);
    }
}
