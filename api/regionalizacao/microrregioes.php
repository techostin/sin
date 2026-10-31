<?php
/**
 * API: Microrregiões por Mesorregião
 */
// Caminho corrigido (antes apontava para fora do app e o endpoint falhava).
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';

header('Content-Type: application/json');

// Dados de referência usados por páginas autenticadas.
ApiAuth::exigirLogin();

$mesorregiaoId = isset($_GET['mesorregiao_id']) ? (int) $_GET['mesorregiao_id'] : 0;

if ($mesorregiaoId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Mesorregião não informada']);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, nome FROM microrregioes_rs WHERE mesorregiao_id = ? ORDER BY nome");
$stmt->execute([$mesorregiaoId]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['sucesso' => true, 'dados' => $dados]);