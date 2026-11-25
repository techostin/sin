<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Carteirinha.php';
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
    header('Location: ' . SITE_URL . '/associados');
    exit;
}

$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $_SESSION['error_message'] = 'Falha de segurança (token). Tente novamente.';
        header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
        exit;
    }
}

$result = (new Carteirinha())->gerar($id);
if (!empty($result['erro'])) {
    $_SESSION['error_message'] = $result['erro'];
} else {
    $_SESSION['success_message'] = 'Carteirinha digital gerada/renovada com sucesso. Validade: ' . date('d/m/Y', strtotime($result['validade'])) . '.';
}

header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
exit;