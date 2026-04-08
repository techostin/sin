<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM contas LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Contas row keys: " . implode(", ", array_keys($row)) . "\n";
        print_r($row);
    } else {
        echo "No data in contas table.\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
