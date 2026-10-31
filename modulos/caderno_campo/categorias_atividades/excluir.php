<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/ApiAuth.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Endpoint de exclusão: exige login (antes bastava existir qualquer sessão).
ApiAuth::exigirLogin();

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !is_numeric((string)$id)) {
    header('Location: ' . SITE_URL . '/caderno-campo/categorias-atividades?erro=csrf');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Anti-IDOR: só o dono (nível 3) ou Admin/Gestor excluem a categoria;
    // categorias globais (associado_id nulo) ficam restritas à equipe.
    $stmt = $db->prepare('SELECT associado_id FROM categorias_atividades WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    ApiAuth::exigirProprioAssociado((int) $stmt->fetchColumn());

    $stmt = $db->prepare('DELETE FROM categorias_atividades WHERE id = ?');
    $stmt->execute([(int)$id]);
    header('Location: ' . SITE_URL . '/caderno-campo/categorias-atividades?msg=excluido');
    exit;
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . '/caderno-campo/categorias-atividades?erro=exclusao');
    exit;
}

