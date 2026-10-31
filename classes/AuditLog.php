<?php
require_once __DIR__ . '/Database.php';

class AuditLog {
    /**
     * Registra um evento de auditoria na tabela audit_log (NFR03).
     * @param string $acao Ação executada (ex.: LOGIN, ATIVIDADE_CADASTRADA, INSUMO_EXCLUIDO).
     * @param string|null $tabela Tabela afetada.
     * @param int|null $registro_id ID do registro afetado.
     * @param string|null $descricao Detalhes adicionais.
     * @return bool
     */
    public static function registrar($acao, $tabela = null, $registro_id = null, $descricao = null) {
        try {
            $db = Database::getInstance()->getConnection();
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $stmt = $db->prepare("INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $acao, $tabela, $registro_id, $descricao, $ip]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }
}
