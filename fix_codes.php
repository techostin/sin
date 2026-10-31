<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Corrigir códigos IBGE das microrregiões
$sql = file_get_contents('sql/fix_microrregiao_codes.sql');
$db->exec($sql);
echo "Códigos IBGE das microrregiões corrigidos!\n";

// Verificar
$stmt = $db->query("SELECT id, nome, codigo_ibge FROM microrregioes_rs ORDER BY id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']}, {$row['nome']}: {$row['codigo_ibge']}\n";
}