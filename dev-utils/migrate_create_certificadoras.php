<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $sql = file_get_contents(__DIR__ . '/sql/create_certificadoras.sql');
    $db->exec($sql);
    echo "Migration executada com sucesso.\n";
} catch (Exception $e) {
    echo "Erro na migration: " . $e->getMessage() . "\n";
}
?>