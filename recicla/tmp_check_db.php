<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("DESCRIBE insumos");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($fields);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
