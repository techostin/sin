<?php

declare(strict_types=1);

namespace App\Kernel\Documents;

interface DocumentNumberGeneratorInterface
{
    public function next(string $type, int $year): string;
}
