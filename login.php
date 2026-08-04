<?php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Database.php';

$user = new User();
$error_message = '';

// Se o usuÃ¡rio jÃ¡ estiver logado, redireciona para o dashboard apropriado
if ($user->isLoggedIn()) {
    // UsuÃ¡rios comuns vÃ£o para o dashboard simplificado do Agricultor
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
        header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
    } else {
        header("Location: " . SITE_URL . "/");
    }
    exit;
}

// Etapa 2: Gestor informando Ã³rgÃ£o e especialidade
if (isset($_POST['etapa']) && $_POST['etapa'] === '2' && isset($_SESSION['login_temp'])) {
    $temp = $_SESSION['login_temp'];
    $orgao = trim($_POST['orgao'] ?? '');
    $especialidade = trim($_POST['especialidade'] ?? '');
    $setor = trim($_POST['setor'] ?? '');

    if (empty($orgao) || empty($especialidade)) {
        $error_message = "Preencha o Ã³rgÃ£o e a especialidade/cargo.";
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $table = $temp['user_table'];
            $id = $temp['user_id'];
            
            $stmt = $db->prepare("UPDATE {$table} SET orgao = :orgao, setor = :setor, especialidade = :especialidade WHERE id = :id");
            $stmt->execute([
                ':orgao' => $orgao,
                ':setor' => $setor,
                ':especialidade' => $especialidade,
                ':id' => $id
            ]);

            // Agora sim, efetuar login completo
            $_SESSION['user_id'] = $temp['user_id'];
            $_SESSION['user_nivel'] = $temp['user_nivel'];
            $_SESSION['user_nome'] = $temp['user_nome'];
            $_SESSION['user_tipo'] = $temp['user_tipo'];
            $_SESSION['user_table'] = $temp['user_table'];
            $_SESSION['user_orgao'] = $orgao;
            $_SESSION['user_setor'] = $setor;
            $_SESSION['user_especialidade'] = $especialidade;
            $_SESSION['mostrar_modal_eventos'] = true;

            unset($_SESSION['login_temp']);

            header("Location: " . SITE_URL . "/");
            exit;
        } catch (Exception $e) {
            $error_message = "Erro ao salvar dados. Tente novamente.";
            unset($_SESSION['login_temp']);
        }
    }
}

// Etapa 1: Credenciais (email + senha)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (!isset($_POST['etapa']) || $_POST['etapa'] !== '2')) {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Buscar dados do usuÃ¡rio sem finalizar login
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar na tabela associados
        $stmt = $db->prepare("SELECT * FROM associados WHERE email = :email AND status = 'ativo'");
        $stmt->execute([':email' => $email]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $table = 'associados';

        // Se nÃ£o encontrou, buscar na tabela usuarios
        if (!$userData) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'");
            $stmt->execute([':email' => $email]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            $table = 'usuarios';
        }

        if ($userData && password_verify($senha, $userData['senha'])) {
            // Se for gestor, solicitar Ã³rgÃ£o/especialidade antes de autenticar completamente
            if ((int)$userData['nivel'] === NIVEL_GESTOR) {
                $_SESSION['login_temp'] = [
                    'user_id' => $userData['id'],
                    'user_nivel' => $userData['nivel'],
                    'user_nome' => $userData['nome'],
                    'user_tipo' => $userData['tipo'],
                    'user_table' => $table,
                    'email' => $email
                ];
                // Mostrar etapa 2 no formulÃ¡rio
                $showStep2 = true;
            } else {
                // Admin ou usuÃ¡rio comum: login direto
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['user_nivel'] = $userData['nivel'];
                $_SESSION['user_nome'] = $userData['nome'];
                $_SESSION['user_tipo'] = $userData['tipo'];
                $_SESSION['user_table'] = $table;
                $_SESSION['mostrar_modal_eventos'] = true;

                // Verificar se o perfil estÃ¡ completo
                $user = new User();
                if (!$user->hasPerfilCompleto()) {
                    header("Location: " . SITE_URL . "/completar-perfil");
                    exit;
                }

                if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
                    header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
                } else {
                    header("Location: " . SITE_URL . "/");
                }
                exit;
            }
        } else {
            $error_message = "E-mail ou senha invÃ¡lidos!";
        }
    } catch (Exception $e) {
        $error_message = "Erro ao processar login. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#198754">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="SINPORG - Sistema de GestÃ£o de Agricultura OrgÃ¢nica">
    <title>Login - <?php echo SITE_TITLE; ?></title>
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #0f2417 0%, #1a4a2e 40%, #198754 100%);
            min-height: 100vh;
            min-height: 100dvh;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .login-wrapper {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.35);
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(32px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Header do card */
        .login-header {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .login-logo {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            backdrop-filter: blur(8px);
        }

        .login-logo i {
            font-size: 2rem;
            color: #fff;
        }

        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 0.25rem;
        }

        .login-header p {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
            margin: 0;
        }

        /* Body do card */
        .login-body {
            padding: 1.75rem 1.5rem 2rem;
        }

        /* Indicador de etapas */
        .step-track {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 1.5rem;
        }

        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dee2e6;
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: #198754;
            width: 24px;
            border-radius: 5px;
        }

        .step-dot.done {
            background: #20c997;
        }

        /* Labels */
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        /* Inputs mobile-friendly */
        .form-control {
            min-height: 50px;
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: 1rem; /* previne zoom iOS */
            padding: 0.625rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 3px rgba(25,135,84,0.15);
            outline: none;
        }

        /* Input group para senha */
        .input-password-wrapper {
            position: relative;
        }

        .input-password-wrapper .form-control {
            padding-right: 3rem;
        }

        .toggle-password {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            width: 48px;
            background: none;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.2s;
            -webkit-tap-highlight-color: transparent;
        }

        .toggle-password:hover { color: #198754; }

        /* BotÃ£o principal */
        .btn-login {
            width: 100%;
            min-height: 52px;
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 4px 16px rgba(25,135,84,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:active {
            transform: scale(0.97);
            box-shadow: 0 2px 8px rgba(25,135,84,0.25);
        }

        /* Info box de gestor */
        .info-box {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-left: 4px solid #198754;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            color: #065f46;
            margin-bottom: 1.25rem;
        }

        /* Links */
        .login-link {
            color: #198754;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .login-link:hover {
            color: #0f5132;
            text-decoration: underline;
        }

        /* Erro */
        .alert-danger {
            border-radius: 10px;
            border: none;
            background: #fef2f2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        /* Footer do login */
        .login-footer {
            text-align: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid #f3f4f6;
        }

        /* Loader no botÃ£o */
        .btn-loader { display: none; }
        .btn-login.loading .btn-text { display: none; }
        .btn-login.loading .btn-loader { display: inline-block; }

        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .btn-loader i { animation: spin 1s linear infinite; }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-leaf-fill"></i>
                </div>
                <h1><?php echo SITE_TITLE; ?></h1>
                <p>Sistema de GestÃ£o OrgÃ¢nica</p>
            </div>

            <!-- Body -->
            <div class="login-body">

                <?php if (isset($showStep2) && $showStep2): ?>
                    <!-- Etapa 2: Gestor - Ã“rgÃ£o e Especialidade -->
                    <div class="step-track">
                        <span class="step-dot done"></span>
                        <span class="step-dot active"></span>
                    </div>

                    <h5 class="fw-700 mb-1" style="font-size:1.1rem;">Confirmar VÃ­nculo</h5>
                    <p class="text-muted small mb-3">Informe seus dados profissionais para acessar.</p>

                    <div class="info-box">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['login_temp']['user_nome']); ?></strong>,
                        confirme seu vÃ­nculo institucional.
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo SITE_URL; ?>/login" method="POST" id="form-step2">
                        <input type="hidden" name="etapa" value="2">

                        <div class="mb-3">
                            <label for="orgao" class="form-label">Ã“rgÃ£o <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="orgao" name="orgao"
                                   placeholder="Ex: MAPA, EMATER, Prefeitura..."
                                   autocomplete="organization"
                                   inputmode="text"
                                   required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="especialidade" class="form-label">Cargo / Especialidade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="especialidade" name="especialidade"
                                   placeholder="Ex: Fiscal, TÃ©c. AgrÃ­cola, Eng. AgrÃ´nomo..."
                                   autocomplete="organization-title"
                                   inputmode="text"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="setor" class="form-label">Setor <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" class="form-control" id="setor" name="setor"
                                   placeholder="Ex: Defesa SanitÃ¡ria, CertificaÃ§Ã£o..."
                                   autocomplete="off"
                                   inputmode="text">
                        </div>

                        <button type="submit" class="btn-login" id="btn-step2">
                            <span class="btn-text"><i class="bi bi-shield-check me-1"></i> Acessar Sistema</span>
                            <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Aguarde...</span>
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Etapa 1: Credenciais -->
                    <div class="step-track">
                        <span class="step-dot active"></span>
                        <span class="step-dot"></span>
                    </div>

                    <h5 class="fw-bold mb-1" style="font-size:1.1rem;">Bem-vindo de volta</h5>
                    <p class="text-muted small mb-3">Entre com suas credenciais para continuar.</p>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form action="<?php echo SITE_URL; ?>/login" method="POST" id="form-login">

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="seu@email.com"
                                   autocomplete="email"
                                   inputmode="email"
                                   autocapitalize="none"
                                   autocorrect="off"
                                   spellcheck="false"
                                   required autofocus>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="senha" class="form-label mb-0">Senha</label>
                                <a href="#" class="login-link">Esqueceu?</a>
                            </div>
                            <div class="input-password-wrapper">
                                <input type="password" class="form-control" id="senha" name="senha"
                                       placeholder="Sua senha"
                                       autocomplete="current-password"
                                       required>
                                <button type="button" class="toggle-password" id="toggle-senha" aria-label="Mostrar senha">
                                    <i class="bi bi-eye" id="toggle-senha-icon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-login" id="btn-entrar">
                            <span class="btn-text"><i class="bi bi-box-arrow-in-right me-1"></i> Entrar</span>
                            <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Verificando...</span>
                        </button>

                    </form>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <small class="text-muted"><?php echo SITE_TITLE; ?> &mdash; Agricultura OrgÃ¢nica</small>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle de senha
        var toggleBtn = document.getElementById('toggle-senha');
        var senhaInput = document.getElementById('senha');
        var toggleIcon = document.getElementById('toggle-senha-icon');

        if (toggleBtn && senhaInput) {
            toggleBtn.addEventListener('click', function() {
                var isPass = senhaInput.type === 'password';
                senhaInput.type = isPass ? 'text' : 'password';
                toggleIcon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
                toggleBtn.setAttribute('aria-label', isPass ? 'Ocultar senha' : 'Mostrar senha');
            });
        }

        // Loading state ao submeter
        ['form-login', 'form-step2'].forEach(function(id) {
            var form = document.getElementById(id);
            if (!form) return;
            form.addEventListener('submit', function() {
                var btn = form.querySelector('.btn-login');
                if (btn) btn.classList.add('loading');
            });
        });
    </script>
</body>
</html>

