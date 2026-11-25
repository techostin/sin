<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

$tables = ['associados', 'cidades', 'estados'];
foreach ($tables as $table) {
    echo "=== $table ===\n";
    $stmt = $db->query("SHOW COLUMNS FROM $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Key'] . "\n";
    }
    echo "\n";
}