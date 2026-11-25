<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/CarteirinhaImpressa.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

session_start();

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . '/associados/carteirinhas');
    exit;
}
if (!CSRFProtection::validatePostRequest()) {
    $_SESSION['error_message'] = 'Falha de segurança (token). Tente novamente.';
    header('Location: ' . SITE_URL . '/associados/carteirinhas');
    exit;
}

$ids = array_filter(array_map('intval', (array) ($_POST['associados'] ?? [])));
if (empty($ids)) {
    $_SESSION['error_message'] = 'Nenhum associado selecionado.';
    header('Location: ' . SITE_URL . '/associados/carteirinhas');
    exit;
}

$carteirinha = new CarteirinhaImpressa();
$associadoModel = new Associado();

$enviados = 0;
$semEmail = [];
$comErro = [];

foreach ($ids as $id) {
    $assoc = $associadoModel->getById($id);
    if (!$assoc) {
        continue;
    }
    $email = trim((string) ($assoc['email'] ?? ''));
    if (empty($email)) {
        $semEmail[] = trim(($assoc['nome'] ?? '') . ' ' . ($assoc['sobrenome'] ?? ''));
        continue;
    }

    $dados = $carteirinha->getDados($id);
    if (!empty($dados['erro'])) {
        $comErro[] = trim(($assoc['nome'] ?? '') . ' ' . ($assoc['sobrenome'] ?? ''));
        continue;
    }

    $pdfBytes = $carteirinha->gerarPdf($dados);
    if ($pdfBytes === null) {
        $comErro[] = trim(($assoc['nome'] ?? '') . ' ' . ($assoc['sobrenome'] ?? ''));
        continue;
    }

    $nome = trim(($assoc['nome'] ?? '') . ' ' . ($assoc['sobrenome'] ?? ''));
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
        $enviados++;
    } else {
        $comErro[] = $nome . ' (' . $email . ')';
    }
}

$partes = ["{$enviados} carteirinha(s) enviada(s) por e-mail."];
if ($semEmail) {
    $partes[] = count($semEmail) . ' sem e-mail: ' . implode(', ', array_slice($semEmail, 0, 5)) . (count($semEmail) > 5 ? '...' : '');
}
if ($comErro) {
    $partes[] = count($comErro) . ' com erro: ' . implode(', ', array_slice($comErro, 0, 5)) . (count($comErro) > 5 ? '...' : '');
}

if ($enviados > 0) {
    $_SESSION['success_message'] = implode(' ', $partes);
} else {
    $_SESSION['error_message'] = 'Nenhuma carteirinha enviada. ' . implode(' ', $partes);
}

header('Location: ' . SITE_URL . '/associados/carteirinhas');
exit;