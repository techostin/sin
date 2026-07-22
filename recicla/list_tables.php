<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SHOW TABLES');
    echo implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo $e->getMessage();
}
