<?php

declare(strict_types=1);

namespace App\Modules\Rastreabilidade;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\AccessScope;
use InvalidArgumentException;
use Throwable;

final class RastreabilidadeController
{
    public function __construct(private readonly RastreabilidadeRepositoryInterface $repository)
    {
    }

    public function createVegetal(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $required = ['nome_lote', 'produto_id', 'data_producao', 'quantidade', 'unidade_medida'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->invalid($request, 'Campo obrigatorio: ' . $field);
            }
        }
        if (!is_numeric($data['produto_id']) || !is_numeric($data['quantidade'])) {
            return $this->invalid($request, 'produto_id/quantidade invalidos.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['data_producao']) !== 1) {
            return $this->invalid($request, 'data_producao deve ser YYYY-MM-DD.');
        }

        $data['associado_id'] = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id']
            : $context->associadoId();

        try {
            $lot = $this->repository->createVegetalLot($data, (int) $context->userId());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        }

        return Response::json($lot, 201, ['requestId' => $request->requestId]);
    }

    public function createInsumo(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        if (!isset($data['nome']) || !is_string($data['nome']) || trim($data['nome']) === '') {
            return $this->invalid($request, 'Campo obrigatorio: nome.');
        }

        $data['associado_id'] = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id']
            : $context->associadoId();

        try {
            $lot = $this->repository->createInputLot($data, (int) $context->userId());
        } catch (Throwable $exception) {
            return $this->invalid($request, 'Falha ao criar lote de insumo.');
        }

        return Response::json($lot, 201, ['requestId' => $request->requestId]);
    }

    public function show(Request $request, string $uuid, ?ContextoUsuario $context = null): Response
    {
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid) !== 1) {
            return $this->invalid($request, 'uuid invalido.');
        }

        $lot = $this->repository->findByUuid($uuid);
        if ($lot === null || ($context !== null && !$this->canAccessLot($lot, $context))) {
            return $this->notFound($request);
        }

        return Response::json($lot, 200, ['requestId' => $request->requestId]);
    }

    public function addEvent(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        $lot = $this->repository->findByUuid($uuid);
        if ($lot === null || !$this->canAccessLot($lot, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        if (!isset($data['tipo']) || !is_string($data['tipo']) || trim($data['tipo']) === '') {
            return $this->invalid($request, 'Campo obrigatorio: tipo.');
        }
        if (isset($data['payload']) && !is_array($data['payload'])) {
            return $this->invalid($request, 'payload deve ser objeto.');
        }

        $event = $this->repository->addEvent($uuid, $data, (int) $context->userId());
        if ($event === null) {
            return $this->notFound($request);
        }

        return Response::json($event, 201, ['requestId' => $request->requestId]);
    }

    public function addLink(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        $lot = $this->repository->findByUuid($uuid);
        if ($lot === null || !$this->canAccessLot($lot, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        $destino = $data['destino_uuid'] ?? null;
        if (!is_string($destino) || preg_match('/^[0-9a-fA-F-]{36}$/', $destino) !== 1) {
            return $this->invalid($request, 'destino_uuid invalido.');
        }

        $tipo = isset($data['tipo']) ? (string) $data['tipo'] : 'insumo';
        if (!in_array($tipo, ['insumo', 'transformacao', 'movimentacao'], true)) {
            return $this->invalid($request, 'tipo de elo invalido.');
        }
        if (isset($data['quantidade']) && !is_numeric($data['quantidade'])) {
            return $this->invalid($request, 'quantidade invalida.');
        }

        $created = $this->repository->addLink($uuid, $destino, [
            'tipo' => $tipo,
            'quantidade' => $data['quantidade'] ?? null,
            'unidade' => isset($data['unidade']) ? (string) $data['unidade'] : null,
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
        ], (int) $context->userId());

        if (!$created) {
            return $this->invalid($request, 'Lotes invalidos ou iguais.');
        }

        return Response::json(['origem_uuid' => $uuid, 'destino_uuid' => $destino, 'tipo' => $tipo], 201, [
            'requestId' => $request->requestId,
        ]);
    }

    public function verify(Request $request, string $code): Response
    {
        if (preg_match('/^LOTE-[A-Z0-9]{6,20}$/', $code) !== 1) {
            return $this->notFound($request);
        }

        $result = $this->repository->verifyCode($code);
        if ($result === null) {
            return $this->notFound($request);
        }

        return Response::json($result, 200, ['requestId' => $request->requestId]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }

    /** @param array<string, mixed> $lot */
    private function canAccessLot(array $lot, ContextoUsuario $context): bool
    {
        return AccessScope::fromContext($context)->allowsAssociado(
            isset($lot['associado_id']) ? (int) $lot['associado_id'] : null,
            isset($lot['associado_associacao_id']) ? (int) $lot['associado_associacao_id'] : null
        );
    }

    private function notFound(Request $request): Response
    {
        return Response::error('NAO_ENCONTRADO', 'Lote nao encontrado.', 404, $request->requestId);
    }
}
