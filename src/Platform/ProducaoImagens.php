<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Upload das imagens de propriedade usando a MESMA classe do sistema legado
 * (`classes/ImageUploader.php`): JPG/PNG/WEBP, até 5 MB, redimensionamento e
 * otimização, com arquivos em `uploads/propriedades/`.
 */
final class ProducaoImagens
{
    /**
     * @param array<string, mixed> $arquivo
     * @return array{ok: bool, filename: string, erro: string}
     */
    public static function upload(array $arquivo, string $prefixo, string $pasta = 'propriedades'): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'filename' => '', 'erro' => 'Selecione uma imagem.'];
        }

        $raiz = dirname(__DIR__, 2);
        require_once $raiz . '/classes/ImageUploader.php';
        $classe = 'ImageUploader';
        if (!class_exists($classe)) {
            return ['ok' => false, 'filename' => '', 'erro' => 'Upload de imagens indisponível.'];
        }
        $pasta = preg_replace('/[^a-z0-9_-]/i', '', $pasta) ?: 'propriedades';
        $uploader = new $classe($raiz . '/uploads/' . $pasta . '/');
        $resultado = call_user_func([$uploader, 'uploadImage'], $arquivo, $prefixo);

        return [
            'ok' => (bool) ($resultado['success'] ?? false),
            'filename' => (string) ($resultado['filename'] ?? ''),
            'erro' => (string) ($resultado['message'] ?? 'Não foi possível enviar a imagem.'),
        ];
    }

    public static function remover(?string $filename, string $pasta = 'propriedades'): void
    {
        $filename = trim((string) $filename);
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/')) {
            return;
        }

        $pasta = preg_replace('/[^a-z0-9_-]/i', '', $pasta) ?: 'propriedades';
        $caminho = dirname(__DIR__, 2) . '/uploads/' . $pasta . '/' . $filename;
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }

    public static function url(?string $valor, string $pasta = 'propriedades'): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $valor) === 1) {
            return $valor;
        }

        $pasta = preg_replace('/[^a-z0-9_-]/i', '', $pasta) ?: 'propriedades';

        return Urls::base() . '/uploads/' . $pasta . '/' . rawurlencode($valor);
    }
}
