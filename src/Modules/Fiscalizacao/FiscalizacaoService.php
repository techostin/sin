<?php

declare(strict_types=1);

namespace App\Modules\Fiscalizacao;

use App\Kernel\Contracts\AuditLogger;
use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Documents\DocumentNumberGeneratorInterface;
use App\Kernel\Security\AccessScope;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;
use InvalidArgumentException;

final class FiscalizacaoService
{
    public function __construct(
        private readonly FiscalizacaoRepositoryInterface $repository,
        private readonly RastreabilidadeRepositoryInterface $traceability,
        private readonly DocumentNumberGeneratorInterface $numbers,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVisit(ContextoUsuario $context, array $data): array
    {
        $associadoId = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : $context->associadoId();
        if ($associadoId === null) {
            throw new InvalidArgumentException('associado_id obrigatorio.');
        }

        return $this->repository->createVisit([
            'associado_id' => $associadoId,
            'propriedade_id' => $data['propriedade_id'] ?? null,
            'data_agendada' => $data['data_agendada'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
        ], (int) $context->userId());
    }

    /** @return list<array<string, mixed>> */
    public function listVisits(ContextoUsuario $context): array
    {
        $scope = AccessScope::fromContext($context);
        if ($scope->isGlobal()) {
            return $this->repository->listVisits(null, []);
        }
        if ($scope->associadoId() !== null) {
            return $this->repository->listVisits($scope->associadoId(), []);
        }

        return $this->repository->listVisits(null, $scope->associationIds());
    }

    /** @return array<string, mixed> */
    public function findVisit(ContextoUsuario $context, string $uuid): array
    {
        $visit = $this->repository->findVisit($uuid);
        if ($visit === null || !$this->canAccessVisit($visit, $context)) {
            throw new InvalidArgumentException('Visita nao encontrada.');
        }

        return $visit;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addNonConformity(ContextoUsuario $context, string $uuid, array $data): array
    {
        $visit = $this->findVisit($context, $uuid);
        if (($visit['status'] ?? '') === 'aprovada') {
            throw new InvalidArgumentException('Visita ja aprovada.');
        }
        if (!isset($data['descricao']) || !is_string($data['descricao']) || trim($data['descricao']) === '') {
            throw new InvalidArgumentException('descricao obrigatoria.');
        }

        $nc = $this->repository->addNonConformity($uuid, $data, (int) $context->userId());
        if ($nc === null) {
            throw new InvalidArgumentException('Visita nao encontrada.');
        }

        $this->audit->record('criar_nc', 'fiscalizacao_nao_conformidades', (string) ($nc['id'] ?? ''), [
            'visita' => $uuid,
        ]);

        return $nc;
    }

    public function review(ContextoUsuario $context, string $uuid, string $decision, ?string $notes): bool
    {
        $visit = $this->findVisit($context, $uuid);
        if (!in_array((string) ($visit['status'] ?? ''), ['concluida', 'em_andamento'], true)) {
            throw new InvalidArgumentException('Visita nao esta em revisao.');
        }
        if (!in_array($decision, ['aprovada', 'recusada'], true)) {
            throw new InvalidArgumentException('Decisao invalida.');
        }

        $updated = $this->repository->review($uuid, $decision, $notes, (int) $context->userId());
        if ($updated) {
            $this->audit->record('revisar_visita', 'fiscalizacao_visitas', $uuid, [
                'decision' => $decision,
            ]);
        }

        return $updated;
    }

    public function updateStatus(ContextoUsuario $context, string $uuid, string $status): bool
    {
        $visit = $this->findVisit($context, $uuid);
        $current = (string) ($visit['status'] ?? '');
        $transitions = [
            'agendada' => ['em_andamento'],
            'em_andamento' => ['concluida'],
        ];
        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new InvalidArgumentException('Transicao de status invalida.');
        }

        $updated = $this->repository->updateStatus($uuid, $status, (int) $context->userId());
        if ($updated) {
            $this->audit->record('status_visita', 'fiscalizacao_visitas', $uuid, ['status' => $status]);
        }

        return $updated;
    }

    /** @return array<string, mixed> */
    public function issueCertificate(ContextoUsuario $context, string $uuid, string $lotUuid): array
    {
        $visit = $this->findVisit($context, $uuid);
        if ((string) ($visit['status'] ?? '') !== 'aprovada') {
            throw new InvalidArgumentException('Visita precisa estar aprovada.');
        }

        $lot = $this->traceability->findByUuid($lotUuid);
        if ($lot === null || (string) ($lot['status'] ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Lote rastreavel inexistente ou inativo.');
        }
        if (($lot['eventos'] ?? []) === []) {
            throw new InvalidArgumentException('Lote sem eventos de cadeia.');
        }
        if (
            isset($lot['associado_id'])
            && is_numeric($lot['associado_id'])
            && (int) $lot['associado_id'] !== (int) $visit['associado_id']
        ) {
            throw new InvalidArgumentException('Lote nao pertence ao associado da visita.');
        }

        $number = $this->numbers->next('CERT', (int) date('Y'));
        $document = $this->repository->issueDocument([
            'tipo' => 'certificado_organico',
            'numero' => $number,
            'associado_id' => (int) $visit['associado_id'],
            'visita_id' => (int) $visit['id'],
            'lote_uuid' => $lotUuid,
            'emitido_por' => (int) $context->userId(),
            'payload' => [
                'visita_uuid' => $uuid,
                'lote_uuid' => $lotUuid,
                'lote_codigo' => $lot['codigo_publico'] ?? null,
                'eventos' => count($lot['eventos'] ?? []),
                'emitido_em' => date(DATE_ATOM),
            ],
        ]);

        $this->audit->record('emitir_certificado', 'documentos_emitidos', $number, [
            'visita' => $uuid,
            'lote' => $lotUuid,
        ]);

        return $document;
    }

    /** @return array<string, mixed>|null */
    public function verifyDocument(string $number): ?array
    {
        return $this->repository->findDocumentByNumber($number);
    }

    /** @param array<string, mixed> $visit */
    private function canAccessVisit(array $visit, ContextoUsuario $context): bool
    {
        return AccessScope::fromContext($context)->allowsAssociado(
            isset($visit['associado_id']) ? (int) $visit['associado_id'] : null,
            isset($visit['associado_associacao_id']) ? (int) $visit['associado_associacao_id'] : null
        );
    }
}
