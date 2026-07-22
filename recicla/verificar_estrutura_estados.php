<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "=== VERIFICANDO ESTRUTURA DA TABELA ESTADOS ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar estrutura da tabela estados
    echo "Estrutura da tabela estados:\n";
    $stmt = $db->query("DESCRIBE estados");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($colunas as $coluna) {
        echo "  - " . $coluna['Field'] . " (" . $coluna['Type'] . ")\n";
    }
    echo "\n";
    
    // Verificar alguns registros da tabela estados
    echo "Primeiros 5 registros da tabela estados:\n";
    $stmt = $db->query("SELECT * FROM estados LIMIT 5");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($estados as $estado) {
        echo "  - ID: " . $estado['id'] . " | ";
        foreach ($estado as $campo => $valor) {
            if ($campo != 'id') {
                echo $campo . ": " . $valor . " | ";
            }
        }
        echo "\n";
    }
    echo "\n";
    
    // Verificar estrutura da tabela cidades
    echo "Estrutura da tabela cidades:\n";
    $stmt = $db->query("DESCRIBE cidades");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($colunas as $coluna) {
        echo "  - " . $coluna['Field'] . " (" . $coluna['Type'] . ")\n";
    }
    echo "\n";
    
    // Verificar alguns registros da tabela cidades
    echo "Primeiros 5 registros da tabela cidades:\n";
    $stmt = $db->query("SELECT * FROM cidades LIMIT 5");
    $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cidades as $cidade) {
        echo "  - ID: " . $cidade['id'] . " | ";
        foreach ($cidade as $campo => $valor) {
            if ($campo != 'id') {
                echo $campo . ": " . $valor . " | ";
            }
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
?> 