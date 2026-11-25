<?php

declare(strict_types=1);

/**
 * Sistema 01 — Gestão Associativa.
 * Diretoria: liberar contas do pré-cadastro e aprovar fichas de adesão.
 */
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';

\App\Platform\Fluxo::pendencias('assoc');
