<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Modules\Fiscalizacao\FiscalizacaoRepositoryInterface;

final class FakeFiscalizacaoRepository implements FiscalizacaoRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $visits = [];
    /** @var array<string, array<string, mixed>> */
    public array $documents = [];

    public function __construct()
    {
        $this->visits['11111111-1111-4111-8111-111111111111'] = [
            'id' => 1,
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'associado_id' => 7,
            'associado_associacao_id' => 1,
            'fiscal_id' => 7,
            'status' => 'agendada',
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function createVisit(array $data, int $userId): array
    {
        $uuid = '22222222-2222-4222-8222-222222222222';
        $this->visits[$uuid] = array_merge($data, [
            'id' => 2,
            'uuid' => $uuid,
            'associado_associacao_id' => 1,
            'fiscal_id' => $userId,
            'status' => 'agendada',
        ]);

        return $this->visits[$uuid];
    }

    /** @param list<int> $associationIds @return list<array<string, mixed>> */
    public function listVisits(?int $associadoId, array $associationIds): array
    {
        return array_values($this->visits);
    }

    /** @return array<string, mixed>|null */
    public function findVisit(string $uuid): ?array
    {
        return $this->visits[$uuid] ?? null;
    }

    /** @param array<string, mixed> $data @return array<string, mixed>|null */
    public function addNonConformity(string $uuid, array $data, int $userId): ?array
    {
        return [
            'id' => 1,
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'visita_id' => 1,
            'descricao' => $data['descricao'] ?? '',
            'gravidade' => $data['gravidade'] ?? 'media',
        ];
    }

    public function review(string $uuid, string $decision, ?string $notes, int $userId): bool
    {
        if (!isset($this->visits[$uuid])) {
            return false;
        }
        $this->visits[$uuid]['status'] = $decision;

        return true;
    }

    public function updateStatus(string $uuid, string $status, int $userId): bool
    {
        if (!isset($this->visits[$uuid])) {
            return false;
        }
        $this->visits[$uuid]['status'] = $status;

        return true;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function issueDocument(array $data): array
    {
        $document = [
            'id' => 1,
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'tipo' => $data['tipo'] ?? 'certificado_organico',
            'numero' => $data['numero'] ?? '',
            'associado_id' => $data['associado_id'] ?? null,
            'lote_uuid' => $data['lote_uuid'] ?? null,
            'status' => 'emitido',
            'emitido_em' => date('Y-m-d H:i:s'),
        ];
        $this->documents[(string) $document['numero']] = $document;

        return $document;
    }

    /** @return array<string, mixed>|null */
    public function findDocumentByNumber(string $number): ?array
    {
        return $this->documents[$number] ?? null;
    }
}
