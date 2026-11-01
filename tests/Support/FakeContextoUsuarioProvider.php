<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel\Contracts\ContextoUsuario;
use App\Modules\Identidade\ContextoUsuarioProvider;

final class FakeContextoUsuarioProvider implements ContextoUsuarioProvider
{
    public function __construct(private readonly ?ContextoUsuario $context = null)
    {
    }

    public function current(): ?ContextoUsuario
    {
        return $this->context;
    }
}
