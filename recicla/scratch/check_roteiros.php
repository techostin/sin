<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM roteiros_inspecao");
$stmt->execute();
echo "<pre>";
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
?>