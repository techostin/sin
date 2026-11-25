<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Verificar os primeiros 5 dígitos dos códigos IBGE dos municípios
$stmt = $db->query("
    SELECT c.ibge, c.nome, FLOOR(c.ibge / 100) as prefixo_5
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS'
    ORDER BY c.ibge
");
$prefixos = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $prefixos[$row['prefixo_5']][] = $row['nome'];
}

echo "Prefixos de 5 dígitos únicos: " . count($prefixos) . "\n\n";
foreach ($prefixos as $prefixo => $cidades) {
    echo "$prefixo: " . implode(', ', array_slice($cidades, 0, 5)) . (count($cidades) > 5 ? " ... (+".(count($cidades)-5).")" : "") . "\n";
}