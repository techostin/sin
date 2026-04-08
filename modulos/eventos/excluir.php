<?php
session_start();
if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario') {
    header('Location: listar.php?erro=permissao');
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !$id) {
    header('Location: listar.php?erro=id_nao_informado');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM eventos WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: listar.php?msg=excluido');
    exit;
} catch (PDOException $e) {
    header('Location: listar.php?erro=exclusao&msg=' . urlencode($e->getMessage()));
    exit;
}