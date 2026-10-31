<?php
require 'classes/Database.php';
$db = Database::getInstance()->getConnection();
foreach (['propriedades','agroindustrias','ambiente_criacao_animal'] as $t) {
    $r = $db->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_ASSOC);
    echo "== $t ==\n";
    // pegar só campos de identificação
    preg_match('/`id` int[^\n]*|`propriedade` varchar[^\n]*|`nome_fantasia` varchar[^\n]*|`razao_social` varchar[^\n]*|`nome_instalacao` varchar[^\n]*|`associado_id` int[^\n]*|`excluido` [^\n]*/', $r['Create Table'], $m);
    echo $m[0] ?? '?\n';
    echo "\n";
}
echo "== User::areaProducao ==\n";