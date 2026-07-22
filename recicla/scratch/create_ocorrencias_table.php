<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Read and execute SQL file
    $sql = file_get_contents(__DIR__ . '/../sql/criar_tabela_ocorrencias.sql');
    $db->exec($sql);
    
    echo "Tabela ocorrencias_manejo criada com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>