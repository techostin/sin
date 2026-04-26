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
    echo json_encode([
        'success' => false, 
        'message' => 'Usuário não autenticado',
        'session_status' => session_status(),
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar todos os eventos com status Agendado (independentemente da data)
    $sql = "SELECT 
                id, 
                nome, 
                descricao, 
                data_evento, 
                local, 
                status 
            FROM eventos 
            WHERE status = 'Agendado'
            ORDER BY data_evento ASC
            LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'eventos' => $eventos,
        'total' => count($eventos),
        'user_id' => $_SESSION['user_id'],
        'debug' => [
            'curdate' => date('Y-m-d'),
            'session_id' => session_id(),
            'query' => $sql
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
