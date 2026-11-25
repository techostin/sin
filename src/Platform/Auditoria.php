<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Auditoria da plataforma.
 *
 * Grava no mesmo `audit_log` usado pelo restante do sistema (mesmo formato),
 * mas sem depender da classe global legada — o framework só precisa do PDO.
 */
final class Auditoria
{
    public static function registrar(
        string $acao,
        ?int $usuarioId,
        ?string $tabela,
        ?int $registroId,
        string $descricao,
        ?string $app = null
    ): void {
        try {
            $stmt = Identidades::pdo()->prepare(
                'INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)
                 VALUES (:user_id, :acao, :tabela, :registro_id, :descricao, :ip)'
            );

            $descricaoFinal = $app !== null ? trim($descricao . ' | app: ' . $app) : $descricao;

            $stmt->execute([
                'user_id' => $usuarioId,
                'acao' => $acao,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'descricao' => $descricaoFinal,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable) {
            // Auditoria nunca deve derrubar o fluxo de login.
        }
    }
}
