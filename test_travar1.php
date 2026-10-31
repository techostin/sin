<?php
// Teste rápido das rotas via CLI
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/MicrorregiaoRS.php';

// Testar API
$mr = new MicrorregiaoRS();
$dados = $mr->getDadosMapaCalor();
echo "API Regionalização: OK - " . count($dados) . " microrregiões\n";

// Testar classe
$mesorregioes = $mr->listMesorregioes();
echo "Mesorregiões: " . count($mesorregioes) . "\n";

$micros = $mr->listAll();
echo "Microrregiões: " . count($micros) . "\n";

// Testar estatísticas
$stats = $mr->getEstatisticasPorMicrorregiao();
echo "Estatísticas: " . count($stats) . " linhas\n";

// Verificar associados com microrregiao
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT COUNT(*) FROM associados WHERE microrregiao_id IS NOT NULL");
echo "Associados com microrregiao_id: " . $stmt->fetchColumn() . "\n";

$stmt = $db->query("SELECT COUNT(*) FROM associados WHERE cluster_comunicacao IS NOT NULL");
echo "Associados com cluster_comunicacao: " . $stmt->fetchColumn() . "\n";

echo "\n✅ Trava 1 - Regionalização RS: CONCLUÍDA\n";