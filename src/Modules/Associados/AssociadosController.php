<?php

declare(strict_types=1);

namespace App\Modules\Associados;

use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

/**
 * D1 - fatia A (somente leitura). Sem rota publica nesta fase: a exposicao
 * aguarda a K1 (contexto autenticado) para nao vazar dados pessoais.
 */
final class AssociadosController
{
    private const STATUS = ['ativo', 'inativo', 'pendente'];

    public function __construct(private readonly AssociadosRepositoryInterface $repository)
    {
    }

    public function list(Request $request): Response
    {
        $page = $this->positiveInt($request->query('page'), 1);
        $perPage = min(100, $this->positiveInt($request->query('per_page'), 25));
        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUS, true) ? $status : null;
        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : null;

        $result = $this->repository->paginate($page, $perPage, $status, $q);
        $items = array_map(fn (array $row): array => $this->present($row), $result['items']);

        return Response::json($items, 200, [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $result['total'],
            'requestId' => $request->requestId,
        ]);
    }

    public function pending(Request $request): Response
    {
        $page = $this->positiveInt($request->query('page'), 1);
        $perPage = min(100, $this->positiveInt($request->query('per_page'), 25));
        $result = $this->repository->paginate($page, $perPage, 'pendente', null);
        $items = array_map(fn (array $row): array => $this->present($row), $result['items']);

        return Response::json($items, 200, [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $result['total'],
            'requestId' => $request->requestId,
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid) !== 1) {
            return Response::error('VALIDACAO', 'uuid invalido.', 400, $request->requestId);
        }

        $row = $this->repository->findByUuid($uuid);
        if ($row === null) {
            return Response::error('NAO_ENCONTRADO', 'Associado nao encontrado.', 404, $request->requestId);
        }

        return Response::json($this->present($row), 200, ['requestId' => $request->requestId]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'uuid' => $row['uuid'] ?? null,
            'nome' => $row['nome'] ?? null,
            'sobrenome' => $row['sobrenome'] ?? null,
            'cpf_mascarado' => $this->maskCpf(isset($row['cpf']) ? (string) $row['cpf'] : null),
            'email_mascarado' => $this->maskEmail(isset($row['email']) ? (string) $row['email'] : null),
            'status' => $row['status'] ?? null,
            'registro_cnpo' => $row['registro_cnpo'] ?? null,
            'associacao_id' => $row['associacao_id'] ?? null,
            'associacao_nome' => $row['associacao_nome'] ?? null,
            'microrregiao_id' => $row['microrregiao_id'] ?? null,
        ];
    }

    private function maskCpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf);
        if ($digits === null || strlen($digits) !== 11) {
            return '***';
        }

        return '***.***.***-' . substr($digits, -2);
    }

    private function maskEmail(?string $email): ?string
    {
        if ($email === null || !str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $first = $local !== '' ? substr($local, 0, 1) : '*';

        return $first . '***@' . $domain;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max(1, (int) $value);
    }
}
