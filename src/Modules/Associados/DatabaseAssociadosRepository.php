<?php

declare(strict_types=1);

namespace App\Modules\Associados;

use App\Kernel\Persistence\Repository;
use PDO;

final class DatabaseAssociadosRepository extends Repository implements AssociadosRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, ?string $status, ?string $q): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }
        if ($q !== null && $q !== '') {
            $where[] = "(CONCAT(a.nome, ' ', a.sobrenome) LIKE :q_nome OR a.email LIKE :q_email)";
            $params['q_nome'] = '%' . $q . '%';
            $params['q_email'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);
        $countRow = $this->fetchOne('SELECT COUNT(*) AS total FROM associados a WHERE ' . $whereSql, $params);
        $total = (int) ($countRow['total'] ?? 0);

        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT a.id, a.uuid, a.nome, a.sobrenome, a.cpf, a.email, a.status,
                       a.associacao_id, a.microrregiao_id, ass.nome AS associacao_nome
                FROM associados a
                LEFT JOIN associacoes ass ON ass.id = a.associacao_id
                WHERE ' . $whereSql . '
                ORDER BY a.nome, a.sobrenome
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return ['items' => $this->fetchAll($sql, $params), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT a.id, a.uuid, a.nome, a.sobrenome, a.cpf, a.email, a.status,
                    a.registro_cnpo, a.associacao_id, a.microrregiao_id,
                    ass.nome AS associacao_nome
             FROM associados a
             LEFT JOIN associacoes ass ON ass.id = a.associacao_id
             WHERE a.uuid = :uuid LIMIT 1',
            ['uuid' => $uuid]
        );
    }
}
