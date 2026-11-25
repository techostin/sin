<?php

declare(strict_types=1);

namespace App\Kernel\Persistence;

use PDO;
use Throwable;

final class UnitOfWork
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
