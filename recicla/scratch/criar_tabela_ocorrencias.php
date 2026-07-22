<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $sql = file_get_contents(__DIR__ . '/../sql/criar_tabela_ocorrencias.sql');
    $db->exec($sql);
    echo json_encode(['status' => 'success', 'message' => 'Tabela ocorrencias_manejo criada/verificada com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
