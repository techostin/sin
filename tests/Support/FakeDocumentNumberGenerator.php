<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel\Documents\DocumentNumberGeneratorInterface;

final class FakeDocumentNumberGenerator implements DocumentNumberGeneratorInterface
{
    private int $sequence = 0;

    public function next(string $type, int $year): string
    {
        $this->sequence++;

        return sprintf('%s-%d-%06d', $type, $year, $this->sequence);
    }
}
