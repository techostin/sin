<?php
require 'config/config.php';
require 'classes/Database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE estados');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) echo $col['Field'].' ';
