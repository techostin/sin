<?php
require 'c:\xampp\htdocs\app\config\config.php';
require 'c:\xampp\htdocs\app\classes\Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW CREATE TABLE contas");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $res['Create Table'];
} catch (Exception $e) {
    echo $e->getMessage();
}
