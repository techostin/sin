<?php

declare(strict_types=1);

namespace App\Modules\Fiscalizacao;

use App\Kernel\Persistence\Repository;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseFiscalizacaoRepository extends Repository implements FiscalizacaoRepositoryInterface
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVisit(array $data, int $userId): array
    {
        $uuid = $this->uuid();
        $insert = $this->pdo->prepare(
            'INSERT INTO fiscalizacao_visitas
                (uuid, associado_id, propriedade_id, fiscal_id, data_agendada, status, observacoes)
             VALUES (:uuid, :associado_id, :propriedade_id, :fiscal_id, :data_agendada, :status, :observacoes)'
        );
        $insert->execute([
            'uuid' => $uuid,
            'associado_id' => (int) ($data['associado_id'] ?? 0),
            'propriedade_id' => isset($data['propriedade_id']) && is_numeric($data['propriedade_id'])
                ? (int) $data['propriedade_id'] : null,
            'fiscal_id' => $userId,
            'data_agendada' => $data['data_agendada'] ?? null,
            'status' => 'agendada',
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        $created = $this->findVisit($uuid);
        if ($created === null) {
            throw new RuntimeException('Visita criada nao encontrada.');
        }

        return $created;
    }

    /** @param list<int> $associationIds @return list<array<string, mixed>> */
    public function listVisits(?int $associadoId, array $associationIds): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($associadoId !== null) {
            $where[] = 'v.associado_id = :associado_id';
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

        return $this->fetchAll(
            'SELECT v.*, a.associacao_id AS associado_associacao_id
             FROM fiscalizacao_visitas v
             LEFT JOIN associados a ON a.id = v.associado_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY v.created_at DESC',
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findVisit(string $uuid): ?array
    {
        return $this->fetchOne(
            'SELECT v.*, a.associacao_id AS associado_associacao_id
             FROM fiscalizacao_visitas v
             LEFT JOIN associados a ON a.id = v.associado_id
             WHERE v.uuid = :uuid LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function addNonConformity(string $uuid, array $data, int $userId): ?array
    {
        $visit = $this->findVisit($uuid);
        if ($visit === null) {
            return null;
        }

        $ncUuid = $this->uuid();
        $insert = $this->pdo->prepare(
            'INSERT INTO fiscalizacao_nao_conformidades
                (uuid, visita_id, descricao, gravidade, prazo, status, created_by)
             VALUES (:uuid, :visita_id, :descricao, :gravidade, :prazo, :status, :created_by)'
        );
        $insert->execute([
            'uuid' => $ncUuid,
            'visita_id' => (int) $visit['id'],
            'descricao' => mb_substr((string) ($data['descricao'] ?? ''), 0, 500),
            'gravidade' => (string) ($data['gravidade'] ?? 'media'),
            'prazo' => $data['prazo'] ?? null,
            'status' => 'aberta',
            'created_by' => $userId,
        ]);

        return $this->fetchOne(
            'SELECT * FROM fiscalizacao_nao_conformidades WHERE uuid = :uuid LIMIT 1',
            ['uuid' => $ncUuid]
        );
    }

    public function review(string $uuid, string $decision, ?string $notes, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE fiscalizacao_visitas
             SET status = :status, observacoes = COALESCE(:notes, observacoes)
             WHERE uuid = :uuid AND status IN (\'concluida\', \'em_andamento\')'
        );
        $statement->execute(['status' => $decision, 'notes' => $notes, 'uuid' => $uuid]);

        return $statement->rowCount() > 0;
    }

    public function updateStatus(string $uuid, string $status, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE fiscalizacao_visitas
             SET status = :status
             WHERE uuid = :uuid AND status IN (\'agendada\', \'em_andamento\')'
        );
        $statement->execute(['status' => $status, 'uuid' => $uuid]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function issueDocument(array $data): array
    {
        $uuid = $this->uuid();
        $payload = json_encode($data['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insert = $this->pdo->prepare(
            'INSERT INTO documentos_emitidos
                (uuid, tipo, numero, associado_id, visita_id, lote_uuid, status,
                 payload_json, emitido_por, emitido_em)
             VALUES (:uuid, :tipo, :numero, :associado_id, :visita_id, :lote_uuid, :status,
                     :payload, :emitido_por, NOW())'
        );
        $insert->execute([
            'uuid' => $uuid,
            'tipo' => (string) ($data['tipo'] ?? 'certificado_organico'),
            'numero' => (string) ($data['numero'] ?? ''),
            'associado_id' => $data['associado_id'] ?? null,
            'visita_id' => $data['visita_id'] ?? null,
            'lote_uuid' => $data['lote_uuid'] ?? null,
            'status' => 'emitido',
            'payload' => $payload === false ? '{}' : $payload,
            'emitido_por' => $data['emitido_por'] ?? null,
        ]);

        $document = $this->findDocumentByNumber((string) ($data['numero'] ?? ''));
        if ($document === null) {
            throw new RuntimeException('Documento emitido nao encontrado.');
        }

        return $document;
    }

    /** @return array<string, mixed>|null */
    public function findDocumentByNumber(string $number): ?array
    {
        return $this->fetchOne(
            'SELECT id, uuid, tipo, numero, associado_id, lote_uuid, status, emitido_em, created_at
             FROM documentos_emitidos WHERE numero = :numero LIMIT 1',
            ['numero' => $number]
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
