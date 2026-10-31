<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Verificar duplicidade de CPF
$stmt = $db->query("
    SELECT cpf, COUNT(*) as qtd 
    FROM associados 
    WHERE cpf IS NOT NULL AND cpf != ''
    GROUP BY cpf 
    HAVING COUNT(*) > 1
");
$duplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "CPFs duplicados: " . count($duplicados) . "\n";
foreach ($duplicados as $row) {
    echo "  CPF: {$row['cpf']} - {$row['qtd']} ocorrências\n";
}

// Verificar índice único
$stmt = $db->query("SHOW INDEX FROM associados WHERE Key_name = 'cpf' OR Column_name = 'cpf'");
echo "\nÍndices no CPF:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['Key_name']} - {$row['Column_name']} - Non_unique: {$row['Non_unique']}\n";
}