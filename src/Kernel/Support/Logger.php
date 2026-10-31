<?php

declare(strict_types=1);

namespace App\Kernel\Support;

/**
 * Logger simples (linha JSON) para a API. Não registra corpo nem dados pessoais.
 */
final class Logger
{
    public function __construct(private readonly string $file)
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        $line = sprintf("[%s] %s %s %s\n", date('c'), $level, $message, $json);
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
