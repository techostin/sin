<?php
/**
 * Teste final: simula a requisição PUT da API para alterar status para 'Em Análise'
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== TESTE FINAL: ATUALIZAÇÃO DE STATUS ===" . PHP_EOL . PHP_EOL;

// Estado inicial
$stmt = $db->prepare("SELECT id, status FROM plano_manejo WHERE id = 1");
$stmt->execute();
$antes = $stmt->fetch();
echo "Antes:  id=1 status=[{$antes['status']}]" . PHP_EOL;

// Simular o que handlePut faz
$statusInput = 'Em Análise'; // O que o frontend envia

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

    return $map[$key] ?? $normalized;
}

$statusFinal = normalizeStatusValue($statusInput);
echo "normalizeStatusValue('Em Análise') => '$statusFinal'" . PHP_EOL;

// Executar UPDATE como a API faria
$updateStmt = $db->prepare("UPDATE plano_manejo SET status = ?, updated_at = NOW() WHERE id = ?");
$updateStmt->execute([$statusFinal, 1]);

echo "Rows affected: " . $updateStmt->rowCount() . PHP_EOL;

// Verificar resultado
$stmt2 = $db->prepare("SELECT id, status FROM plano_manejo WHERE id = 1");
$stmt2->execute();
$depois = $stmt2->fetch();
echo "Depois: id=1 status=[{$depois['status']}]" . PHP_EOL;

echo PHP_EOL;
if ($depois['status'] === 'Em Análise') {
    echo "✅ SUCESSO: status salvo corretamente como 'Em Análise'" . PHP_EOL;
} else {
    echo "❌ FALHA: status deveria ser 'Em Análise', mas é '{$depois['status']}'" . PHP_EOL;
}

// Testar todos os valores
echo PHP_EOL . "=== Testando todos os valores do ENUM ===" . PHP_EOL;
$testStatuses = ['Rascunho', 'Em Análise', 'Aprovado', 'Reprovado', 'Revisão Necessária'];
foreach ($testStatuses as $s) {
    $upd = $db->prepare("UPDATE plano_manejo SET status = ? WHERE id = 1");
    $upd->execute([$s]);
    $chk = $db->prepare("SELECT status FROM plano_manejo WHERE id = 1");
    $chk->execute();
    $saved = $chk->fetchColumn();
    $ok = ($saved === $s) ? "✅" : "❌";
    echo "  $ok '$s' -> salvo: '$saved'" . PHP_EOL;
}

// Restaurar para Rascunho
$db->prepare("UPDATE plano_manejo SET status = 'Rascunho' WHERE id = 1")->execute();
echo PHP_EOL . "Plano 1 restaurado para 'Rascunho'." . PHP_EOL;
?>
