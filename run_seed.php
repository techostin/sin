<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Executar seed de microrregiões
$sql = file_get_contents('sql/seed_meso_microrregioes_rs.sql');
$db->exec($sql);
echo "Seed executado com sucesso!\n";

// Verificar resultados
$stmt = $db->query("SELECT COUNT(*) as total FROM cidades WHERE estado = (SELECT id FROM estados WHERE uf = 'RS')");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total cidades RS: {$row['total']}\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM cidades WHERE estado = (SELECT id FROM estados WHERE uf = 'RS') AND microrregiao_id IS NOT NULL");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Cidades com microrregiao: {$row['total']}\n";

$stmt = $db->query("SELECT COUNT(*) as total FROM associados WHERE estado = 'RS' AND microrregiao_id IS NOT NULL");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Associados RS com microrregiao: {$row['total']}\n";

// Mostrar distribuição por mesorregião
$stmt = $db->query("
    SELECT mr.nome as mesorregiao, COUNT(DISTINCT c.id) as cidades, COUNT(DISTINCT a.id) as associados
    FROM microrregioes_rs mi
    INNER JOIN mesorregioes_rs mr ON mr.id = mi.mesorregiao_id
    LEFT JOIN cidades c ON c.microrregiao_id = mi.id
    LEFT JOIN associados a ON a.microrregiao_id = mi.id
    GROUP BY mr.id, mr.nome
    ORDER BY mr.nome
");
echo "\nDistribuição por mesorregião:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['mesorregiao']}: {$row['cidades']} cidades, {$row['associados']} associados\n";
}