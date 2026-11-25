<?php

declare(strict_types=1);

namespace App\Kernel\Http;

use RuntimeException;

/**
 * Roteador simples da API modular. Cada módulo registra suas rotas em routes/.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            $regex = $this->compile($route['pattern']);
            $matched = preg_match($regex, $request->path, $matches);

            if ($route['method'] !== $request->method) {
                if ($matched === 1) {
                    $allowed[$route['method']] = true;
                }
                continue;
            }

            if ($matched !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $name => $value) {
                if (is_int($name)) {
                    continue;
                }
                $params[$name] = (string) $value;
            }

            $result = ($route['handler'])($request, $params);
            if ($result instanceof Response) {
                return $result;
            }

            if (is_array($result)) {
                return Response::json($result, 200, ['requestId' => $request->requestId]);
            }

            throw new RuntimeException('Handler nao retornou Response nem array.');
        }

        if ($allowed !== []) {
            return Response::error('METODO_NAO_PERMITIDO', 'Metodo nao permitido.', 405, $request->requestId);
        }

        return Response::error('NAO_ENCONTRADO', 'Recurso nao encontrado.', 404, $request->requestId);
    }

    private function compile(string $pattern): string
    {
        $parts = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if ($parts === false) {
            return '#^' . preg_quote($pattern, '#') . '$#';
        }

        $regex = '#^';
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $name) === 1) {
                $regex .= '(?P<' . $name[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return $regex . '$#';
    }
}
