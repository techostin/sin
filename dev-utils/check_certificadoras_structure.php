<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Estrutura da tabela certificadoras ===\n";
$stmt = $db->query('SHOW COLUMNS FROM certificadoras');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Dados das certificadoras ===\n";
$stmt2 = $db->query('SELECT id, nome, gestor_id, logo_filename, logo_url, cidade, uf FROM certificadoras');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Estrutura da tabela usuarios ===\n";
$stmt3 = $db->query('SHOW COLUMNS FROM usuarios');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Estrutura da tabela certificacao_participativa ===\n";
$stmt4 = $db->query('SHOW COLUMNS FROM certificacao_participativa');
print_r($stmt4->fetchAll(PDO::FETCH_ASSOC));
