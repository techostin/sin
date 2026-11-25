<?php

declare(strict_types=1);

/**
 * Sistema 01 — Gestão Associativa.
 * Ficha de adesão do primeiro acesso (produtor sem cadastro de associado).
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::ficha('assoc');
