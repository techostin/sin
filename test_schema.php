<?php
require 'config/config.php';
require 'classes/Database.php';
$db = Database::getInstance()->getConnection();
$tables = ['contas'];
foreach($tables as $t) {
    echo "Table: $t\n";
    try {
        $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach($cols as $c) {
            echo "  - {$c['Field']} ({$c['Type']})\n";
        }
    } catch(Exception $e) {
        echo "  Missing table\n";
    }
    echo "\n";
}
