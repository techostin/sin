<?php

declare(strict_types=1);

/**
 * Sistema 01 — Gestão Associativa.
 * Página protegida: exige login e perfil compatível.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::home('assoc');
