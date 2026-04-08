<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !$id) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: listar.php?msg=excluido');
    exit;
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao excluir: " . $e->getMessage() . "</div>";
}
require_once __DIR__ . '/../../includes/footer.php'; 