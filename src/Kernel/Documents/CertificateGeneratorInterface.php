<?php

declare(strict_types=1);

namespace App\Kernel\Documents;

interface CertificateGeneratorInterface
{
    /** @param array<string, mixed> $data */
    public function generate(string $number, array $data): string;
}
