<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    echo "Templates: " . $db->query("SELECT COUNT(*) FROM roteiros_inspecao")->fetchColumn() . "\n";
    echo "Associados: " . $db->query("SELECT COUNT(*) FROM associados WHERE status = 'ativo'")->fetchColumn() . "\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
