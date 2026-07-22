<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// Plano id=1
$stmt = $db->prepare("SELECT id, status, titulo FROM plano_manejo WHERE id = 1");
$stmt->execute();
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "PLANO 1: " . json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Schema da coluna status
$stmt2 = $db->query("SHOW COLUMNS FROM plano_manejo LIKE 'status'");
$col = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "COLUNA STATUS: " . json_encode($col, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Simular o normalize que a API faz
function normalizeStatusValue($value) {
    if ($value === null) return null;
    $normalized = trim((string) $value);
    if ($normalized === '') return null;

    $map = [
        'em análise'         => 'Em Análise',
        'em analise'         => 'Em Análise',
        'em-analise'         => 'Em Análise',
        'em_analise'         => 'Em Análise',
        'emanalise'          => 'Em Análise',
        'rascunho'           => 'Rascunho',
        'aprovado'           => 'Aprovado',
        'reprovado'          => 'Reprovado',
        'revisao necessaria' => 'Revisão Necessária',
        'revisão necessária' => 'Revisão Necessária',
        'revisao-necessaria' => 'Revisão Necessária',
        'revisao_necessaria' => 'Revisão Necessária',
    ];

    $key = mb_strtolower($normalized, 'UTF-8');
    $key = str_replace(['_', '-'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);
    $key = trim($key);

    echo "  normalizeStatusValue('" . $normalized . "') => key='" . $key . "' => '" . ($map[$key] ?? $normalized) . "'" . PHP_EOL;
    return $map[$key] ?? $normalized;
}

echo PHP_EOL . "=== Teste de normalização ===" . PHP_EOL;
$testValues = ['Em Análise', 'em análise', 'em analise', 'Em An\u00e1lise', 'Rascunho'];
foreach ($testValues as $v) {
    normalizeStatusValue($v);
}

// Simular o que acontece quando o formulário envia status='Em Análise'
echo PHP_EOL . "=== Simulação do PUT com status='Em Análise' ===" . PHP_EOL;
$inputStatus = 'Em Análise';
$statusFinal = normalizeStatusValue($inputStatus);
echo "statusFinal: " . $statusFinal . PHP_EOL;

// Verificar se o campo está no ENUM ou VARCHAR
$stmt3 = $db->query("SHOW CREATE TABLE plano_manejo");
$create = $stmt3->fetch(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== CREATE TABLE (trecho status) ===" . PHP_EOL;
$tableCreate = $create['Create Table'] ?? '';
// Extrair apenas a linha do status
$lines = explode("\n", $tableCreate);
foreach ($lines as $line) {
    if (stripos($line, 'status') !== false) {
        echo trim($line) . PHP_EOL;
    }
}
?>
