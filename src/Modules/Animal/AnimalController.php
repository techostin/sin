<?php

declare(strict_types=1);

namespace App\Modules\Animal;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Kernel\Security\AccessScope;
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

    public function addAnimal(Request $request, string $uuid, ContextoUsuario $context): Response
    {
        if (!$this->canAccessLot($uuid, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        $nome = isset($data['nome_animal']) && is_string($data['nome_animal']) ? trim($data['nome_animal']) : '';
        $especie = isset($data['especie']) && is_string($data['especie']) ? trim($data['especie']) : '';
        if ($nome === '' || $especie === '') {
            return $this->invalid($request, 'nome_animal e especie sao obrigatorios.');
        }

        $animal = $this->repository->addAnimal($uuid, $data, (int) $context->userId());
        if ($animal === null) {
            return $this->notFound($request);
        }

        return Response::json($animal, 201, ['requestId' => $request->requestId]);
    }

    public function sanitary(Request $request, string $uuid, int $animalId, ContextoUsuario $context): Response
    {
        if (!$this->canAccessLot($uuid, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        if (!isset($data['tipo_tratamento']) || !is_string($data['tipo_tratamento'])) {
            return $this->invalid($request, 'tipo_tratamento obrigatorio.');
        }
        if (
            isset($data['data_tratamento'])
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['data_tratamento']) !== 1
        ) {
            return $this->invalid($request, 'data_tratamento invalida.');
        }

        $event = $this->repository->addSanitaryEvent($uuid, $animalId, $data, (int) $context->userId());
        if ($event === null) {
            return $this->notFound($request);
        }

        return Response::json($event, 201, ['requestId' => $request->requestId]);
    }

    public function reproduction(Request $request, string $uuid, int $animalId, ContextoUsuario $context): Response
    {
        if (!$this->canAccessLot($uuid, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        $tipos = ['COBERTURA', 'INSEMINACAO', 'PARTO', 'NASCIMENTO', 'DESMAME'];
        if (!isset($data['tipo_evento']) || !in_array($data['tipo_evento'], $tipos, true)) {
            return $this->invalid($request, 'tipo_evento invalido.');
        }

        $event = $this->repository->addReproductionEvent($uuid, $animalId, $data, (int) $context->userId());
        if ($event === null) {
            return $this->notFound($request);
        }

        return Response::json($event, 201, ['requestId' => $request->requestId]);
    }

    public function movement(Request $request, string $uuid, int $animalId, ContextoUsuario $context): Response
    {
        if (!$this->canAccessLot($uuid, $context)) {
            return $this->notFound($request);
        }

        $data = $request->json();
        $tipos = ['ENTRADA', 'SAIDA', 'TRANSFERENCIA', 'OBITO'];
        if (!isset($data['tipo']) || !in_array($data['tipo'], $tipos, true)) {
            return $this->invalid($request, 'tipo de movimentacao invalido.');
        }

        $movement = $this->repository->addAnimalMovement($uuid, $animalId, $data, (int) $context->userId());
        if ($movement === null) {
            return $this->notFound($request);
        }

        return Response::json($movement, 201, ['requestId' => $request->requestId]);
    }

    private function canAccessLot(string $uuid, ContextoUsuario $context): bool
    {
        $lot = $this->repository->findByUuid($uuid);
        if ($lot === null) {
            return false;
        }

        return AccessScope::fromContext($context)->allowsAssociado(
            isset($lot['associado_id']) ? (int) $lot['associado_id'] : null,
            isset($lot['associado_associacao_id']) ? (int) $lot['associado_associacao_id'] : null
        );
    }

    private function notFound(Request $request): Response
    {
        return Response::error('NAO_ENCONTRADO', 'Lote ou animal nao encontrado.', 404, $request->requestId);
    }

    private function invalid(Request $request, string $message): Response
    {
        return Response::error('VALIDACAO', $message, 400, $request->requestId);
    }
}
