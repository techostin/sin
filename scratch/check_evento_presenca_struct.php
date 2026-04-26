<?php
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "--- EVENTO_PRESENCA STRUCTURE ---\n";
    $stmt = $db->query("SHOW CREATE TABLE evento_presenca");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
