<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Certificações existentes ===\n";
$stmt = $db->query("SELECT id, associado_id, tipo_certificacao, numero_certificado, organismo_certificador, nome_grupo, status, data_emissao, data_validade FROM certificacao_participativa");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Certificadoras com logos ===\n";
$stmt2 = $db->query("SELECT id, nome, sigla, logo_filename, logo_url FROM certificadoras WHERE logo_filename IS NOT NULL OR logo_url IS NOT NULL");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Arquivos em uploads/certificadoras/ ===\n";
$dir = __DIR__ . '/uploads/certificadoras/';
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            echo $f . "\n";
        }
    }
} else {
    echo "Diretório não existe\n";
}
