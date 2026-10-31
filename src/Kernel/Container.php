<?php

declare(strict_types=1);

namespace App\Kernel;

use RuntimeException;

/**
 * Contêiner mínimo de dependências (PSR-11 inspirado).
 */
final class Container
{
    /** @var array<string, callable(self): object> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @param callable(self): object $factory */
    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new RuntimeException('Servico nao registrado: ' . $id);
        }

        $instance = ($this->bindings[$id])($this);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
