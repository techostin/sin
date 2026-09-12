<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/AuditLog.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/caderno-campo/atividades?erro=permissao');
    exit;
}

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !is_numeric((string)$id)) {
    header('Location: ' . SITE_URL . '/caderno-campo/atividades?erro=csrf');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('UPDATE atividades_diarias SET excluido = 1 WHERE id = ?');
    $stmt->execute([(int)$id]);
    AuditLog::registrar('ATIVIDADE_EXCLUIDA', 'atividades_diarias', (int)$id, 'Soft delete de atividade diária');
    header('Location: ' . SITE_URL . '/caderno-campo/atividades?msg=excluido');
    exit;
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . '/caderno-campo/atividades?erro=exclusao');
    exit;
}

