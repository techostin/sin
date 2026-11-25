<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Verificar estados dos associados
$stmt = $db->query("
    SELECT estado, COUNT(*) as qtd 
    FROM associados 
    GROUP BY estado 
    ORDER BY qtd DESC
");
echo "Estados dos associados:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  '{$row['estado']}': {$row['qtd']}\n";
}

// Verificar alguns associados
$stmt = $db->query("SELECT id, nome, sobrenome, cidade, estado FROM associados LIMIT 20");
echo "\nExemplos:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, {$row['nome']} {$row['sobrenome']}, Cidade: {$row['cidade']}, Estado: '{$row['estado']}'\n";
}