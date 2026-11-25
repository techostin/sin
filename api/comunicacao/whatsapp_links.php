<?php
/**
 * API: Links WhatsApp por Cluster
 */
// Caminho corrigido (antes apontava para fora do app e o endpoint falhava).
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';

header('Content-Type: application/json');

// Lista nomes/telefones de associados por cluster: somente equipe interna.
ApiAuth::exigirStaff();

if (!CSRFProtection::validatePostRequest()) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token CSRF inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$cluster = $input['cluster'] ?? '';

if (empty($cluster)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Cluster não informado']);
    exit;
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT a.nome, a.sobrenome, a.telefone
    FROM associados a
    WHERE a.estado = 23
      AND a.cluster_comunicacao = ?
      AND a.status = 'ativo'
      AND a.telefone IS NOT NULL AND a.telefone != ''
");
$stmt->execute([$cluster]);
$associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$links = [];
foreach ($associados as $a) {
    $telefone = preg_replace('/\D/', '', $a['telefone']);
    if ($telefone) {
        $msg = urlencode("Olá {$a['nome']},\n\nMensagem da associação para o cluster {$cluster}.\n\n--\nSINPORG");
        $links["{$a['nome']} {$a['sobrenome']}"] = "https://wa.me/55{$telefone}?text={$msg}";
    }
}

echo json_encode(['sucesso' => true, 'links' => $links]);