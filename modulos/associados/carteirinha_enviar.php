<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/CarteirinhaImpressa.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/associados');
    exit;
}
$id = (int) $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
    exit;
}
if (!CSRFProtection::validatePostRequest()) {
    $_SESSION['error_message'] = 'Falha de segurança (token). Tente novamente.';
    header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
    exit;
}

$carteirinha = new CarteirinhaImpressa();
$dados = $carteirinha->getDados($id);
if (!empty($dados['erro'])) {
    $_SESSION['error_message'] = $dados['erro'];
    header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
    exit;
}

$nome = trim(($dados['associado']['nome'] ?? '') . ' ' . ($dados['associado']['sobrenome'] ?? ''));
$email = $dados['associado']['email'] ?? '';
if (empty($email)) {
    $_SESSION['error_message'] = 'Associado sem e-mail cadastrado para envio.';
    header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
    exit;
}

$pdfBytes = $carteirinha->gerarPdf($dados);
if ($pdfBytes === null) {
    $_SESSION['error_message'] = 'Erro ao gerar o PDF da carteirinha.';
    header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
    exit;
}

$html = '
<p>Olá, <strong>' . htmlspecialchars($nome) . '</strong>!</p>
<p>Segue anexa a sua <strong>carteirinha de associado</strong>.</p>
<p>Ela contém os seus dados cadastrais e a situação atual junto à associação.</p>
<p>Conserve este documento e apresente-o quando necessário. Caso precise de uma cópia, os dados também podem ser verificados pelo QR Code.</p>
<p>Em caso de dúvidas, procure a secretaria da associação.</p>';

$resultado = Mailer::send(
    $email,
    '[' . (defined('SITE_TITLE') ? SITE_TITLE : 'SINPORG') . '] Sua carteirinha de associado',
    Mailer::layout('Sua Carteirinha de Associado', $html),
    null,
    [
        [
            'content' => base64_encode($pdfBytes),
            'filename' => 'carteirinha_' . $id . '.pdf',
            'content_type' => 'application/pdf',
        ],
    ]
);

if ($resultado['success']) {
    $_SESSION['success_message'] = 'Carteirinha enviada por e-mail para ' . $nome . ' (' . $email . ').';
} else {
    $_SESSION['error_message'] = 'Erro ao enviar o e-mail: ' . ($resultado['error'] ?? 'erro desconhecido');
}

header('Location: ' . SITE_URL . '/associado/' . $id . '/ficha');
exit;