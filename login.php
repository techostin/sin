<?php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/Database.php';

$user = new User();
$error_message = '';

// Se o usuário já estiver logado, redireciona para o dashboard apropriado
if ($user->isLoggedIn()) {
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
        header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
    } else {
        header("Location: " . SITE_URL . "/");
    }
    exit;
}

// Etapa 2: Gestor informando órgão e especialidade
if (isset($_POST['etapa']) && $_POST['etapa'] === '2' && isset($_SESSION['login_temp'])) {
    $temp = $_SESSION['login_temp'];
    $orgao        = trim($_POST['orgao']        ?? '');
    $especialidade = trim($_POST['especialidade'] ?? '');
    $setor        = trim($_POST['setor']        ?? '');

    if (empty($orgao) || empty($especialidade)) {
        $error_message = "Preencha o órgão e a especialidade/cargo.";
    } else {
        try {
            $db    = Database::getInstance()->getConnection();
            $table = $temp['user_table'];
            $id    = $temp['user_id'];

            $stmt = $db->prepare("UPDATE {$table} SET orgao = :orgao, setor = :setor, especialidade = :especialidade WHERE id = :id");
            $stmt->execute([
                ':orgao'         => $orgao,
                ':setor'         => $setor,
                ':especialidade' => $especialidade,
                ':id'            => $id,
            ]);

            $_SESSION['user_id']           = $temp['user_id'];
            $_SESSION['user_nivel']        = $temp['user_nivel'];
            $_SESSION['user_nome']         = $temp['user_nome'];
            $_SESSION['user_tipo']         = $temp['user_tipo'];
            $_SESSION['user_table']        = $temp['user_table'];
            $_SESSION['user_orgao']        = $orgao;
            $_SESSION['user_setor']        = $setor;
            $_SESSION['user_especialidade'] = $especialidade;
            $_SESSION['mostrar_modal_eventos'] = true;

            // Primeiro acesso do gestor: redirecionar para completar perfil
            $isPrimeiroAcesso = !empty($temp['primeiro_acesso']);
            if ($isPrimeiroAcesso) {
                $_SESSION['primeiro_acesso'] = true;
            }

            unset($_SESSION['login_temp']);

            // Anti fixação de sessão ao concluir a 2ª etapa do login
            session_regenerate_id(true);

            // Redirecionar: primeiro acesso vai para /meu-perfil, senão para o dashboard
            if ($isPrimeiroAcesso) {
                header("Location: " . SITE_URL . "/meu-perfil");
            } else {
                header("Location: " . SITE_URL . "/");
            }
            exit;
        } catch (Exception $e) {
            $error_message = "Erro ao salvar dados. Tente novamente.";
            unset($_SESSION['login_temp']);
        }
    }
}

// Etapa 1: Credenciais (email + senha)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['etapa']) || $_POST['etapa'] !== '2')) {
    // Apenas strings: um array (ex.: senha[]=x) faria password_verify()
    // lançar TypeError e derrubar a requisição com HTTP 500.
    $email = (isset($_POST['email']) && is_string($_POST['email'])) ? trim($_POST['email']) : '';
    $senha = (isset($_POST['senha']) && is_string($_POST['senha'])) ? $_POST['senha'] : '';

    if (empty($email) || empty($senha)) {
        $error_message = "Informe o e-mail e a senha.";
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // O mesmo e-mail pode existir em "associados" E em "usuarios"
            // (equipe que também é associada). Antes, a linha de "associados"
            // escondia a conta de sistema (inclusive Admin) quando as senhas
            // divergiam. Agora as duas são validadas pelo hash, com preferência
            // para a conta de sistema (usuarios).
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'");
            $stmt->execute([':email' => $email]);
            $usuarioSistema = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM associados WHERE email = :email AND status = 'ativo'");
            $stmt->execute([':email' => $email]);
            $usuarioAssociado = $stmt->fetch(PDO::FETCH_ASSOC);

            $userData = null;
            $table = '';
            if ($usuarioSistema && !empty($usuarioSistema['senha']) && password_verify($senha, $usuarioSistema['senha'])) {
                $userData = $usuarioSistema;
                $table = 'usuarios';
            } elseif ($usuarioAssociado && !empty($usuarioAssociado['senha']) && password_verify($senha, $usuarioAssociado['senha'])) {
                $userData = $usuarioAssociado;
                $table = 'associados';
            }

            if ($userData) {
                // Anti fixação de sessão: novo ID de sessão ao autenticar.
                session_regenerate_id(true);

                // Gestor: solicitar órgão/especialidade apenas no primeiro acesso
                if ((int)$userData['nivel'] === NIVEL_GESTOR) {
                    // Campos de vínculo profissional já preenchidos? => login direto.
                    $gestorJaPreenchido = !empty($userData['orgao'])
                        && !empty($userData['especialidade'])
                        && !empty($userData['setor']);

                    if (!$gestorJaPreenchido) {
                        // Primeiro acesso: pedir órgão/cargo/setor antes de completar login
                        $_SESSION['login_temp'] = [
                            'user_id'    => $userData['id'],
                            'user_nivel' => $userData['nivel'],
                            'user_nome'  => trim($userData['nome'] . ' ' . ($userData['sobrenome'] ?? '')),
                            'user_tipo'  => 'gestor',
                            'user_table' => $table,
                            'email'      => $email,
                        ];
                        $_SESSION['login_temp']['primeiro_acesso'] = true;
                        $showStep2 = true;
                    } else {
                        // Login direto do gestor (Órgão/cargo/setor já armazenados)
                        $_SESSION['user_id']           = $userData['id'];
                        $_SESSION['user_nivel']        = $userData['nivel'];
                        $_SESSION['user_nome']         = trim($userData['nome'] . ' ' . ($userData['sobrenome'] ?? ''));
                        $_SESSION['user_tipo']         = 'gestor';
                        $_SESSION['user_table']        = $table;
                        $_SESSION['user_orgao']        = $userData['orgao'];
                        $_SESSION['user_setor']        = $userData['setor'];
                        $_SESSION['user_especialidade'] = $userData['especialidade'];
                        $_SESSION['mostrar_modal_eventos'] = true;

                        $userCheck = new User();
                        if (!$userCheck->hasPerfilCompleto()) {
                            header("Location: " . SITE_URL . "/meu-perfil");
                            exit;
                        }
                        header("Location: " . SITE_URL . "/");
                        exit;
                    }
                } else {
                    // Admin ou usuário comum: login direto
                    $_SESSION['user_id']    = $userData['id'];
                    $_SESSION['user_nivel'] = $userData['nivel'];
                    $_SESSION['user_nome']  = trim($userData['nome'] . ' ' . ($userData['sobrenome'] ?? ''));
                    $_SESSION['user_tipo']  = $userData['tipo'] ?? 'usuario';
                    $_SESSION['user_table'] = $table;
                    $_SESSION['mostrar_modal_eventos'] = true;

                    // -----------------------------------------------------------------
                    // PEDIDO DE ACESSO recém-autorizado (conta em "usuarios" sem
                    // cadastro de associado): o primeiro acesso deve exibir a FICHA
                    // DE ADESÃO. Ela é oferecida pelo modal global
                    // (includes/ficha_adesao_modal.php, incluído pelo header), por
                    // isso levamos a pessoa a uma página útil do produtor em vez de
                    // /meu-perfil (que é centrada no cadastro do associado, que
                    // ainda não existe). Ao aprovar a ficha, o cadastro nasce e é
                    // vinculado a esta mesma conta.
                    // -----------------------------------------------------------------
                    if ($table === 'usuarios' && (int) $userData['nivel'] === NIVEL_USUARIO) {
                        $vinculoExistente = (int) ($userData['associado_id'] ?? 0);
                        if ($vinculoExistente <= 0) {
                            require_once __DIR__ . '/classes/FichaAdesao.php';
                            $temCadastro = (new FichaAdesao())->associadoIdDoUsuario((int) $userData['id']) > 0;
                            if (!$temCadastro) {
                                header('Location: ' . SITE_URL . '/caderno-campo/dashboard-agricultor');
                                exit;
                            }
                        }
                    }

                    // Detectar primeiro acesso (campos essenciais do perfil vazios)
                    $isPrimeiroAcesso = empty($userData['telefone']) && empty($userData['cpf']);
                    if ($isPrimeiroAcesso) {
                        $_SESSION['primeiro_acesso'] = true;
                    }

                    // Verificar se o perfil está completo
                    $userCheck = new User();
                    if (!$userCheck->hasPerfilCompleto()) {
                        header("Location: " . SITE_URL . "/meu-perfil");
                        exit;
                    }

                    // Primeiro acesso: sempre redirecionar para completar o perfil
                    if ($isPrimeiroAcesso && (int)$userData['nivel'] !== NIVEL_ADMIN) {
                        header("Location: " . SITE_URL . "/meu-perfil");
                        exit;
                    }

                    if ((int)$userData['nivel'] === NIVEL_USUARIO) {
                        header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
                    } else {
                        header("Location: " . SITE_URL . "/");
                    }
                    exit;
                }
            } else {
                $error_message = "E-mail ou senha inválidos!";
            }
        } catch (Exception $e) {
            error_log("Erro login: " . $e->getMessage());
            $error_message = "Erro ao processar login. Tente novamente.";
        }
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
    <meta name="apple-mobile-web-app-title" content="SINPORG">
    <meta name="description" content="SINPORG - Sistema de Gestão de Agricultura Orgânica">
    <title>Login - <?php echo SITE_TITLE; ?></title>
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/img/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* Fundo: foto do agricultor cobrindo toda a tela */
        body {
            min-height: 100vh;
            min-height: 100dvh;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background-color: #0f2417;
            background-image: url('<?php echo SITE_URL; ?>/assets/img/login-bg.jpg');
            background-size: cover;
            background-position: center 30%;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        /* Overlay escuro sobre a foto */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(
                120deg,
                rgba(5, 30, 15, 0.18) 0%,
                rgba(10, 40, 20, 0.10) 45%,
                rgba(5, 20, 10, 0.05) 100%
            );
            z-index: 0;
        }

        /* Wrapper: card no canto superior direito */
        .login-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            padding: calc(2.5rem + env(safe-area-inset-top, 0px)) max(3rem, env(safe-area-inset-right, 3rem)) calc(2rem + env(safe-area-inset-bottom, 0px)) 1rem;
        }

        /* Card */
        .login-card {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 28px 64px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,255,255,0.15);
            overflow: hidden;
            animation: slideDown 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Header do card */
        .login-header {
            background: linear-gradient(135deg, #0f5132 0%, #198754 60%, #20c997 100%);
            padding: 1.5rem 1.5rem 1.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -20%;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            pointer-events: none;
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            width: 120px;
            background: #ffffff;
            border-radius: 10px;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,0.22);
            position: relative;
            z-index: 1;
        }

        .login-logo img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 10px;
        }

        .login-header h1 {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 0.15rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 1px 4px rgba(0,0,0,0.25);
        }

        .login-header p {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.82);
            margin: 0;
            position: relative;
            z-index: 1;
        }

        /* Body do card */
        .login-body {
            padding: 1.5rem 1.5rem 1.75rem;
        }

        /* Responsivo: mobile centra o card */
        @media (max-width: 600px) {
            .login-wrapper {
                align-items: center;
                justify-content: center;
                padding: 1.5rem 1rem;
                padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
            }
            .login-card { max-width: 100%; }
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

        .step-dot.done { background: #20c997; }

        /* Labels */
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        /* Inputs */
        .form-control {
            min-height: 50px;
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: 1rem;
            padding: 0.625rem 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 3px rgba(25,135,84,0.15);
            outline: none;
        }

        /* Input de senha */
        .input-password-wrapper { position: relative; }
        .input-password-wrapper .form-control { padding-right: 3rem; }

        .toggle-password {
            position: absolute;
            right: 0; top: 0;
            height: 100%; width: 48px;
            background: none; border: none;
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af; cursor: pointer;
            transition: color 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        .toggle-password:hover { color: #198754; }

        /* Botão principal */
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

        /* Info box gestor */
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
        .login-link:hover { color: #0f5132; text-decoration: underline; }

        /* Erro */
        .alert-danger {
            border-radius: 10px;
            border: none;
            background: #fef2f2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 0.75rem 1.5rem;
            border-top: 1px solid #f3f4f6;
        }

        /* Loader no botão */
        .btn-loader { display: none; }
        .btn-login.loading .btn-text { display: none; }
        .btn-login.loading .btn-loader { display: inline-block; }

        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .btn-loader i { animation: spin 1s linear infinite; }

        /* ── Abas Login / Primeiro Acesso ── */
        .login-tabs {
            display: flex;
            border-bottom: 2px solid #f3f4f6;
            margin: -0.25rem -0rem 1.25rem;
        }
        .login-tab {
            flex: 1;
            padding: 0.65rem 0.5rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: #6b7280;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            cursor: pointer;
            transition: color 0.2s, border-color 0.2s;
            letter-spacing: 0.02em;
        }
        .login-tab.active {
            color: #198754;
            border-bottom-color: #198754;
        }
        .login-tab:hover:not(.active) { color: #374151; }
        .login-panel { display: none; }
        .login-panel.active { display: block; }

        /* Sucesso do cadastro */
        .cadastro-success {
            text-align: center;
            padding: 1rem 0;
        }
        .cadastro-success .success-icon {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #198754, #20c997);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.75rem; color: #fff;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Header com logo centralizada -->
            <div class="login-header">
                <div class="login-logo">
                    <img src="<?php echo SITE_URL; ?>/img/logo_Sinporg-2.png"
                         alt="SINPORG - Sistema Integrado da Produção Orgânica">
                </div>
                <p>Sistema Integrado da Produção Orgânica</p>
            </div>

            <!-- Body -->
            <div class="login-body">

                <?php if (isset($showStep2) && $showStep2): ?>
                    <!-- Etapa 2: Gestor - Órgão e Especialidade -->
                    <div class="step-track">
                        <span class="step-dot done"></span>
                        <span class="step-dot active"></span>
                    </div>

                    <h5 class="fw-bold mb-1" style="font-size:1.05rem;">Confirmar Vínculo</h5>
                    <p class="text-muted small mb-3">Informe seus dados profissionais para acessar.</p>

                    <div class="info-box">
                        <i class="bi bi-shield-lock-fill me-1"></i>
                        <strong><?php echo htmlspecialchars($_SESSION['login_temp']['user_nome']); ?></strong>,
                        confirme seu vínculo institucional.
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo SITE_URL; ?>/login" method="POST" id="form-step2">
                        <input type="hidden" name="etapa" value="2">

                        <div class="mb-3">
                            <label for="orgao" class="form-label">Órgão <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="orgao" name="orgao"
                                   placeholder="Ex: MAPA, EMATER, Prefeitura..."
                                   autocomplete="organization" inputmode="text" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="especialidade" class="form-label">Cargo / Especialidade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="especialidade" name="especialidade"
                                   placeholder="Ex: Fiscal, Téc. Agrícola, Eng. Agrônomo..."
                                   autocomplete="organization-title" inputmode="text" required>
                        </div>

                        <div class="mb-4">
                            <label for="setor" class="form-label">Setor <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" class="form-control" id="setor" name="setor"
                                   placeholder="Ex: Defesa Sanitária, Certificação..."
                                   autocomplete="off" inputmode="text">
                        </div>

                        <button type="submit" class="btn-login" id="btn-step2">
                            <span class="btn-text"><i class="bi bi-shield-check me-1"></i> Acessar Sistema</span>
                            <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Aguarde...</span>
                        </button>
                    </form>

                <?php else: ?>

                    <!-- ── Abas: Entrar | Primeiro Acesso ── -->
                    <div class="login-tabs" role="tablist">
                        <button class="login-tab active" id="tab-login" role="tab"
                                aria-selected="true" aria-controls="panel-login"
                                onclick="switchTab('login')">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                        </button>
                        <button class="login-tab" id="tab-cadastro" role="tab"
                                aria-selected="false" aria-controls="panel-cadastro"
                                onclick="switchTab('cadastro')">
                            <i class="bi bi-person-plus me-1"></i> Primeiro Acesso
                        </button>
                        <button class="login-tab" id="tab-perfil" role="tab"
                                aria-selected="false" aria-controls="panel-perfil"
                                onclick="switchTab('perfil')">
                            <i class="bi bi-flower1 me-1"></i> Perfil de Acesso
                        </button>
                    </div>

                    <!-- ── Painel: Login ── -->
                    <div class="login-panel active" id="panel-login" role="tabpanel">

                        <h5 class="fw-bold mb-1" style="font-size:1.05rem;">Bem-vindo de volta</h5>
                        <p class="text-muted small mb-3">Entre com suas credenciais para continuar.</p>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger mb-3">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form action="<?php echo SITE_URL; ?>/login" method="POST" id="form-login">

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="seu@email.com"
                                       autocomplete="email" inputmode="email"
                                       autocapitalize="none" autocorrect="off" spellcheck="false"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       required autofocus>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="senha" class="form-label mb-0">Senha</label>
                                    <a href="<?php echo SITE_URL; ?>/recuperar-senha" class="login-link">Esqueceu?</a>
                                </div>
                                <div class="input-password-wrapper">
                                    <input type="password" class="form-control" id="senha" name="senha"
                                           placeholder="Sua senha"
                                           autocomplete="current-password" required>
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
                    </div><!-- /panel-login -->

                    <!-- ── Painel: Primeiro Acesso + Perfil (form único) ── -->
                    <form id="form-cadastro" novalidate>

                        <!-- ── Passo 1: Dados pessoais ── -->
                        <div class="login-panel" id="panel-cadastro" role="tabpanel">
                            <h5 class="fw-bold mb-1" style="font-size:1.05rem;">Criar minha conta</h5>
                            <p class="text-muted small mb-3">
                                Preencha seus dados abaixo e continue para escolher o <strong>Perfil de Acesso</strong>.
                            </p>

                            <div id="cadastro-error" class="alert alert-danger mb-3" style="display:none;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <span id="cadastro-error-text"></span>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="c-nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="c-nome" name="nome"
                                           placeholder="Seu nome"
                                           autocomplete="given-name" required>
                                </div>
                                <div class="col-6">
                                    <label for="c-sobrenome" class="form-label">Sobrenome <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="c-sobrenome" name="sobrenome"
                                           placeholder="Sobrenome"
                                           autocomplete="family-name" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="c-email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="c-email" name="email"
                                       placeholder="seu@email.com"
                                       autocomplete="email" inputmode="email"
                                       autocapitalize="none" autocorrect="off" required>
                            </div>

                            <div class="mb-3">
                                <label for="c-senha" class="form-label">Senha <span class="text-danger">*</span></label>
                                <div class="input-password-wrapper">
                                    <input type="password" class="form-control" id="c-senha" name="senha"
                                           placeholder="Mínimo 6 caracteres"
                                           autocomplete="new-password" required>
                                    <button type="button" class="toggle-password" id="toggle-c-senha" aria-label="Mostrar senha">
                                        <i class="bi bi-eye" id="toggle-c-senha-icon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="c-confirma" class="form-label">Confirmar senha <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="c-confirma" name="confirma_senha"
                                       placeholder="Repita a senha"
                                       autocomplete="new-password" required>
                            </div>

                            <button type="button" class="btn-login" id="btn-cadastrar-proximo">
                                <span class="btn-text"><i class="bi bi-arrow-right-circle me-1"></i> Continuar</span>
                                <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Enviando...</span>
                            </button>
                        </div><!-- /panel-cadastro -->

                        <!-- ── Passo 2: Perfil de Acesso ── -->
                        <div class="login-panel" id="panel-perfil" role="tabpanel">
                            <h5 class="fw-bold mb-1" style="font-size:1.05rem;">Perfil de Acesso</h5>
                            <p class="text-muted small mb-3">
                                Conforme o perfil escolhido, os módulos do menu lateral serão disponibilizados para você.
                            </p>

                            <div id="perfil-error" class="alert alert-danger mb-3" style="display:none;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <span>Selecione um perfil de acesso.</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Escolha seu perfil <span class="text-danger">*</span></label>

                                <div class="form-check form-check-inline w-100 p-2 border rounded mb-2" style="border-color:#e5e7eb!important;">
                                    <input class="form-check-input" type="radio" name="perfil" value="vegetal" id="p-vegetal">
                                    <label class="form-check-label w-100" for="p-vegetal">
                                        <i class="bi bi-tree text-success me-1"></i> <strong>Vegetal</strong>
                                        <span class="text-muted small d-block">Lavouras, produtos, atividades, insumos e rastreabilidade.</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline w-100 p-2 border rounded mb-2" style="border-color:#e5e7eb!important;">
                                    <input class="form-check-input" type="radio" name="perfil" value="animal" id="p-animal">
                                    <label class="form-check-label w-100" for="p-animal">
                                        <i class="bi bi-egg-fill text-secondary me-1"></i> <strong>Animal</strong>
                                        <span class="text-muted small d-block">Rebanho, lotes, alimentação, sanidade, reprodução e ambientes.</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline w-100 p-2 border rounded mb-2" style="border-color:#e5e7eb!important;">
                                    <input class="form-check-input" type="radio" name="perfil" value="agroindustria" id="p-agro">
                                    <label class="form-check-label w-100" for="p-agro">
                                        <i class="bi bi-buildings text-primary me-1"></i> <strong>Agroindústria</strong>
                                        <span class="text-muted small d-block">Beneficiamento, receitas, ordens de produção e rastreabilidade.</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline w-100 p-2 border rounded mb-2" style="border-color:#e5e7eb!important;">
                                    <input class="form-check-input" type="radio" name="perfil" value="mista" id="p-mista">
                                    <label class="form-check-label w-100" for="p-mista">
                                        <i class="bi bi-collection text-warning me-1"></i> <strong>Mista</strong>
                                        <span class="text-muted small d-block">Produção diversificada: animal, vegetal e/ou agroindústria.</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline w-100 p-2 border rounded mb-2" style="border-color:#e5e7eb!important;">
                                    <input class="form-check-input" type="radio" name="perfil" value="gestor" id="p-gestor">
                                    <label class="form-check-label w-100" for="p-gestor">
                                        <i class="bi bi-shield-lock text-danger me-1"></i> <strong>Gestor / Fiscal</strong>
                                        <span class="text-muted small d-block">Órgãos e fiscalização. Acesso aprovado pelo administrador.</span>
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i>
                                    Gestores e fiscais são <strong>aprovados pelo administrador</strong> antes de acessarem o sistema.
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn-login" id="btn-cadastrar-voltar" style="flex:0 0 auto; background:linear-gradient(135deg,#6c757d 0%,#adb5bd 100%); box-shadow:none; max-width:120px;">
                                    <i class="bi bi-arrow-left me-1"></i> Voltar
                                </button>
                                <button type="submit" class="btn-login" id="btn-cadastrar">
                                    <span class="btn-text"><i class="bi bi-person-plus me-1"></i> Solicitar Acesso</span>
                                    <span class="btn-loader"><i class="bi bi-arrow-repeat"></i> Enviando...</span>
                                </button>
                            </div>
                        </div><!-- /panel-perfil -->

                    </form>

                    <!-- Estado: sucesso -->
                    <div id="cadastro-success-area" style="display:none;" class="cadastro-success">
                        <div class="success-icon">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Cadastro enviado!</h5>
                        <p class="text-muted small mb-3">
                            Seu cadastro foi recebido com sucesso.<br>
                            <strong>Aguarde a ativação por um administrador</strong> para poder fazer login.
                        </p>
                        <p class="text-muted small mb-4">
                            Você receberá um aviso assim que sua conta for ativada.
                        </p>
                        <button class="btn-login" onclick="switchTab('login')" style="max-width:200px;margin:0 auto;">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Ir para Login
                        </button>
                    </div><!-- /cadastro-success-area -->

                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <small class="text-muted"><?php echo SITE_TITLE; ?> &mdash; Agricultura Orgânica<br>&copy; <?php echo date('Y'); ?> Edmildon L. Santos. Todos os direitos reservados.</small>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var SITE_URL = '<?php echo SITE_URL; ?>';

        // ── Toggle visibilidade senha (login) ──
        var toggleBtn  = document.getElementById('toggle-senha');
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

        // ── Toggle visibilidade senha (cadastro) ──
        var toggleCBtn  = document.getElementById('toggle-c-senha');
        var cSenhaInput = document.getElementById('c-senha');
        var toggleCIcon = document.getElementById('toggle-c-senha-icon');
        if (toggleCBtn && cSenhaInput) {
            toggleCBtn.addEventListener('click', function() {
                var isPass = cSenhaInput.type === 'password';
                cSenhaInput.type = isPass ? 'text' : 'password';
                toggleCIcon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
                toggleCBtn.setAttribute('aria-label', isPass ? 'Ocultar senha' : 'Mostrar senha');
            });
        }

        // ── Loading ao submeter login/step2 ──
        ['form-login', 'form-step2'].forEach(function(id) {
            var form = document.getElementById(id);
            if (!form) return;
            form.addEventListener('submit', function() {
                var btn = form.querySelector('.btn-login');
                if (btn) btn.classList.add('loading');
            });
        });

        // ── Troca de abas ──
        function switchTab(tab) {
            document.querySelectorAll('.login-tab').forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.login-panel').forEach(function(p) {
                p.classList.remove('active');
            });
            var activeTab   = document.getElementById('tab-' + tab);
            var activePanel = document.getElementById('panel-' + tab);
            if (activeTab)   { activeTab.classList.add('active'); activeTab.setAttribute('aria-selected', 'true'); }
            if (activePanel) activePanel.classList.add('active');
        }

        // ── Submit do formulário de pré-cadastro (2 passos: dados → perfil) ──
        var formCadastro  = document.getElementById('form-cadastro');
        var btnProximo    = document.getElementById('btn-cadastrar-proximo');
        var btnVoltar     = document.getElementById('btn-cadastrar-voltar');

        function validarDadosCadastro() {
            var nome     = document.getElementById('c-nome').value.trim();
            var sobrenom = document.getElementById('c-sobrenome').value.trim();
            var email    = document.getElementById('c-email').value.trim();
            var senha    = document.getElementById('c-senha').value;
            var confirma = document.getElementById('c-confirma').value;

            if (!nome || !sobrenom || !email || !senha || !confirma) {
                mostrarErroCadastro('Preencha todos os campos obrigatórios.');
                return false;
            }
            if (senha.length < 6) {
                mostrarErroCadastro('A senha deve ter pelo menos 6 caracteres.');
                return false;
            }
            if (senha !== confirma) {
                mostrarErroCadastro('As senhas não coincidem.');
                return false;
            }
            return true;
        }

        if (btnProximo) {
            btnProximo.addEventListener('click', function() {
                document.getElementById('cadastro-error').style.display = 'none';
                if (validarDadosCadastro()) {
                    switchTab('perfil');
                }
            });
        }

        if (btnVoltar) {
            btnVoltar.addEventListener('click', function() {
                switchTab('cadastro');
            });
        }

        if (formCadastro) {
            formCadastro.addEventListener('submit', function(e) {
                e.preventDefault();

                document.getElementById('cadastro-error').style.display = 'none';
                document.getElementById('perfil-error').style.display   = 'none';

                if (!validarDadosCadastro()) return;

                var perfil = formCadastro.querySelector('input[name="perfil"]:checked');
                if (!perfil) {
                    document.getElementById('perfil-error').style.display = 'block';
                    formCadastro.querySelector('#btn-cadastrar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }

                var btn = document.getElementById('btn-cadastrar');
                btn.classList.add('loading');

                var data = new FormData(formCadastro);
                fetch(SITE_URL + '/api/pre_cadastro.php', {
                    method: 'POST',
                    body: data
                })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    btn.classList.remove('loading');
                    if (resp.success) {
                        formCadastro.style.display = 'none';
                        document.getElementById('cadastro-success-area').style.display = 'block';
                    } else {
                        mostrarErroCadastro(resp.error || 'Erro desconhecido. Tente novamente.');
                        switchTab('cadastro');
                    }
                })
                .catch(function() {
                    btn.classList.remove('loading');
                    mostrarErroCadastro('Erro de comunicação. Verifique sua conexão e tente novamente.');
                    switchTab('cadastro');
                });
            });
        }

        function mostrarErroCadastro(msg) {
            var el = document.getElementById('cadastro-error');
            document.getElementById('cadastro-error-text').textContent = msg;
            el.style.display = 'block';
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>
</body>
</html>
