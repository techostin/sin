<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Caixa.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && CSRFProtection::validatePostRequest()) {
    if ((new Caixa())->delete((int) $_GET['id'])) {
        $_SESSION['success_message'] = 'Lançamento de caixa excluído.';
    } else {
        $_SESSION['error_message'] = 'Erro ao excluir o lançamento.';
    }
} else {
    $_SESSION['error_message'] = 'Operação inválida.';
}

header('Location: ' . SITE_URL . '/caixa');
exit;