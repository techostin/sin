<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM entidades LIMIT 1");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        print_r($res);
    } else {
        echo "No data in entidades table.\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
