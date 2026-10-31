<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/MicrorregiaoRS.php';
$mr = new MicrorregiaoRS();
$dados = $mr->getDadosMapaCalor();
echo 'Total microrregioes com dados: ' . count($dados) . "\n";
foreach ($dados as $d) {
    echo $d['mesorregiao'] . ' / ' . $d['microrregiao'] . ': ' . $d['qtd_associados'] . ' associados, ' . $d['percentual_inadimplencia'] . '% inad' . "\n";
}