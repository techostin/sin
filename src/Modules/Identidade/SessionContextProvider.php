<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Contracts\ContextoUsuario;

/**
 * Resolve o contexto a partir da sessão legada. A API é stateless por padrão;
 * este provider inicia a sessão apenas quando um endpoint autenticado é chamado.
 */
final class SessionContextProvider implements ContextoUsuarioProvider
{
    public function __construct(
        private readonly IdentityRepositoryInterface $repository,
        private readonly CapabilityService $capabilities,
    ) {
    }

    public function current(): ?ContextoUsuario
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (headers_sent()) {
                return null;
            }
            @session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!is_numeric($userId)) {
            return null;
        }

        $loaded = $this->repository->load((int) $userId);
        if ($loaded === null) {
            return null;
        }

        $user = $loaded['user'];
        $roles = $loaded['roles'];
        $permissions = $this->capabilities->effectivePermissions($roles, $loaded['permissions']);
        $name = trim((string) ($user['nome'] ?? '') . ' ' . (string) ($user['sobrenome'] ?? ''));
        $areas = [];
        if (isset($user['area_producao']) && is_string($user['area_producao'])) {
            $areas = array_values(array_filter(explode(',', $user['area_producao'])));
        }

        return new UsuarioContexto(
            (int) ($user['id'] ?? 0),
            $name,
            isset($user['associado_id']) ? (int) $user['associado_id'] : null,
            isset($user['certificadora_id']) ? (int) $user['certificadora_id'] : null,
            $roles,
            $permissions,
            $areas,
            $loaded['association_ids'],
        );
    }
}
