<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ApiAuth.php';

// Antes este endpoint respondia sem autenticacao (qualquer visitante podia
// listar as propriedades de qualquer associado).
ApiAuth::exigirLogin();

$associado_id = $_GET['associado_id'] ?? null;

if (!$associado_id) {
    echo json_encode([]);
    exit;
}

// Admin/Gestor acessam qualquer produtor; usuario comum, apenas o proprio.
ApiAuth::exigirProprioAssociado($associado_id);

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, propriedade as nome FROM propriedades WHERE associado_id = ?");
    $stmt->execute([$associado_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
