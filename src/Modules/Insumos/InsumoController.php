<?php

declare(strict_types=1);

namespace App\Modules\Insumos;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;
use InvalidArgumentException;
use Throwable;

final class InsumoController
{
    public function __construct(private readonly RastreabilidadeRepositoryInterface $repository)
    {
    }

    public function create(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $nome = isset($data['nome']) && is_string($data['nome']) ? trim($data['nome']) : '';
        if ($nome === '') {
            return Response::error('VALIDACAO', 'nome obrigatorio.', 400, $request->requestId);
        }
        $data['nome'] = $nome;
        $data['associado_id'] = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : $context->associadoId();

        try {
            $lot = $this->repository->createLegacyInputLot($data, (int) $context->userId());
        } catch (InvalidArgumentException $exception) {
            return Response::error('VALIDACAO', $exception->getMessage(), 400, $request->requestId);
        } catch (Throwable $exception) {
            return Response::error('ERRO_INSUMO', 'Falha ao criar insumo.', 500, $request->requestId);
        }

        return Response::json($lot, 201, ['requestId' => $request->requestId]);
    }
}
