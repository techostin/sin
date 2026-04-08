<?php
require_once 'config/config.php';
require_once 'classes/User.php';

$user = new User();
$error_message = '';

// Se o usuário já estiver logado, redireciona para o dashboard
if ($user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // A tabela associados tem a senha em hash?
    // O script SQL mostra senhas em texto plano. Precisamos ajustar isso.
    // Por agora, vamos assumir que a senha não está hasheada para o login funcionar.
    // Idealmente, a senha deveria ser verificada com password_verify.

    if ($user->login($email, $senha)) {
        // Definir flag para mostrar o modal de eventos
        $_SESSION['mostrar_modal_eventos'] = true;
        
        // Usar o tipo salvo na sessão
        if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario') {
            header("Location: " . SITE_URL . "/caderno-campo/dashboard");
            exit;
        } else {
            header("Location: " . SITE_URL . "/");
            exit;
        }
    } else {
        $error_message = "E-mail ou senha inválidos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 1.5rem;
        }
        .card-header h3 {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo SITE_TITLE; ?></h3>
                </div>
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-4">Acesso ao Sistema</h5>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form action="<?php echo SITE_URL; ?>/login" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Entrar</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <a href="#">Esqueceu a senha?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
