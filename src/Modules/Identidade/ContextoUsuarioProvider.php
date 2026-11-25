<?php

declare(strict_types=1);

namespace App\Modules\Identidade;

use App\Kernel\Contracts\ContextoUsuario;

interface ContextoUsuarioProvider
{
    public function current(): ?ContextoUsuario;
}
