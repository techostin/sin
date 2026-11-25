<?php

declare(strict_types=1);

/**
 * Sistema 01 — Gestão Associativa.
 * Login próprio (sessão SINPORG_ASSOC, cookie em /app/assoc/).
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::login('assoc');
