<?php

declare(strict_types=1);

namespace App\Modules\Portal;

use App\Kernel\Contracts\ContextoUsuario;
use App\Kernel\Http\Request;
use App\Kernel\Http\Response;
use App\Modules\Identidade\CapabilityService;
use App\Modules\Relatorios\RelatoriosRepositoryInterface;

final class PortalController
{
    public function __construct(
        private readonly RelatoriosRepositoryInterface $reports,
        private readonly CapabilityService $capabilities,
    ) {
    }

    public function dashboard(Request $request, ContextoUsuario $context): Response
    {
        $summary = $this->reports->summary();
        $cards = [];

        if ($context->can('associados', 'read')) {
            $cards[] = ['key' => 'associados', 'label' => 'Associados', 'data' => $summary['associados']];
        }
        if ($context->can('caderno', 'read')) {
            $cards[] = ['key' => 'rastreabilidade', 'label' => 'Lotes rastreáveis', 'data' => $summary['lotes']];
        }
        if ($context->can('fiscalizacao', 'read')) {
            $cards[] = ['key' => 'fiscalizacao', 'label' => 'Fiscalização', 'data' => $summary['visitas']];
        }
        if ($context->can('financeiro', 'read')) {
            $cards[] = ['key' => 'financeiro', 'label' => 'Financeiro', 'data' => $summary['financeiro']];
        }
        if ($context->can('relatorios', 'read')) {
            $cards[] = ['key' => 'documentos', 'label' => 'Documentos', 'data' => $summary['documentos']];
        }

        return Response::json([
            'user' => ['id' => $context->userId(), 'name' => $context->name()],
            'capabilities' => $this->capabilities->forContext($context),
            'cards' => $cards,
        ], 200, ['requestId' => $request->requestId]);
    }
}
