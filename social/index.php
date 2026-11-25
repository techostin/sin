<?php

declare(strict_types=1);

/**
 * Sistema 02 — Controle Social e Certificação (OPAC/OCS).
 * Página protegida: exige login e perfil compatível.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::home('social');
