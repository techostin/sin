<?php

declare(strict_types=1);

/**
 * Sistema 03 — Produção do Agricultor.
 * Página protegida: exige login e perfil compatível.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::home('producao');
