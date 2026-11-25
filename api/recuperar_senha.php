<?php
/**
 * API: Solicita recuperação de senha.
 *
 * Recebe o e-mail, valida se existe uma conta ativa e envia um link de
 * redefinição por e-mail (Resend). Responde de forma genérica para não
 * revelar quais e-mails estão cadastrados.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/PasswordReset.php';
require_once __DIR__ . '/../classes/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

$mensagemGenerica = 'Se este e-mail estiver cadastrado, você receberá um link para redefinir sua senha em instantes. Verifique também a caixa de spam.';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Informe um e-mail válido.']);
    exit;
}

try {
    $reset = new PasswordReset();
    $user  = $reset->findUserByEmail($email);

    // Independe de a conta existir: sempre responde a mensagem genérica
    if (!$user) {
        echo json_encode(['success' => true, 'message' => $mensagemGenerica]);
        exit;
    }

    $token = $reset->createToken($email);
    $link  = SITE_URL . '/redefinir-senha/' . $token;

    $titulo      = 'Recuperação de senha';
    $htmlConteudo =
        '<p>Olá, <strong>' . htmlspecialchars($user['nome']) . '</strong>!</p>' .
        '<p>Recebemos uma solicitação para redefinir a senha da sua conta. Clique no botão abaixo para definir uma nova senha:</p>' .
        '<p style="text-align:center;margin:26px 0;">' .
            '<a href="' . $link . '" style="display:inline-block;background:#198754;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:10px;font-weight:700;">Redefinir minha senha</a>' .
        '</p>' .
        '<p>Este link é válido por <strong>60 minutos</strong>. Se o link expirar, solicite uma nova recuperação na tela de login.</p>' .
        '<p style="color:#6b7280;font-size:13px;">Ou copie e cole este endereço no navegador:<br>' . $link . '</p>';

    $resultado = Mailer::send($user['email'], '[' . SITE_TITLE . '] ' . $titulo, Mailer::layout($titulo, $htmlConteudo));

    if (!$resultado['success']) {
        // Envio falhou (ex: API key ainda não configurada) — registra o link para fins de teste
        error_log("Recuperação de senha ({$user['email']}): " . ($resultado['error'] ?? 'erro desconhecido') . " | LINK: {$link}");
        // Para não frustrar o usuário final, trata como falha de envio
        echo json_encode([
            'success' => false,
            'error'   => 'Não foi possível enviar o e-mail neste momento. Tente novamente mais tarde ou contate o suporte.',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => $mensagemGenerica]);
    exit;

} catch (Exception $e) {
    error_log("Erro recuperação de senha: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno. Tente novamente em instantes.']);
    exit;
}