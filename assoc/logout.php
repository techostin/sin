<?php

declare(strict_types=1);

/**
 * Sistema 01 — Gestão Associativa.
 * Encerra APENAS a sessão deste sistema.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::logout('assoc');
