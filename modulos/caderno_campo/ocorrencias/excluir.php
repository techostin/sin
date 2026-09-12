<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/Ocorrencia.php';
require_once __DIR__ . '/../../../classes/AuditLog.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/caderno-campo/ocorrencias?erro=permissao');
    exit;
}

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !is_numeric((string)$id)) {
    header('Location: ' . SITE_URL . '/caderno-campo/ocorrencias?erro=csrf');
    exit;
}

try {
    $ocorrencia_obj = new Ocorrencia();
    $dados_ocorrencia = $ocorrencia_obj->getById((int)$id);
    
    // Verificar permissão
    if (!$user->hasPermission(NIVEL_GESTOR)) {
        $associado_id = $user->getAssociadoId();
        if (!$dados_ocorrencia || $dados_ocorrencia['associado_id'] != $associado_id) {
            header('Location: ' . SITE_URL . '/caderno-campo/ocorrencias?erro=permissao');
            exit;
        }
    }
    
    // Excluir
    $ocorrencia_obj->delete((int)$id, $dados_ocorrencia['associado_id']);
    AuditLog::registrar('OCORRENCIA_EXCLUIDA', 'ocorrencias_manejo', (int)$id, 'Soft delete de ocorrência');
    
    header('Location: ' . SITE_URL . '/caderno-campo/ocorrencias?msg=excluido');
    exit;
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . '/caderno-campo/ocorrencias?erro=exclusao');
    exit;
}
