<?php

declare(strict_types=1);

namespace App\Modules\Animal;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;
use InvalidArgumentException;
use Throwable;

final class AnimalController
{
    public function __construct(private readonly RastreabilidadeRepositoryInterface $repository)
    {
    }

    public function create(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $nome = isset($data['nome_lote']) && is_string($data['nome_lote']) ? trim($data['nome_lote']) : '';
        if ($nome === '') {
            return $this->invalid($request, 'nome_lote obrigatorio.');
        }
        if (isset($data['quantidade']) && !is_numeric($data['quantidade'])) {
            return $this->invalid($request, 'quantidade invalida.');
        }

        $data['nome_lote'] = $nome;
        $data['associado_id'] = isset($data['associado_id']) && is_numeric($data['associado_id'])
            ? (int) $data['associado_id'] : $context->associadoId();

        try {
            $lot = $this->repository->createAnimalLot($data, (int) $context->userId());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        } catch (Throwable $exception) {
            return Response::error('ERRO_ANIMAL', 'Falha ao criar lote animal.', 500, $request->requestId);
        }

        return Response::json($lot, 201, ['requestId' => $request->requestId]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }
}
