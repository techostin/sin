<?php

declare(strict_types=1);

namespace App\Modules\Financeiro;

use App\Kernel\Persistence\Repository;
use PDO;
use RuntimeException;

final class DatabaseFinanceiroRepository extends Repository implements FinanceiroRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createMensalidade(array $data): array
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO mensalidades
                (associado_id, associacao_id, plano_id, fonte, ano_referencia, mes_referencia,
                 valor_cobrado, data_vencimento, status_mensalidade, observacoes)
             VALUES (:associado_id, :associacao_id, :plano_id, :fonte, :ano, :mes,
                     :valor, :vencimento, :status, :observacoes)'
        );
        $insert->execute([
            'associado_id' => (int) ($data['associado_id'] ?? 0),
            'associacao_id' => $data['associacao_id'] ?? null,
            'plano_id' => $data['plano_id'] ?? null,
            'fonte' => 'manual',
            'ano' => (int) ($data['ano_referencia'] ?? date('Y')),
            'mes' => (int) ($data['mes_referencia'] ?? date('n')),
            'valor' => $data['valor_cobrado'] ?? 0,
            'vencimento' => (string) ($data['data_vencimento'] ?? date('Y-m-d')),
            'status' => 'pendente',
            'observacoes' => $data['observacoes'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findMensalidade($id);
        if ($row === null) {
            throw new RuntimeException('Mensalidade criada nao encontrada.');
        }

        return $row;
    }

    /**
     * @param list<int> $associationIds
     * @return list<array<string, mixed>>
     */
    public function listMensalidades(?int $associadoId, array $associationIds): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($associadoId !== null) {
            $where[] = 'associado_id = :associado_id';
            $params['associado_id'] = $associadoId;
        } elseif ($associationIds !== []) {
            $placeholders = [];
            foreach ($associationIds as $index => $id) {
                $key = 'assoc_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $where[] = 'associacao_id IN (' . implode(', ', $placeholders) . ')';
        }

        return $this->fetchAll(
            'SELECT * FROM mensalidades WHERE ' . implode(' AND ', $where) . ' ORDER BY data_vencimento DESC',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findMensalidade(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM mensalidades WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function pay(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE mensalidades
             SET status_mensalidade = 'pago', data_pagamento = CURDATE()
             WHERE id = :id AND status_mensalidade IN ('pendente', 'atrasado')"
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
