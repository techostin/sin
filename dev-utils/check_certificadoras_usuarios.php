<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Certificadoras ===\n";
$stmt = $db->query("SELECT id, nome, sigla, tipo, registro_mapa, logo_filename, logo_url, gestor_id, cidade, uf FROM certificadoras ORDER BY id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Usuários (certificadora_id) ===\n";
$stmt2 = $db->query("SELECT id, nome, email, nivel, certificadora_id FROM usuarios ORDER BY id");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Sessão atual (se disponível) ===\n";
if (session_status() === PHP_SESSION_NONE) session_start();
print_r($_SESSION);

echo "\n=== Arquivos uploads/certificadoras ===\n";
$dir = __DIR__ . '/uploads/certificadoras';
if (is_dir($dir)) {
    print_r(scandir($dir));
} else {
    echo "Diretório não existe\n";
}
