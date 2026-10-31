<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';
require_once __DIR__ . '/../../classes/CarteirinhaImpressa.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
// Impressão/PDF: Admin/Gestor OU membro da Diretoria (somente leitura)
// PDF com dado pessoal do associado: exige login e devolve 403 em vez de
// conteúdo para quem não é Admin/Gestor nem o próprio dono da carteirinha.
ApiAuth::exigirLogin(false);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    ApiAuth::negar(400, 'Associado não informado.', false);
}
$id = (int) $_GET['id'];

// Anti-IDOR: o id é o associado_id da carteirinha (nível 3 só o próprio).
ApiAuth::exigirProprioAssociado($id, false);

$carteirinha = new CarteirinhaImpressa();
$dados = $carteirinha->getDados($id);
if (!empty($dados['erro'])) {
    die($dados['erro']);
}

$pdfBytes = $carteirinha->gerarPdf($dados);
if ($pdfBytes === null) {
    die('Erro ao gerar o PDF da carteirinha.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="carteirinha_' . $id . '.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
echo $pdfBytes;
exit;