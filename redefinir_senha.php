<?php
/**
 * Página: Redefinição de senha (via token do link do e-mail).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/PasswordReset.php';
require_once __DIR__ . '/classes/AuditLog.php';

$token       = isset($_GET['token']) ? trim($_GET['token']) : '';
$erro        = '';
$sucesso     = '';
$tokenValido = false;

if (!empty($token)) {
    $reset = new PasswordReset();
    $registro = $reset->getValidToken($token);
    if ($registro) {
        $tokenValido = true;

        // Processa a nova senha
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $senha    = $_POST['senha']     ?? '';
            $confirma = $_POST['confirma']  ?? '';

            if (strlen($senha) < 6) {
                $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
            } elseif ($senha !== $confirma) {
                $erro = 'As senhas não coincidem.';
            } else {
                $user = $reset->findUserByEmail($registro['email']);
                if (!$user) {
                    $erro = 'Conta não encontrada ou inativa. Solicite uma nova recuperação.';
                } else {
                    if ($reset->updatePassword($user['tabela'], $user['id'], $senha)) {
                        $reset->markUsed((int)$registro['id']);
                        AuditLog::registrar('SENHA_RESET', $user['tabela'], (int)$user['id'], 'Senha redefinida via link de recuperação');
                        $sucesso = 'Senha redefinida com sucesso! Você já pode entrar com a nova senha.';
                        $tokenValido = false;
                    } else {
                        $erro = 'Erro ao salvar a nova senha. Tente novamente.';
                    }
                }
            }
        }
    } else {
        $erro = 'Link inválido, expirado ou já utilizado. Solicite uma nova recuperação de senha.';
    }
} else {
    $erro = 'Link de recuperação inválido (sem token).';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $sucesso ? 'Senha redefinida' : 'Redefinir senha' ?> &mdash; <?php echo SITE_TITLE; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { margin:0; min-height:100vh; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; background-color:#0f2417; background-image:url('<?php echo SITE_URL; ?>/assets/img/login-bg.jpg'); background-size:cover; background-position:center 30%; background-repeat:no-repeat; background-attachment:fixed; }
        body::before { content:''; position:fixed; inset:0; background:linear-gradient(120deg, rgba(5,30,15,0.55), rgba(10,40,20,0.60)); z-index:0; }
        .wrap { position:relative; z-index:1; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .card-reset { width:100%; max-width:430px; background:rgba(255,255,255,0.97); border-radius:20px; box-shadow:0 28px 64px rgba(0,0,0,0.45); overflow:hidden; }
        .card-head { background:linear-gradient(135deg,#0f5132 0%,#198754 60%,#20c997 100%); padding:1.6rem 1.6rem 1.3rem; text-align:center; color:#fff; }
        .card-head h1 { font-size:1.15rem; font-weight:700; margin:0; }
        .card-head p { font-size:0.8rem; margin:0.3rem 0 0; color:rgba(255,255,255,0.85); }
        .card-body { padding:1.75rem; }
        .form-label { font-weight:600; font-size:0.85rem; color:#374151; margin-bottom:0.4rem; }
        .form-control { min-height:50px; border-radius:10px; border:1.5px solid #e5e7eb; padding:0.625rem 1rem; }
        .form-control:focus { border-color:#198754; box-shadow:0 0 0 3px rgba(25,135,84,0.15); outline:none; }
        .btn-primary-full { width:100%; min-height:52px; background:linear-gradient(135deg,#198754 0%,#20c997 100%); border:none; border-radius:12px; color:#fff; font-weight:700; }
        .btn-primary-full:hover { filter:brightness(1.05); }
        .icon-passo { font-size:2.4rem; color:#198754; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card-reset">
            <div class="card-head">
                <h1><?= $sucesso ? 'Senha redefinida' : ($tokenValido ? 'Defina uma nova senha' : 'Redefinir senha'); ?></h1>
                <p><?php echo SITE_TITLE; ?> &mdash; Recuperação de acesso</p>
            </div>
            <div class="card-body">
                <?php if ($sucesso): ?>
                    <div class="text-center mb-3">
                        <i class="bi bi-check-circle-fill text-success icon-passo"></i>
                    </div>
                    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($sucesso); ?></div>
                    <a href="<?php echo SITE_URL; ?>/login" class="btn btn-primary-full">Ir para o login</a>

                <?php elseif ($tokenValido): ?>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($erro); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo SITE_URL . '/redefinir-senha/' . htmlspecialchars($token); ?>" id="form-reset">
                        <div class="mb-3">
                            <label for="senha" class="form-label">Nova senha <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="senha" name="senha"
                                   placeholder="Mínimo 6 caracteres" minlength="6"
                                   autocomplete="new-password" required>
                        </div>
                        <div class="mb-4">
                            <label for="confirma" class="form-label">Confirmar nova senha <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirma" name="confirma"
                                   placeholder="Repita a nova senha" minlength="6"
                                   autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary-full">
                            <i class="bi bi-shield-lock me-1"></i> Redefinir senha
                        </button>
                    </form>
                    <p class="text-center small text-muted mt-3 mb-0">
                        <a href="<?php echo SITE_URL; ?>/login">Voltar para o login</a>
                    </p>

                <?php else: ?>
                    <div class="text-center mb-3">
                        <i class="bi bi-hourglass-split text-danger icon-passo"></i>
                    </div>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($erro); ?></div>
                    <a href="<?php echo SITE_URL; ?>/recuperar-senha" class="btn btn-primary-full">
                        <i class="bi bi-envelope me-1"></i> Solicitar novo link
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>