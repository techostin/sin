<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

// Exclusão de evento é dado GLOBAL gerido pela equipe: exige Admin/Gestor.
// Antes a checagem usava $_SESSION['user_tipo'] === 'usuario' (case-sensitive),
// não exigia login e deixava o nível 3 chegar direto ao DELETE.
ApiAuth::exigirStaff();

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