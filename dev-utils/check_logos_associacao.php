<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Arquivos de logo em uploads/ ===\n";
$files = glob(__DIR__ . '/uploads/*.{jpeg,jpg,png,gif,svg,webp}', GLOB_BRACE);
foreach ($files as $f) {
    echo basename($f) . " (" . round(filesize($f)/1024) . " KB)\n";
}

echo "\n=== Certificações com associação a usuários ===\n";
$sql = "SELECT c.id as cert_id, c.tipo_certificacao, c.organismo_certificador, c.nome_grupo, 
               a.id as assoc_id, a.nome as assoc_nome,
               u.id as user_id, u.certificadora_id as user_cert_id
        FROM certificacao_participativa c
        LEFT JOIN associados a ON c.associado_id = a.id
        LEFT JOIN usuarios u ON u.associado_id = a.id
        ORDER BY c.id";
$stmt = $db->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Colunas de associados (cert) ===\n";
$stmt2 = $db->query("SHOW COLUMNS FROM associados LIKE '%cert%'");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Colunas de associados ===\n";
$stmt3 = $db->query("SHOW COLUMNS FROM associados");
$cols = $stmt3->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . "\n";
}
