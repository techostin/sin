<?php
/**
 * Webhook Asaas - Recebe notificações de pagamento PIX
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Mensalidade.php';
require_once __DIR__ . '/../../../classes/Database.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido']);
    exit;
}

// Asaas envia evento "payment_received" ou "payment_confirmed"
// O txid está em data.id ou data.externalReference
$txid = $data['payment']['id'] ?? $data['payment']['externalReference'] ?? '';

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Txid não encontrado no payload']);
    exit;
}

$model = new Mensalidade();
$result = $model->processarWebhookPix($txid, $data);

if (isset($result['erro'])) {
    error_log("Webhook Asaas erro: " . $result['erro'] . " | txid: $txid");
    http_response_code(400);
    echo json_encode($result);
    exit;
}

echo json_encode($result);