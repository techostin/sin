<?php

declare(strict_types=1);

namespace App\Modules\Agroindustria;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;

final class AgroindustriaController
{
    public function __construct(private readonly RastreabilidadeRepositoryInterface $repository)
    {
    }

    public function transform(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $origem = $data['origem_uuid'] ?? null;
        $destino = $data['destino_uuid'] ?? null;
        if (!is_string($origem) || preg_match('/^[0-9a-fA-F-]{36}$/', $origem) !== 1) {
            return $this->invalid($request, 'origem_uuid invalido.');
        }
        if (!is_string($destino) || preg_match('/^[0-9a-fA-F-]{36}$/', $destino) !== 1) {
            return $this->invalid($request, 'destino_uuid invalido.');
        }
        if (isset($data['quantidade']) && !is_numeric($data['quantidade'])) {
            return $this->invalid($request, 'quantidade invalida.');
        }

        $created = $this->repository->addLink($origem, $destino, [
            'tipo' => 'transformacao',
            'quantidade' => $data['quantidade'] ?? null,
            'unidade' => isset($data['unidade']) ? (string) $data['unidade'] : null,
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
        ], (int) $context->userId());

        if (!$created) {
            return $this->invalid($request, 'Lotes invalidos ou iguais.');
        }

        return Response::json([
            'origem_uuid' => $origem,
            'destino_uuid' => $destino,
            'tipo' => 'transformacao',
        ], 201, ['requestId' => $request->requestId]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }
}
