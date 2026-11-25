<?php
/**
 * API Regionalização - Dados agregados por microrregião para mapa de calor
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/MicrorregiaoRS.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';

header('Content-Type: application/json; charset=utf-8');

// Dados agregados do mapa de calor: usados apenas em páginas autenticadas.
ApiAuth::exigirLogin();

try {
    $microrregiao = new MicrorregiaoRS();
    $dados = $microrregiao->getDadosMapaCalor();
    
    echo json_encode([
        'success' => true,
        'data' => $dados,
        'total_microrregioes' => count($dados),
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}