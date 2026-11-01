<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;

final class MeController
{
    public function __construct(
        private readonly ContextoUsuarioProvider $provider,
        private readonly CapabilityService $capabilities,
    ) {
    }

    public function me(Request $request): Response
    {
        $context = $this->provider->current();
        if ($context === null) {
            return $this->unauthorized($request);
        }

        return Response::json($this->userData($context), 200, ['requestId' => $request->requestId]);
    }

    public function capabilities(Request $request): Response
    {
        $context = $this->provider->current();
        if ($context === null) {
            return $this->unauthorized($request);
        }

        return Response::json(
            array_merge($this->userData($context), $this->capabilities->forContext($context)),
            200,
            ['requestId' => $request->requestId]
        );
    }

    /** @return array<string, mixed> */
    private function userData(ContextoUsuario $context): array
    {
        return [
            'id' => $context->userId(),
            'name' => $context->name(),
            'roles' => $context->roles(),
            'associadoId' => $context->associadoId(),
            'certificadoraId' => $context->certificadoraId(),
            'visibleAssociations' => $context->visibleAssociations(),
            'productionAreas' => $context->productionAreas(),
        ];
    }

    private function unauthorized(Request $request): Response
    {
        return Response::error('NAO_AUTENTICADO', 'Autenticacao necessaria.', 401, $request->requestId);
    }
}
