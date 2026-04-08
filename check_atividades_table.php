<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar colunas da tabela atividades_diarias
    $stmt = $db->query("SHOW COLUMNS FROM atividades_diarias");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas da tabela 'atividades_diarias':\n";
    print_r($columns);
    
    // Verificar categorias de atividades
    $stmt = $db->query("SELECT * FROM categorias_atividades LIMIT 5");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nExemplo de categorias de atividades:\n";
    print_r($categorias);
    
} catch (PDOException $e) {
    echo "Erro ao verificar estrutura do banco de dados: " . $e->getMessage();
}
?>
