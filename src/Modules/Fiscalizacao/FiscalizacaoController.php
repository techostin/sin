<?php

declare(strict_types=1);

namespace App\Modules\Fiscalizacao;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use InvalidArgumentException;

final class FiscalizacaoController
{
    public function __construct(private readonly FiscalizacaoService $service)
    {
    }

    public function list(Request $request, ContextoUsuario $context): Response
    {
        return Response::json($this->service->listVisits($context), 200, ['requestId' => $request->requestId]);
    }

    public function create(Request $request, ContextoUsuario $context): Response
    {
        try {
            $visit = $this->service->createVisit($context, $request->json());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json($visit, 201, ['requestId' => $request->requestId]);
    }

    public function show(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        try {
            $visit = $this->service->findVisit($context, $uuid);
        } catch (InvalidArgumentException $exception) {
            return $this->notFound($request);
        }

        return Response::json($visit, 200, ['requestId' => $request->requestId]);
    }

    public function addNonConformity(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        try {
            $nc = $this->service->addNonConformity($context, $uuid, $request->json());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json($nc, 201, ['requestId' => $request->requestId]);
    }

    public function review(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $decision = isset($data['decision']) && is_string($data['decision']) ? $data['decision'] : '';
        $notes = isset($data['observacoes']) && is_string($data['observacoes']) ? $data['observacoes'] : null;

        try {
            if (!$this->service->review($context, $uuid, $decision, $notes)) {
                return $this->notFound($request);
            }
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json(['uuid' => $uuid, 'status' => $decision], 200, [
            'requestId' => $request->requestId,
        ]);
    }

    public function status(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : '';

        try {
            if (!$this->service->updateStatus($context, $uuid, $status)) {
                return $this->notFound($request);
            }
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json(['uuid' => $uuid, 'status' => $status], 200, [
            'requestId' => $request->requestId,
        ]);
    }

    public function certificate(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $lotUuid = isset($data['lote_uuid']) && is_string($data['lote_uuid']) ? $data['lote_uuid'] : '';
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $lotUuid) !== 1) {
            return $this->invalid($request, 'lote_uuid invalido.');
        }

        try {
            $document = $this->service->issueCertificate($context, $uuid, $lotUuid);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json($document, 201, ['requestId' => $request->requestId]);
    }

    public function verify(Request $request, string $number): Response
    {
        if (preg_match('/^CERT-\d{4}-\d{6}$/', $number) !== 1) {
            return $this->notFound($request);
        }

        $document = $this->service->verifyDocument($number);
        if ($document === null) {
            return $this->notFound($request);
        }

        return Response::json([
            'numero' => $document['numero'],
            'tipo' => $document['tipo'],
            'status' => $document['status'],
            'emitido_em' => $document['emitido_em'],
        ], 200, ['requestId' => $request->requestId]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }

    private function notFound(Request $request): Response
    {
        return Response::error('NAO_ENCONTRADO', 'Registro nao encontrado.', 404, $request->requestId);
    }
}
