<?php

declare(strict_types=1);

/**
 * Sistema 02 — Controle Social e Certificação (OPAC/OCS).
 * Encerra APENAS a sessão deste sistema.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::logout('social');
