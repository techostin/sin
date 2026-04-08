<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Associacao.php';

header('Content-Type: application/json');

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da associação é obrigatório']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $associacao = new Associacao();
    $detalhes = $associacao->getDetalhesCompletos($id);
    
    if ($detalhes) {
        echo json_encode([
            'success' => true,
            'data' => $detalhes
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Associação não encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?> 