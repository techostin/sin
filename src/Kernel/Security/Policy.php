<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use App\Kernel\Contracts\ContextoUsuario;

interface Policy
{
    public function allows(ContextoUsuario $context, string $resource, string $action): bool;
}
