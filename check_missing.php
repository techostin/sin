<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Verificar quais cidades ainda não têm microrregiao
$stmt = $db->query("
    SELECT c.id, c.nome, c.ibge 
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS' AND c.microrregiao_id IS NULL
    ORDER BY c.nome
");
echo "Cidades RS SEM microrregiao_id (" . $stmt->rowCount() . "):\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, {$row['nome']}, IBGE: {$row['ibge']}\n";
}