<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

$db = Database::getInstance()->getConnection();

// Verificar estrutura da tabela atividades_diarias
$query = "SHOW COLUMNS FROM atividades_diarias";
$stmt = $db->query($query);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Estrutura da tabela atividades_diarias:</h2>";
echo "<pre>";
print_r($columns);
echo "</pre>";

// Verificar se há dados na tabela
$query = "SELECT * FROM atividades_diarias LIMIT 1";
try {
    $stmt = $db->query($query);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Exemplo de dados:</h2>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar dados: " . $e->getMessage() . "</div>";
}
?>
