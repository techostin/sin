<?php

declare(strict_types=1);

/**
 * Sistema 02 — Controle Social e Certificação (OPAC/OCS).
 * Login próprio (sessão SINPORG_SOCIAL, cookie em /app/social/).
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::login('social');
