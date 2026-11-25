<?php
/**
 * Webhook Mercado Pago - Recebe notificações de pagamento PIX
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Mensalidade.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['erro' => 'JSON inválido']);
    exit;
}

// Mercado Pago envia "data.id" (payment_id) e "type" (payment)
$txid = $data['data']['id'] ?? $data['data']['external_reference'] ?? '';

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Txid não encontrado no payload']);
    exit;
}

$model = new Mensalidade();
$result = $model->processarWebhookPix($txid, $data);

if (isset($result['erro'])) {
    error_log("Webhook Mercado Pago erro: " . $result['erro'] . " | txid: $txid");
    http_response_code(400);
    echo json_encode($result);
    exit;
}

echo json_encode($result);