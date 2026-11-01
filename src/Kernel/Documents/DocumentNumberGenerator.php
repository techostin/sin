<?php

declare(strict_types=1);

namespace App\Kernel\Documents;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Numeração sequencial por tipo/ano, com transação e bloqueio no MySQL.
 */
final class DocumentNumberGenerator
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function next(string $type, int $year): string
    {
        if (preg_match('/^[A-Z0-9_-]{1,20}$/', $type) !== 1) {
            throw new InvalidArgumentException('Tipo de documento invalido.');
        }
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Ano invalido.');
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->pdo->beginTransaction();

        try {
            $sql = 'SELECT ultimo_numero FROM document_sequences WHERE tipo = :tipo AND ano = :ano';
            if ($driver === 'mysql') {
                $sql .= ' FOR UPDATE';
            }
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['tipo' => $type, 'ano' => $year]);
            $current = $statement->fetchColumn();

            if ($current === false) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO document_sequences (tipo, ano, ultimo_numero) VALUES (:tipo, :ano, 1)'
                );
                $insert->execute(['tipo' => $type, 'ano' => $year]);
                $number = 1;
            } else {
                $number = (int) $current + 1;
                $update = $this->pdo->prepare(
                    'UPDATE document_sequences SET ultimo_numero = :numero WHERE tipo = :tipo AND ano = :ano'
                );
                $update->execute(['numero' => $number, 'tipo' => $type, 'ano' => $year]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return sprintf('%s-%d-%06d', $type, $year, $number);
    }
}
