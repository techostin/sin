<?php

declare(strict_types=1);

/**
 * Sistema 03 — Produção do Agricultor.
 * Login próprio (sessão SINPORG_PRODUCAO, cookie em /app/producao/).
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::login('producao');
