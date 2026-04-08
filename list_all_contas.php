<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM contas ORDER BY id ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total rows: " . count($rows) . "\n";
    print_r($rows);
} catch (Exception $e) {
    echo $e->getMessage();
}
