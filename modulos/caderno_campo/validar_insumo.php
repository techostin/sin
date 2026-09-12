<?php
/**
 * Script de Validação de Insumos Orgânicos
 * 
 * Cruza o insumo digitado/selecionado com a lista de permitidos (Portaria 52/2021)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/InsumoValidador.php';

header('Content-Type: application/json');

$validador = new InsumoValidador();
$resultado = $validador->avaliar($_GET['nome'] ?? '');

if ($resultado['status'] === 'success') {
    echo json_encode([
        'status' => 'success',
        'permitido' => true,
        'message' => $resultado['message'],
        'detalhes' => $resultado['insumo']
    ]);
} elseif ($resultado['status'] === 'warning') {
    echo json_encode([
        'status' => 'warning',
        'permitido' => false,
        'message' => $resultado['message'],
        'restricoes' => $resultado['insumo']['restricoes'] ?? null
    ]);
} else {
    echo json_encode([
        'status' => $resultado['status'],
        'permitido' => false,
        'message' => $resultado['message']
    ]);
}
