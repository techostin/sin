<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW COLUMNS FROM nao_conformidades");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
