<?php
/**
 * API: Pré-cadastro de novo usuário (primeiro acesso)
 * Salva o associado com status='pendente' e retorna JSON.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

$nome       = trim($_POST['nome']       ?? '');
$sobrenome  = trim($_POST['sobrenome']  ?? '');
$email      = trim($_POST['email']      ?? '');
$senha      = $_POST['senha']           ?? '';
$confirma   = $_POST['confirma_senha']  ?? '';
$tipoUser   = trim($_POST['tipo_usuario'] ?? 'usuario');

// Tipo de usuário solicitado (usuario | gestor)
$tipoUser = in_array($tipoUser, ['usuario', 'gestor'], true) ? $tipoUser : 'usuario';

// Validações
$errors = [];
if (empty($nome))                          $errors[] = 'O nome é obrigatório.';
if (empty($sobrenome))                     $errors[] = 'O sobrenome é obrigatório.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                           $errors[] = 'Informe um e-mail válido.';
if (strlen($senha) < 6)                   $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
if ($senha !== $confirma)                  $errors[] = 'As senhas não coincidem.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verificar e-mail duplicado em associados e usuarios
    $stmtChk = $db->prepare("SELECT COUNT(*) FROM associados WHERE email = :e");
    $stmtChk->execute([':e' => $email]);
    if ($stmtChk->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }
    $stmtChk2 = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :e");
    $stmtChk2->execute([':e' => $email]);
    if ($stmtChk2->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Define nível e tipo de acordo com o perfil solicitado
    $nivelCadastro = ($tipoUser === 'gestor') ? NIVEL_GESTOR : NIVEL_USUARIO;
    $tipoCadastro  = ($tipoUser === 'gestor') ? 'Gestor' : 'Usuario';

    $stmt = $db->prepare("
        INSERT INTO associados
            (nome, sobrenome, email, senha, nivel, tipo, status, data_cadastro)
        VALUES
            (:nome, :sobrenome, :email, :senha, :nivel, :tipo, 'pendente', NOW())
    ");
    $stmt->execute([
        ':nome'      => $nome,
        ':sobrenome' => $sobrenome,
        ':email'     => $email,
        ':senha'     => $senha_hash,
        ':nivel'     => $nivelCadastro,
        ':tipo'      => $tipoCadastro,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado! Aguarde a ativação pelo administrador para acessar o sistema.',
    ]);

} catch (Exception $e) {
    error_log("Erro pré-cadastro: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno. Tente novamente em instantes.']);
}
