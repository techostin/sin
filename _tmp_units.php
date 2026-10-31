<?php
require 'classes/Database.php';
$db = Database::getInstance()->getConnection();
echo "== unidade tables sample ==\n";
foreach (['propriedades','agroindustrias','ambiente_criacao_animal'] as $t) {
    try {
        $q = $db->query("SELECT COUNT(*) FROM $t");
        echo "  $t: " . $q->fetchColumn() . "\n";
    } catch (Exception $e) { echo "  $t: ERRO\n"; }
}