<?php

declare(strict_types=1);

namespace App\Kernel\Audit;

use App\Kernel\Contracts\AuditLogger;
use PDO;

final class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function record(string $action, string $resource, ?string $resourceId = null, array $data = []): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userId = is_numeric($userId) ? (int) $userId : null;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        $description = trim($action . ' ' . $resource . ' ' . ($resourceId ?? '') . ' ' . $json);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ip = is_string($ip) ? substr($ip, 0, 45) : null;

        $statement = $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)
             VALUES (:user_id, :acao, :tabela, :registro_id, :descricao, :ip)'
        );
        $statement->execute([
            'user_id' => $userId,
            'acao' => mb_substr($action, 0, 50),
            'tabela' => mb_substr($resource, 0, 50),
            'registro_id' => $resourceId !== null && ctype_digit($resourceId) ? (int) $resourceId : null,
            'descricao' => mb_substr($description, 0, 500),
            'ip' => $ip,
        ]);
    }
}
