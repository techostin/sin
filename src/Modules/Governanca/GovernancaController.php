<?php

declare(strict_types=1);

namespace App\Modules\Governanca;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use Throwable;

final class GovernancaController
{
    public function __construct(private readonly GovernancaRepositoryInterface $repository)
    {
    }

    public function events(Request $request): Response
    {
        return Response::json($this->repository->listEvents(), 200, ['requestId' => $request->requestId]);
    }

    public function assemblies(Request $request): Response
    {
        return Response::json($this->repository->listAssemblies(), 200, ['requestId' => $request->requestId]);
    }

    public function createEvent(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        if (!isset($data['nome']) || trim((string) $data['nome']) === '') {
            return Response::error('VALIDACAO', 'nome obrigatorio.', 400, $request->requestId);
        }

        try {
            $event = $this->repository->createEvent($data, (int) $context->userId());
        } catch (Throwable $exception) {
            return Response::error('ERRO_EVENTO', 'Falha ao criar evento.', 500, $request->requestId);
        }

        return Response::json($event, 201, ['requestId' => $request->requestId]);
    }

    public function createAssembly(Request $request, ContextoUsuario $context): Response
    {
        $data = $request->json();
        if (!isset($data['titulo']) || trim((string) $data['titulo']) === '') {
            return Response::error('VALIDACAO', 'titulo obrigatorio.', 400, $request->requestId);
        }

        try {
            $assembly = $this->repository->createAssembly($data, (int) $context->userId());
        } catch (Throwable $exception) {
            return Response::error('ERRO_ASSEMBLEIA', 'Falha ao criar assembleia.', 500, $request->requestId);
        }

        return Response::json($assembly, 201, ['requestId' => $request->requestId]);
    }
}
