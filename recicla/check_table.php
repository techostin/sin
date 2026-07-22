<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar colunas da tabela associados
    $stmt = $db->query("SHOW COLUMNS FROM associados");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas da tabela 'associados':\n";
    print_r($columns);
    
    // Verificar se a tabela associacoes existe
    $stmt = $db->query("SHOW TABLES LIKE 'associacoes'");
    $hasAssociacoes = $stmt->rowCount() > 0;
    
    echo "\nTabela 'associacoes' existe? " . ($hasAssociacoes ? 'Sim' : 'Não') . "\n";
    
    // Se existir, mostrar suas colunas
    if ($hasAssociacoes) {
        $stmt = $db->query("SHOW COLUMNS FROM associacoes");
        $associacoesColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "\nColunas da tabela 'associacoes':\n";
        print_r($associacoesColumns);
    }
    
} catch (PDOException $e) {
    echo "Erro ao verificar estrutura do banco de dados: " . $e->getMessage();
}
?>
