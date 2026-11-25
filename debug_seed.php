<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Verificar microrregioes_rs
$stmt = $db->query("SELECT * FROM microrregioes_rs ORDER BY mesorregiao_id, id");
echo "Microrregiões:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, Mesorregião: {$row['mesorregiao_id']}, Nome: {$row['nome']}, IBGE: {$row['codigo_ibge']}\n";
}

// Verificar cidades do RS sem microrregiao
$stmt = $db->query("
    SELECT c.id, c.nome, c.ibge, c.estado, e.uf
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS' AND c.microrregiao_id IS NULL
    LIMIT 20
");
echo "\nCidades RS sem microrregiao (primeiras 20):\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, Nome: {$row['nome']}, IBGE: {$row['ibge']}, Estado ID: {$row['estado']}, UF: {$row['uf']}\n";
}

// Verificar um exemplo de join
$stmt = $db->query("
    SELECT c.id, c.nome, c.ibge, FLOOR(c.ibge / 1000) as microrregiao_codigo
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS'
    LIMIT 10
");
echo "\nExemplo de códigos IBGE das cidades:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['nome']}: IBGE={$row['ibge']}, MicroRegiaoCode={$row['microrregiao_codigo']}\n";
}