<?php

declare(strict_types=1);

namespace App\Kernel\Documents;

use Mpdf\Mpdf;
use RuntimeException;

final class MpdfCertificateGenerator implements CertificateGeneratorInterface
{
    public function __construct(
        private readonly string $outputDirectory,
        private readonly string $tempDirectory,
        private readonly string $template,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function generate(string $number, array $data): string
    {
        foreach ([$this->outputDirectory, $this->tempDirectory] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Diretorio de certificado indisponivel: ' . $directory);
            }
        }

        $data['numero'] = $number;
        $data['emitido_em'] = $data['emitido_em'] ?? date('d/m/Y H:i');
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->template;
        $html = ob_get_clean();
        if (!is_string($html)) {
            throw new RuntimeException('Falha ao renderizar o certificado.');
        }

        $mpdf = new Mpdf(['tempDir' => $this->tempDirectory]);
        $mpdf->WriteHTML($html);

        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '_', $number) . '.pdf';
        $path = rtrim($this->outputDirectory, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        $mpdf->OutputFile($path);

        return $path;
    }
}
