<?php

declare(strict_types=1);

namespace App\Modules\Governanca;

use App\Kernel\Persistence\Repository;
use PDO;
use RuntimeException;

final class DatabaseGovernancaRepository extends Repository implements GovernancaRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createEvent(array $data, int $userId): array
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO eventos (nome, descricao, data_evento, local, status)
             VALUES (:nome, :descricao, :data_evento, :local, :status)'
        );
        $insert->execute([
            'nome' => (string) ($data['nome'] ?? ''),
            'descricao' => $data['descricao'] ?? null,
            'data_evento' => (string) ($data['data_evento'] ?? date('Y-m-d H:i:s')),
            'local' => $data['local'] ?? null,
            'status' => (string) ($data['status'] ?? 'Agendado'),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $event = $this->fetchOne('SELECT * FROM eventos WHERE id = :id LIMIT 1', ['id' => $id]);
        if ($event === null) {
            throw new RuntimeException('Evento criado nao encontrado.');
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createAssembly(array $data, int $userId): array
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO assembleias
                (titulo, descricao, data_inicio, data_fim, associacao_id, status,
                 tipo_votacao, quorum_minimo, created_by)
             VALUES (:titulo, :descricao, :data_inicio, :data_fim, :associacao_id, :status,
                     :tipo_votacao, :quorum, :created_by)'
        );
        $insert->execute([
            'titulo' => (string) ($data['titulo'] ?? ''),
            'descricao' => $data['descricao'] ?? null,
            'data_inicio' => (string) ($data['data_inicio'] ?? date('Y-m-d H:i:s')),
            'data_fim' => (string) ($data['data_fim'] ?? date('Y-m-d H:i:s')),
            'associacao_id' => $data['associacao_id'] ?? null,
            'status' => 'rascunho',
            'tipo_votacao' => 'simples',
            'quorum' => $data['quorum_minimo'] ?? 50,
            'created_by' => $userId,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $assembly = $this->fetchOne('SELECT * FROM assembleias WHERE id = :id LIMIT 1', ['id' => $id]);
        if ($assembly === null) {
            throw new RuntimeException('Assembleia criada nao encontrada.');
        }

        return $assembly;
    }

    /** @return list<array<string, mixed>> */
    public function listEvents(): array
    {
        return $this->fetchAll('SELECT * FROM eventos ORDER BY data_evento DESC');
    }

    /** @return list<array<string, mixed>> */
    public function listAssemblies(): array
    {
        return $this->fetchAll('SELECT * FROM assembleias ORDER BY data_inicio DESC');
    }
}
