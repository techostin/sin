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

    public function store(Request $request): Response
    {
        $data = $request->json();
        $nome = isset($data['nome']) && is_string($data['nome']) ? trim($data['nome']) : '';
        $sobrenome = isset($data['sobrenome']) && is_string($data['sobrenome']) ? trim($data['sobrenome']) : '';
        if ($nome === '' || $sobrenome === '') {
            return Response::error('VALIDACAO', 'nome e sobrenome sao obrigatorios.', 400, $request->requestId);
        }

        $email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return Response::error('VALIDACAO', 'email invalido.', 400, $request->requestId);
        }

        try {
            $created = $this->repository->create([
                'nome' => $nome,
                'sobrenome' => $sobrenome,
                'email' => $email,
                'cpf' => isset($data['cpf']) && is_string($data['cpf']) ? trim($data['cpf']) : '',
                'associacao_id' => $data['associacao_id'] ?? null,
                'microrregiao_id' => $data['microrregiao_id'] ?? null,
                'registro_cnpo' => $data['registro_cnpo'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return Response::error('CONFLITO', 'Nao foi possivel criar o associado.', 409, $request->requestId);
        }

        return Response::json($this->present($created), 201, ['requestId' => $request->requestId]);
    }

    public function approve(Request $request, string $uuid): Response
    {
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $uuid) !== 1) {
            return Response::error('VALIDACAO', 'uuid invalido.', 400, $request->requestId);
        }

        if (!$this->repository->approve($uuid)) {
            return Response::error('NAO_ENCONTRADO', 'Associado pendente nao encontrado.', 404, $request->requestId);
        }

        return Response::json(['uuid' => $uuid, 'status' => 'ativo'], 200, ['requestId' => $request->requestId]);
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
