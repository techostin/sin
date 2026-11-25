<?php

declare(strict_types=1);

/**
 * Arquivo desativado.
 *
 * O teste ponta a ponta oficial da plataforma está em:
 *   php dev-utils/smoke-plataforma.php
 *
 * Mantido apenas como marcador vazio — pode ser apagado manualmente.
 */

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Arquivo desativado. Use: php dev-utils/smoke-plataforma.php\n");
    exit(1);
}

http_response_code(404);
