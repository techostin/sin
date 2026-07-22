<?php
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM evento_presenca ORDER BY id DESC LIMIT 20");
    echo "--- EVENTO_PRESENCA (Last 20) ---\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
