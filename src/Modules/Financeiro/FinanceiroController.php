<?php

declare(strict_types=1);

namespace App\Modules\Financeiro;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\AccessScope;
use Throwable;

final class FinanceiroController
{
    public function __construct(private readonly FinanceiroRepositoryInterface $repository)
    {
    }

    public function list(Request $request, ContextoUsuario $context): Response
    {
        $scope = AccessScope::fromContext($context);
        if ($scope->isGlobal()) {
            $items = $this->repository->listMensalidades(null, []);
        } elseif ($scope->associadoId() !== null) {
            $items = $this->repository->listMensalidades($scope->associadoId(), []);
        } else {
            $items = $this->repository->listMensalidades(null, $scope->associationIds());
        }

        return Response::json($items, 200, ['requestId' => $request->requestId]);
    }

    public function create(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        if (!isset($data['associado_id']) || !is_numeric($data['associado_id'])) {
            return Response::error('VALIDACAO', 'associado_id obrigatorio.', 400, $request->requestId);
        }
        if (!isset($data['valor_cobrado']) || !is_numeric($data['valor_cobrado'])) {
            return Response::error('VALIDACAO', 'valor_cobrado obrigatorio.', 400, $request->requestId);
        }

        try {
            $row = $this->repository->createMensalidade($data);
        } catch (Throwable $exception) {
            return Response::error('ERRO_FINANCEIRO', 'Falha ao criar mensalidade.', 500, $request->requestId);
        }

        return Response::json($row, 201, ['requestId' => $request->requestId]);
    }

    public function pay(Request $request, int $id, ContextoUsuario $context): Response
    {
        $row = $this->repository->findMensalidade($id);
        if ($row === null) {
            return Response::error('NAO_ENCONTRADO', 'Mensalidade nao encontrada.', 404, $request->requestId);
        }
        $scope = AccessScope::fromContext($context);
        if (
            !$scope->isGlobal()
            && !$scope->allowsAssociado(
                isset($row['associado_id']) ? (int) $row['associado_id'] : null,
                isset($row['associacao_id']) ? (int) $row['associacao_id'] : null
            )
        ) {
            return Response::error('NAO_ENCONTRADO', 'Mensalidade nao encontrada.', 404, $request->requestId);
        }

        if (!$this->repository->pay($id)) {
            return Response::error('CONFLITO', 'Mensalidade nao esta pendente.', 409, $request->requestId);
        }

        return Response::json(['id' => $id, 'status' => 'pago'], 200, ['requestId' => $request->requestId]);
    }
}
