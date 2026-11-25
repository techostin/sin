<?php

declare(strict_types=1);

/**
 * Sistema 03 — Produção do Agricultor.
 * Ficha de adesão do primeiro acesso (produtor sem cadastro de associado).
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::ficha('producao');
