<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Platform\Identidades;
use PHPUnit\Framework\TestCase;

final class IdentidadesTest extends TestCase
{
    public function testEmailNaoEhTratadoComoCpf(): void
    {
        self::assertSame('', Identidades::cpfDeIdentificador('naoexiste@example.com'));
        self::assertSame('', Identidades::cpfDeIdentificador('cici@gmail.com'));
        self::assertSame('', Identidades::cpfDeIdentificador(''));
    }

    public function testCpfValidoEhNormalizado(): void
    {
        self::assertSame('11144477735', Identidades::cpfDeIdentificador('111.444.777-35'));
        self::assertSame('11144477735', Identidades::cpfDeIdentificador('11144477735'));
    }

    public function testCpfIncompletoNaoEhUsado(): void
    {
        self::assertSame('', Identidades::cpfDeIdentificador('1114447773'));
        self::assertSame('', Identidades::cpfDeIdentificador('111444777354'));
    }
}
