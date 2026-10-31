<?php

declare(strict_types=1);

namespace App\Modules\Territorio;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

final class TerritorioController
{
    public function __construct(private readonly TerritorioRepositoryInterface $repository)
    {
    }

    public function estados(Request $request): Response
    {
        $data = $this->repository->estados();

        return Response::json($data, 200, ['count' => count($data), 'requestId' => $request->requestId]);
    }

    public function cidades(Request $request): Response
    {
        $uf = $request->query('uf');
        $estadoId = $request->query('estado_id');

        if ($estadoId !== null && !is_numeric($estadoId)) {
            return $this->invalid($request, 'estado_id invalido.');
        }

        $uf = is_string($uf) ? strtoupper(trim($uf)) : null;
        if ($uf !== null && $uf !== '' && preg_match('/^[A-Z]{2}$/', $uf) !== 1) {
            return $this->invalid($request, 'uf invalida.');
        }

        $estado = is_numeric($estadoId) ? (int) $estadoId : null;
        if ($estado === null && ($uf === null || $uf === '')) {
            return $this->invalid($request, 'Informe uf ou estado_id.');
        }

        $data = $this->repository->cidades($estado, $uf !== '' ? $uf : null);

        return Response::json($data, 200, ['count' => count($data), 'requestId' => $request->requestId]);
    }

    public function microrregioes(Request $request): Response
    {
        $mesorregiaoId = $request->query('mesorregiao_id');
        if ($mesorregiaoId !== null && !is_numeric($mesorregiaoId)) {
            return $this->invalid($request, 'mesorregiao_id invalido.');
        }

        $data = $this->repository->microrregioes(is_numeric($mesorregiaoId) ? (int) $mesorregiaoId : null);

        return Response::json($data, 200, ['count' => count($data), 'requestId' => $request->requestId]);
    }

    public function cnpo(Request $request): Response
    {
        $termo = $request->query('termo');
        $termo = is_string($termo) ? trim($termo) : '';
        if (mb_strlen($termo) < 2) {
            return $this->invalid($request, 'Informe ao menos 2 caracteres em termo.');
        }

        $uf = $request->query('uf');
        $uf = is_string($uf) ? strtoupper(trim($uf)) : '';
        if ($uf !== '' && preg_match('/^[A-Z]{2}$/', $uf) !== 1) {
            return $this->invalid($request, 'uf invalida.');
        }

        $limitRaw = $request->query('limite', 25);
        if (!is_numeric($limitRaw)) {
            return $this->invalid($request, 'limite invalido.');
        }

        $limit = max(1, min(100, (int) $limitRaw));
        $cidade = $request->query('cidade');
        $tipoEntidade = $request->query('tipo_entidade');
        $filters = [
            'termo' => $termo,
            'uf' => $uf,
            'cidade' => is_string($cidade) ? trim($cidade) : '',
            'tipo_entidade' => is_string($tipoEntidade) ? trim($tipoEntidade) : '',
        ];

        $data = $this->repository->cnpo($filters, $limit);

        return Response::json($data, 200, [
            'count' => count($data),
            'limit' => $limit,
            'requestId' => $request->requestId,
        ]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }
}
