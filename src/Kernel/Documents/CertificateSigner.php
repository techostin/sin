<?php

declare(strict_types=1);

namespace App\Kernel\Documents;

use RuntimeException;

/**
 * Assinatura de integridade HMAC do documento. Não substitui assinatura
 * digital ICP-Brasil; registra que o conteúdo não foi alterado.
 */
final class CertificateSigner
{
    public function __construct(private readonly ?string $key = null)
    {
    }

    /** @param array<string, mixed> $payload */
    public function sign(array $payload, string $fileHash): string
    {
        $key = $this->key;
        if ($key === null || $key === '') {
            if (defined('CERT_SIGNING_KEY')) {
                $key = (string) constant('CERT_SIGNING_KEY');
            } elseif (defined('ID_ENCODE_KEY')) {
                $key = (string) constant('ID_ENCODE_KEY');
            }
        }
        if ($key === null || $key === '') {
            throw new RuntimeException('Chave de assinatura nao configurada.');
        }

        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', ($canonical === false ? '{}' : $canonical) . '|' . $fileHash, $key);
    }

    /** @param array<string, mixed> $payload */
    public function verify(array $payload, string $fileHash, string $signature): bool
    {
        return hash_equals($this->sign($payload, $fileHash), $signature);
    }
}
