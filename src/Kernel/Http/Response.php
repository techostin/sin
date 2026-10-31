<?php

declare(strict_types=1);

namespace App\Kernel\Http;

use JsonException;

/**
 * Resposta HTTP com envelope JSON padrão da API v1.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException
     */
    public static function json(array $data, int $status = 200, array $meta = []): self
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], $body);
    }

    /**
     * @throws JsonException
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        ?string $requestId = null,
    ): self {
        $error = ['code' => $code, 'message' => $message];
        if ($requestId !== null && $requestId !== '') {
            $error['requestId'] = $requestId;
        }

        $body = json_encode(
            ['error' => $error],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], $body);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->status, $headers, $this->body);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
