<?php

declare(strict_types=1);

namespace App\Modules\Fiscalizacao;

use App\Kernel\Contracts\AuditLogger;
use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Documents\CertificateGeneratorInterface;
use App\Kernel\Documents\CertificateSigner;
use App\Kernel\Documents\DocumentNumberGeneratorInterface;
use App\Kernel\Security\AccessScope;
use App\Kernel\Security\UploadValidator;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

final class FiscalizacaoService
{
    private readonly UploadValidator $uploads;
    private readonly string $evidenceDirectory;

    public function __construct(
        private readonly FiscalizacaoRepositoryInterface $repository,
        private readonly RastreabilidadeRepositoryInterface $traceability,
        private readonly DocumentNumberGeneratorInterface $numbers,
        private readonly AuditLogger $audit,
        private readonly ?CertificateGeneratorInterface $pdf = null,
        private readonly ?CertificateSigner $signer = null,
        ?UploadValidator $uploads = null,
        ?string $evidenceDirectory = null,
    ) {
        $this->uploads = $uploads ?? new UploadValidator();
        $this->evidenceDirectory = $evidenceDirectory ?? dirname(__DIR__, 3) . '/.evidencias';
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
        $emitidoEm = date('d/m/Y H:i');
        $payload = [
            'visita_uuid' => $uuid,
            'lote_uuid' => $lotUuid,
            'lote_codigo' => $lot['codigo_publico'] ?? null,
            'eventos' => count($lot['eventos'] ?? []),
            'associado_nome' => trim((string) ($visit['associado_nome'] ?? '') . ' '
                . (string) ($visit['associado_sobrenome'] ?? '')),
            'emitido_em' => $emitidoEm,
            'emitido_por_nome' => $context->name(),
            'verificacao_url' => (defined('SITE_URL') ? (string) constant('SITE_URL') : '')
                . '/api/v1/documentos/' . $number . '/pdf',
        ];

        $filePath = null;
        $fileHash = null;
        $signature = null;
        if ($this->pdf !== null) {
            $filePath = $this->pdf->generate($number, $payload);
            $fileHash = hash_file('sha256', $filePath);
            if ($fileHash === false) {
                throw new RuntimeException('Falha ao calcular hash do certificado.');
            }
            if ($this->signer !== null) {
                $signature = $this->signer->sign($payload, $fileHash);
            }
        }

        $document = $this->repository->issueDocument([
            'tipo' => 'certificado_organico',
            'numero' => $number,
            'associado_id' => (int) $visit['associado_id'],
            'visita_id' => (int) $visit['id'],
            'lote_uuid' => $lotUuid,
            'emitido_por' => (int) $context->userId(),
            'payload' => $payload,
            'arquivo' => $filePath,
            'hash_arquivo' => $fileHash,
            'assinatura_hash' => $signature,
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

    /**
     * @param array{error?: int, size?: int, tmp_name?: string, name?: string} $file
     * @return array<string, mixed>
     */
    public function storeEvidence(ContextoUsuario $context, string $visitUuid, array $file): array
    {
        $this->findVisit($context, $visitUuid);
        if ($file === [] || !isset($file['tmp_name'])) {
            throw new InvalidArgumentException('Arquivo de evidencia obrigatorio.');
        }

        $this->uploads->validate($file);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new InvalidArgumentException('Tipo de evidencia nao permitido.');
        }

        $uuid = $this->uuid();
        $directory = $this->evidenceDirectory . DIRECTORY_SEPARATOR . $visitUuid;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Diretorio de evidencias indisponivel.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . $uuid . '.' . $extensions[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
            throw new RuntimeException('Falha ao armazenar a evidencia.');
        }

        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Falha ao calcular hash da evidencia.');
        }

        $evidence = $this->repository->addEvidence($visitUuid, [
            'nome_arquivo' => mb_substr((string) ($file['name'] ?? 'evidencia'), 0, 255),
            'mime' => $mime,
            'tamanho' => (int) ($file['size'] ?? 0),
            'hash_arquivo' => $hash,
            'arquivo' => $path,
        ], (int) $context->userId());
        if ($evidence === null) {
            throw new InvalidArgumentException('Visita nao encontrada.');
        }

        $this->audit->record('anexar_evidencia', 'fiscalizacao_evidencias', (string) ($evidence['uuid'] ?? ''), [
            'visita' => $visitUuid,
        ]);

        return $evidence;
    }

    /** @return list<array<string, mixed>> */
    public function listEvidence(ContextoUsuario $context, string $visitUuid): array
    {
        $this->findVisit($context, $visitUuid);

        return $this->repository->listEvidence($visitUuid);
    }

    /** @return array<string, mixed> */
    public function findEvidence(ContextoUsuario $context, string $uuid): array
    {
        $evidence = $this->repository->findEvidence($uuid);
        if ($evidence === null) {
            throw new InvalidArgumentException('Evidencia nao encontrada.');
        }
        $this->findVisit($context, (string) ($evidence['visita_uuid'] ?? ''));

        return $evidence;
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

    /** @param array<string, mixed> $visit */
    private function canAccessVisit(array $visit, ContextoUsuario $context): bool
    {
        return AccessScope::fromContext($context)->allowsAssociado(
            isset($visit['associado_id']) ? (int) $visit['associado_id'] : null,
            isset($visit['associado_associacao_id']) ? (int) $visit['associado_associacao_id'] : null
        );
    }
}
