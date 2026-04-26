<?php
/**
 * Script de Validação de Insumos Orgânicos
 * 
 * Cruza o insumo digitado/selecionado com a lista de permitidos (Portaria 52/2021)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$nome = $_GET['nome'] ?? '';

if (empty($nome)) {
    echo json_encode(['status' => 'error', 'message' => 'Nome do insumo não fornecido']);
    exit;
}

// Busca aproximada na lista de aprovados
$stmt = $db->prepare("SELECT * FROM insumos_organicos_aprovados 
                      WHERE nome LIKE ? OR nome_comercial LIKE ? 
                      LIMIT 1");
$like = "%$nome%";
$stmt->execute([$like, $like]);
$insumo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($insumo) {
    if ($insumo['permitido']) {
        echo json_encode([
            'status' => 'success',
            'permitido' => true,
            'message' => 'Insumo presente na lista de permitidos pela Portaria 52/2021.',
            'detalhes' => $insumo
        ]);
    } else {
        echo json_encode([
            'status' => 'warning',
            'permitido' => false,
            'message' => 'ATENÇÃO: Este insumo está na lista de RESTRIÇÃO ou PROIBIÇÃO.',
            'restricoes' => $insumo['restricoes']
        ]);
    }
} else {
    echo json_encode([
        'status' => 'not_found',
        'permitido' => false,
        'message' => 'Insumo não localizado na lista oficial. Verifique a conformidade técnica antes do uso.'
    ]);
}
