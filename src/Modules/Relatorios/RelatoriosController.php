<?php

declare(strict_types=1);

namespace App\Modules\Relatorios;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

final class RelatoriosController
{
    public function __construct(private readonly RelatoriosRepositoryInterface $repository)
    {
    }

    public function summary(Request $request, ContextoUsuario $context): Response
    {
        return Response::json($this->repository->summary(), 200, [
            'requestId' => $request->requestId,
            'persona' => $context->roles(),
        ]);
    }
}
