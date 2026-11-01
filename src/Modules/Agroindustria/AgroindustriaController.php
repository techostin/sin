<?php

declare(strict_types=1);

namespace App\Modules\Agroindustria;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Modules\Rastreabilidade\RastreabilidadeRepositoryInterface;
use InvalidArgumentException;
use Throwable;

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

    public function recipe(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $nome = isset($data['nome_produto_final']) && is_string($data['nome_produto_final'])
            ? trim($data['nome_produto_final']) : '';
        if ($nome === '') {
            return $this->invalid($request, 'nome_produto_final obrigatorio.');
        }
        if (!isset($data['percentual_organico_calculado']) || !is_numeric($data['percentual_organico_calculado'])) {
            return $this->invalid($request, 'percentual_organico_calculado obrigatorio.');
        }
        $data['nome_produto_final'] = $nome;
        $data['associado_id'] = $context->associadoId();

        try {
            $recipe = $this->repository->createRecipe($data, (int) $context->userId());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        } catch (Throwable $exception) {
            return Response::error('ERRO_RECEITA', 'Falha ao criar receita.', 500, $request->requestId);
        }

        return Response::json($recipe, 201, ['requestId' => $request->requestId]);
    }

    public function order(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        $required = [
            'receita_id',
            'lote_processado_final',
            'data_processamento',
            'quantidade_final_obtida',
            'unidade_medida',
        ];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return $this->invalid($request, 'Campo obrigatorio: ' . $field);
            }
        }
        if (!is_numeric($data['receita_id']) || !is_numeric($data['quantidade_final_obtida'])) {
            return $this->invalid($request, 'receita_id/quantidade_final_obtida invalidos.');
        }
        if (isset($data['lotes_entrada']) && !is_array($data['lotes_entrada'])) {
            return $this->invalid($request, 'lotes_entrada deve ser lista.');
        }
        $data['associado_id'] = $context->associadoId();

        try {
            $order = $this->repository->createAgroOrder($data, (int) $context->userId());
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($request, $exception->getMessage());
        } catch (Throwable $exception) {
            return Response::error('ERRO_ORDEM', 'Falha ao criar ordem.', 500, $request->requestId);
        }

        return Response::json($order, 201, ['requestId' => $request->requestId]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }
}
