<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$estado = null;

if (isset($_GET['cidade_id']) && is_numeric($_GET['cidade_id'])) {
    $cidade_id = intval($_GET['cidade_id']);

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT e.id, e.nome, e.sigla 
            FROM estados e 
            INNER JOIN cidades c ON e.id = c.estado 
            WHERE c.id = :cidade_id
        ");
        $stmt->bindParam(':cidade_id', $cidade_id, PDO::PARAM_INT);
        $stmt->execute();
        $estado = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro no servidor']);
        exit;
    }
}

echo json_encode($estado);
?> 