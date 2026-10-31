<?php

declare(strict_types=1);

namespace App\Kernel\Http;

/**
 * Requisição HTTP normalizada. Imutável após a criação.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $requestId,
        public readonly string $body = '',
    ) {
    }

    public static function fromGlobals(?string $basePath = null): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';

        $base = $basePath;
        if ($base === null) {
            $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
            $base = $script !== '' ? str_replace('\\', '/', dirname($script)) : '';
        }
        $base = rtrim($base, '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;

        /** @var array<string, string> $headers */
        $headers = [];
        if (function_exists('getallheaders')) {
            /** @var array<string, string> $raw */
            $raw = getallheaders();
            foreach ($raw as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }

        $body = (string) @file_get_contents('php://input');

        return new self($method, $path, $query, $headers, self::resolveRequestId($headers), $body);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?string $requestId = null,
    ): self {
        $normalized = '/' . trim($path, '/');
        if ($normalized === '/') {
            $normalized = '/';
        }

        return new self(
            strtoupper($method),
            $normalized,
            $query,
            $headers,
            $requestId ?? self::resolveRequestId($headers),
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @param array<string, string> $headers */
    private static function resolveRequestId(array $headers): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'X-Request-Id') !== 0) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9._-]{8,64}$/', $value) === 1) {
                return $value;
            }
        }

        return bin2hex(random_bytes(8));
    }
}
