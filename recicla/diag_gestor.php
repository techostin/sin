<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== TABELA USUARIOS ===\n";
foreach ($db->query('SELECT * FROM usuarios ORDER BY id') as $r) {
    print_r($r);
}

echo "\n=== TABELA ASSOCIADOS ===\n";
foreach ($db->query('SELECT * FROM associados ORDER BY id') as $r) {
    print_r($r);
}

echo "\n=== ESTRUTURA TABELA USUARIOS ===\n";
foreach ($db->query('SHOW COLUMNS FROM usuarios') as $r) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | null=' . $r['Null'] . " | default=" . ($r['Default'] ?? 'NULL') . "\n";
}

echo "\n=== ESTRUTURA TABELA ASSOCIADOS ===\n";
foreach ($db->query('SHOW COLUMNS FROM associados') as $r) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | null=' . $r['Null'] . " | default=" . ($r['Default'] ?? 'NULL') . "\n";
}

