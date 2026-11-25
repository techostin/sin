<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/mensalidades');
    exit;
}
$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (CSRFProtection::validatePostRequest()) {
        $model = new Mensalidade();
        $mensalidade = $model->getById($id);
        if ($mensalidade && $mensalidade['fonte'] === 'manual') {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM mensalidades WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = 'Lançamento manual excluído.';
            } else {
                $_SESSION['error_message'] = 'Erro ao excluir lançamento.';
            }
        } else {
            $_SESSION['error_message'] = 'Apenas lançamentos manuais podem ser excluídos.';
        }
    } else {
        $_SESSION['error_message'] = 'Token de segurança inválido.';
    }
}

header('Location: ' . SITE_URL . '/mensalidades');
exit;