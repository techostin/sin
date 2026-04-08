<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    foreach(['mensalidades', 'pagamentos'] as $t) {
        $stmt = $db->query("DESCRIBE $t");
        echo "Table: $t\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "\n---\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
