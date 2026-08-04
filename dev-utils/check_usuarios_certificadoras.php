<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Usuários com certificadora_id ===\n";
$stmt = $db->query("SELECT u.id, u.nome, u.email, u.nivel, u.tipo, u.certificadora_id, c.nome AS certificadora_nome, c.logo_filename, c.logo_url 
                    FROM usuarios u 
                    LEFT JOIN certificadoras c ON u.certificadora_id = c.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Arquivos em uploads/ ===\n";
$files = glob(__DIR__ . '/uploads/*.{jpeg,jpg,png,gif,svg,webp}', GLOB_BRACE);
foreach ($files as $f) {
    echo basename($f) . "\n";
}
