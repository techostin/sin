<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("DESCRIBE insumos");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        echo $field['Field'] . " (" . $field['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
