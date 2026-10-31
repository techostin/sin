<?php

declare(strict_types=1);

namespace App\Kernel\Security;

use InvalidArgumentException;

/**
 * Validação de upload: erro, tamanho, arquivo real e MIME verificado por finfo.
 */
final class UploadValidator
{
    /**
     * @param int $maxBytes
     * @param list<string> $allowedMimeTypes
     * @param bool $requireUploadedFile false apenas em testes
     */
    public function __construct(
        private readonly int $maxBytes = 5_242_880,
        private readonly array $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'],
        private readonly bool $requireUploadedFile = true,
    ) {
    }

    /** @param array{error?: int, size?: int, tmp_name?: string} $file */
    public function validate(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Falha no upload do arquivo.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new InvalidArgumentException('Arquivo vazio ou acima do limite permitido.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            throw new InvalidArgumentException('Arquivo temporario ausente.');
        }

        $validFile = $this->requireUploadedFile
            ? is_uploaded_file($tmpName)
            : is_file($tmpName);

        if (!$validFile) {
            throw new InvalidArgumentException('Arquivo temporario invalido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if (!is_string($mime) || !in_array($mime, $this->allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Tipo de arquivo nao permitido.');
        }
    }
}
