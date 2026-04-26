<?php
// API simplificada para retornar eventos pendentes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar eventos agendados com data futura ou de hoje
    $sql = "SELECT 
                id, 
                nome, 
                descricao, 
                data_evento, 
                local, 
                status 
            FROM eventos 
            WHERE status = 'Agendado' 
            AND data_evento >= CURDATE()
            ORDER BY data_evento ASC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'eventos' => $eventos,
        'total' => count($eventos),
        'user_id' => $_SESSION['user_id']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
