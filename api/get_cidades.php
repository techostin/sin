<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$cidades = [];

// Se solicitado todas as cidades
if (isset($_GET['todos']) && $_GET['todos'] == '1') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT c.id, c.nome, c.estado as estado_id, e.uf as estado_sigla 
            FROM cidades c 
            INNER JOIN estados e ON c.estado = e.id 
            ORDER BY c.nome ASC
        ");
        $stmt->execute();
        $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro no servidor']);
        exit;
    }
}
// Se solicitado cidades de um estado específico
elseif (isset($_GET['estado_id']) && is_numeric($_GET['estado_id'])) {
    $estado_id = intval($_GET['estado_id']);

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, nome, estado FROM cidades WHERE estado = :estado_id ORDER BY nome ASC");
        $stmt->bindParam(':estado_id', $estado_id, PDO::PARAM_INT);
        $stmt->execute();
        $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro no servidor']);
        exit;
    }
}

echo json_encode($cidades);
?>