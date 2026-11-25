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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(array $data): array
    {
        $uuid = $this->uuid();
        $insert = $this->pdo->prepare(
            'INSERT INTO associados
                (uuid, nome, sobrenome, cpf, email, status, associacao_id,
                 microrregiao_id, nivel, registro_cnpo)
             VALUES (:uuid, :nome, :sobrenome, :cpf, :email, :status, :associacao_id,
                     :microrregiao_id, 3, :registro_cnpo)'
        );
        $insert->execute([
            'uuid' => $uuid,
            'nome' => (string) ($data['nome'] ?? ''),
            'sobrenome' => (string) ($data['sobrenome'] ?? ''),
            'cpf' => isset($data['cpf']) && $data['cpf'] !== '' ? (string) $data['cpf'] : null,
            'email' => isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            'status' => 'pendente',
            'associacao_id' => isset($data['associacao_id']) && is_numeric($data['associacao_id'])
                ? (int) $data['associacao_id'] : null,
            'microrregiao_id' => isset($data['microrregiao_id']) && is_numeric($data['microrregiao_id'])
                ? (int) $data['microrregiao_id'] : null,
            'registro_cnpo' => isset($data['registro_cnpo']) && is_numeric($data['registro_cnpo'])
                ? (int) $data['registro_cnpo'] : null,
        ]);

        $created = $this->findByUuid($uuid);
        if ($created === null) {
            throw new \RuntimeException('Associado criado nao encontrado.');
        }

        return $created;
    }

    public function approve(string $uuid): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE associados SET status = 'ativo' WHERE uuid = :uuid AND status = 'pendente'"
        );
        $statement->execute(['uuid' => $uuid]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param list<int> $associationIds
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(
        int $page,
        int $perPage,
        ?string $status,
        ?string $q,
        ?int $associadoId = null,
        array $associationIds = [],
    ): array {
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
        if ($associadoId !== null) {
            $where[] = 'a.id = :associado_id';
            $params['associado_id'] = $associadoId;
        } elseif ($associationIds !== []) {
            $placeholders = [];
            foreach ($associationIds as $index => $associationId) {
                $key = 'assoc_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $associationId;
            }
            $where[] = 'a.associacao_id IN (' . implode(', ', $placeholders) . ')';
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

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
