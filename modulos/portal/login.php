<?php
/**
 * Portal do Associado - Login
 * Suporte a: CPF+senha ou Link Mágico por e-mail
 * 
 * Fluxo de acesso (regra aplicada por classes/PortalAccess.php):
 * - Associado/usuário comum: acessa APENAS o próprio portal (CPF+senha ou
 *   Link Mágico). Qualquer CPF de terceiro é bloqueado em TODOS os fluxos,
 *   inclusive na confirmação do token, com a mensagem de segurança
 *   "Você só tem permissão para acessar os seus próprios dados exclusivos."
 * - Admin do sistema / Diretoria Executiva (Presidente, Vice-Presidente,
 *   Diretor, Secretário, Tesoureiro): podem consultar qualquer associado
 *   ativo por CPF e acessar o portal dele (ação registrada em auditoria).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/PasswordReset.php';
require_once __DIR__ . '/../../classes/PortalAccess.php';
require_once __DIR__ . '/../../classes/AuditLog.php';
require_once __DIR__ . '/../../classes/Mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentativa de autenticação no Portal: não herda a sessão de portal anterior.
// Antes, um POST de login com CPF de outra pessoa era redirecionado de volta
// para a área do associado antigo por causa do redirect abaixo.
$acaoPortalPost = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
$tentativaPortal = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && in_array($acaoPortalPost, ['login_cpf', 'solicitar_magic_link', 'verificar_token', 'consultar_cpf'], true);

$limparSessaoPortal = static function (): void {
    foreach ([
        'associado_id', 'associado_nome', 'associado_cpf', 'associado_email',
        'portal_acesso_via', 'portal_acesso_por',
    ] as $chavePortal) {
        unset($_SESSION[$chavePortal]);
    }
};

// Verificar se já tem sessão de portal ativa
if (isset($_SESSION['associado_id'])) {
    if (!$tentativaPortal) {
        header('Location: ' . SITE_URL . '/portal/area');
        exit;
    }
    // A tentativa de login sempre começa sem a identidade de portal anterior.
    $limparSessaoPortal();
}

// Verificar se usuário está logado no sistema principal (admin/gestor/diretoria)
$user = new User();
$isAdminOrDiretoriaExecutiva = false;
$usuarioSistema = null;

if ($user->isLoggedIn()) {
    // Verifica se é admin do sistema ou diretoria executiva
    // Regra única de privilégio (Admin ou Diretoria Executiva) — PortalAccess
    $isAdminOrDiretoriaExecutiva = PortalAccess::ehPrivilegiado($user);
    $usuarioSistema = $user->getCurrentUser();
}

$mensagem = '';
$mensagem_tipo = '';
$etapa = $_GET['etapa'] ?? 'login'; // login, magic_link, verificar_token, consulta_cpf

$associadoModel = new Associado();
$passwordReset = new PasswordReset();
$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        // Normaliza entradas de texto: valores não-string (ex.: cpf[]=...,
        // token[]=...) são descartados. Sem isso, password_verify()/hash()
        // lançam TypeError e a requisição termina em HTTP 500.
        $entrada = static function ($campo) {
            $valor = $_POST[$campo] ?? '';
            return is_string($valor) ? $valor : '';
        };

        $acao = $entrada('acao');
        $acaoSolicitada = $acao;

        // === BLOQUEIO DE ACESSO INDEVIDO (guarda central PortalAccess) ====
        // Usuário autenticado que NÃO seja Admin do sistema nem cargo da
        // Diretoria Executiva só pode operar sobre o PRÓPRIO CPF, em todos os
        // fluxos: CPF+Senha, Link Mágico e confirmação do token do link.
        if (PortalAccess::bloqueioAtivo($user)) {
            $cpfAlvo = '';
            $associadoAlvo = 0;

            if ($acao === 'consultar_cpf') {
                $cpfAlvo = $entrada('cpf_consulta');
            } elseif ($acao === 'login_cpf' || $acao === 'solicitar_magic_link') {
                $cpfAlvo = $entrada('cpf');
            } elseif ($acao === 'verificar_token') {
                // Quem é o titular do token apresentado?
                $associadoAlvo = PortalAccess::associadoIdDoToken($entrada('token'));
            }

            $permitido = true;
            if ($associadoAlvo > 0) {
                $permitido = PortalAccess::podeAcessarAssociadoId($associadoAlvo, $user);
            } elseif (trim((string) $cpfAlvo) !== '') {
                $permitido = PortalAccess::podeAcessarCpf($cpfAlvo, $user);
            }

            if (!$permitido) {
                $mensagem = PortalAccess::MSG_ACESSO_NEGADO;
                $mensagem_tipo = 'danger';
                $acao = 'bloqueado';
                PortalAccess::registrarAcessoNegado($acaoSolicitada, $cpfAlvo, $associadoAlvo);
            }
        }

        // Anti abuso (força bruta / spam de link mágico) por sessão
        $tentativasExcedidas = PortalAccess::tentativasRestantes() <= 0;

        // === AÇÃO: Consulta por CPF (Admin/Diretoria Executiva) ===
        if ($acao === 'consultar_cpf' && $isAdminOrDiretoriaExecutiva) {
            $cpf = $entrada('cpf_consulta');

            if (trim($cpf) === '') {
                $mensagem = 'Informe o CPF do associado.';
                $mensagem_tipo = 'danger';
            } else {
                // Busca tolerante a pontuação e a zeros à esquerda ausentes
                $associado = PortalAccess::buscarAssociadoAtivoPorCpf($cpf);

                if ($associado) {
                    // Cria a sessão de portal do associado consultado, mantendo
                    // a sessão principal do Admin/Diretoria (auditada).
                    session_regenerate_id(true);
                    $limparSessaoPortal();
                    $_SESSION['associado_id'] = $associado['id'];
                    $_SESSION['associado_nome'] = $associado['nome'] . ' ' . $associado['sobrenome'];
                    $_SESSION['associado_cpf'] = $associado['cpf'];
                    $_SESSION['associado_email'] = $associado['email'] ?? '';
                    $_SESSION['portal_acesso_via'] = 'consulta_admin'; // Auditoria
                    $_SESSION['portal_acesso_por'] = $_SESSION['user_id'] ?? 0; // Quem acessou

                    PortalAccess::registrarAcessoPrivilegiado((int) $associado['id'], 'via=consulta_cpf');

                    header('Location: ' . SITE_URL . '/portal/area');
                    exit;
                } else {
                    $mensagem = 'Associado não encontrado ou inativo para o CPF informado.';
                    $mensagem_tipo = 'danger';
                }
            }
        }

        // === AÇÃO: Login normal CPF + Senha (Associado comum) ===
        elseif ($acao === 'login_cpf') {
            $cpf = $entrada('cpf');
            $senha = $entrada('senha');

            if (trim($cpf) === '' || $senha === '') {
                $mensagem = 'Informe CPF e senha.';
                $mensagem_tipo = 'danger';
            } elseif ($tentativasExcedidas) {
                $mensagem = 'Muitas tentativas em sequência. Aguarde alguns minutos e tente novamente.';
                $mensagem_tipo = 'warning';
            } else {
                $associado = PortalAccess::buscarAssociadoAtivoPorCpf($cpf);

                if ($associado && !empty($associado['senha']) && password_verify($senha, $associado['senha'])) {
                    PortalAccess::limparTentativas();

                    // Evita fixação de sessão no momento da autenticação
                    session_regenerate_id(true);
                    $limparSessaoPortal();

                    // Login bem-sucedido
                    $_SESSION['associado_id'] = $associado['id'];
                    $_SESSION['associado_nome'] = $associado['nome'] . ' ' . $associado['sobrenome'];
                    $_SESSION['associado_cpf'] = $associado['cpf'];
                    $_SESSION['associado_email'] = $associado['email'] ?? '';
                    $_SESSION['portal_acesso_via'] = 'login_comum';

                    // Vincula a sessão principal SOMENTE quando existe vínculo real
                    // (usuarios.associado_id ou associados.user_id). Casar por
                    // e-mail permitia escalonamento de privilégio.
                    $userVinculado = PortalAccess::usuarioVinculadoAoAssociado((int) $associado['id']);
                    if ($userVinculado) {
                        $_SESSION['user_id'] = (int) $userVinculado['id'];
                        $_SESSION['user_nivel'] = (int) $userVinculado['nivel'];
                        $_SESSION['user_table'] = 'usuarios';
                    }

                    AuditLog::registrar('PORTAL_LOGIN', 'associados', (int) $associado['id'], 'via=login_cpf');

                    header('Location: ' . SITE_URL . '/portal/area');
                    exit;
                } else {
                    PortalAccess::registrarTentativa();
                    $mensagem = 'CPF ou senha inválidos.';
                    $mensagem_tipo = 'danger';
                }
            }
        }
        elseif ($acao === 'solicitar_magic_link') {
            // Solicitar link mágico por e-mail
            $cpf = $entrada('cpf');

            if (trim($cpf) === '') {
                $mensagem = 'Informe seu CPF.';
                $mensagem_tipo = 'danger';
            } elseif ($tentativasExcedidas) {
                $mensagem = 'Muitas solicitações em sequência. Aguarde alguns minutos e tente novamente.';
                $mensagem_tipo = 'warning';
            } else {
                PortalAccess::registrarTentativa();

                $associado = PortalAccess::buscarAssociadoAtivoPorCpf($cpf);

                // A pessoa pode ter dois e-mails: o do cadastro (família) e o da
                // conta de sistema (individual). O link vai para o e-mail de
                // acesso quando existir conta vinculada.
                $emailAcesso = $associado
                    ? PortalAccess::emailDeAcessoDoAssociado((int) $associado['id'], (string) ($associado['email'] ?? ''))
                    : '';

                if ($associado && $emailAcesso !== '') {
                    // Usa a classe PasswordReset: grava o SHA-256 em token_hash e
                    // invalida tokens anteriores do mesmo e-mail. O texto puro só vai no link.
                    $token = $passwordReset->createToken($emailAcesso);
                    $link = SITE_URL . "/portal/login?etapa=verificar_token&token=" . $token;

                    // Envio por e-mail (o link NUNCA deve ser exposto a terceiros)
                    $envio = ['success' => false, 'error' => 'Mailer indisponível.'];
                    if (class_exists('Mailer')) {
                        $corpo = '<p>Olá, <strong>' . htmlspecialchars($associado['nome']) . '</strong>!</p>'
                               . '<p>Recebemos uma solicitação de acesso ao Portal do Associado. Clique no botão para entrar:</p>'
                               . '<p style="text-align:center;margin:24px 0;"><a href="' . htmlspecialchars($link) . '" style="background:#198754;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;">Acessar o Portal</a></p>'
                               . '<p style="font-size:12px;color:#6b7280;">O link expira em 60 minutos e só pode ser usado uma vez. Se você não solicitou, ignore este e-mail.</p>';
                        $envio = Mailer::send(
                            $emailAcesso,
                            'Seu acesso ao Portal do Associado - ' . SITE_TITLE,
                            Mailer::layout('Acesso ao Portal do Associado', $corpo)
                        );
                    }
                    if (empty($envio['success'])) {
                        error_log('Portal: falha ao enviar link magico para ' . PortalAccess::mascararCpf($associado['cpf']) . ' - ' . ($envio['error'] ?? 'erro desconhecido'));
                    }

                    AuditLog::registrar(
                        'PORTAL_MAGIC_LINK',
                        'associados',
                        (int) $associado['id'],
                        'solicitado_por=' . ($_SESSION['user_id'] ?? '—') . ' email_enviado=' . (empty($envio['success']) ? '0' : '1')
                    );

                    if (PortalAccess::ehTitularDoCpf($associado['cpf'], $user)) {
                        // Apenas o próprio titular autenticado vê o link na tela (apoio em testes)
                        $avisoEmail = empty($envio['success'])
                            ? '<br><small class="text-warning">O envio de e-mail não está configurado neste ambiente; utilize o link abaixo.</small>'
                            : '<br><small>Também enviamos o link para <strong>' . htmlspecialchars($emailAcesso) . '</strong>.</small>';
                        $mensagem = "Link mágico gerado!" . $avisoEmail . "<br><br>
                        <strong>Link para teste:</strong><br>
                        <code style='word-break: break-all; background:#f8f9fa; padding:10px; display:block;'>" . htmlspecialchars($link) . "</code><br><br>
                        <a href='" . htmlspecialchars($link) . "' class='btn btn-primary btn-sm'>Acessar Portal (Teste)</a>";
                        $mensagem_tipo = 'success';
                    } else {
                        // Nunca revelar o link a terceiros nem confirmar a existência do CPF
                        $mensagem = 'Se o CPF estiver cadastrado e ativo, o link será enviado para o e-mail registrado.';
                        $mensagem_tipo = 'info';
                    }
                    $etapa = 'magic_link';
                } else {
                    // Não revelar se CPF existe ou não (segurança)
                    $mensagem = 'Se o CPF estiver cadastrado e ativo, o link será enviado para o e-mail registrado.';
                    $mensagem_tipo = 'info';
                }
            }
        }
        elseif ($acao === 'verificar_token') {
            // Verificar token do link mágico
            $token = $entrada('token');

            if (empty($token)) {
                $mensagem = 'Token inválido.';
                $mensagem_tipo = 'danger';
            } else {
                // Valida pelo SHA-256: a tabela nao guarda o token em claro.
                $resetToken = $passwordReset->getValidToken($token);
                $reset = false;
                if ($resetToken) {
                    // O token pode ter sido emitido para o e-mail do cadastro OU
                    // para o e-mail da conta de sistema vinculada.
                    $associadoIdToken = PortalAccess::associadoIdPorEmail((string) $resetToken['email']);
                    $stmt = $db->prepare("SELECT id AS associado_id, id, nome, sobrenome, cpf, email FROM associados WHERE id = ? AND status = 'ativo' LIMIT 1");
                    $stmt->execute([$associadoIdToken]);
                    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($reset && !$passwordReset->markUsed($resetToken['id'])) {
                        // Token já consumido por outra requisição (consumo atômico)
                        $reset = false;
                    }
                }

                if ($reset) {
                    // Evita fixação de sessão no momento da autenticação
                    session_regenerate_id(true);
                    $limparSessaoPortal();

                    // Logar associado
                    $_SESSION['associado_id'] = $reset['associado_id'];
                    $_SESSION['associado_nome'] = $reset['nome'] . ' ' . $reset['sobrenome'];
                    $_SESSION['associado_cpf'] = $reset['cpf'];
                    $_SESSION['associado_email'] = $reset['email'];
                    $_SESSION['portal_acesso_via'] = 'magic_link';

                    // Vincula a sessão principal SOMENTE quando existe vínculo real
                    // (usuarios.associado_id ou associados.user_id). Casar por
                    // e-mail permitia escalonamento de privilégio.
                    $userVinculado = PortalAccess::usuarioVinculadoAoAssociado((int) $reset['associado_id']);
                    if ($userVinculado) {
                        $_SESSION['user_id'] = (int) $userVinculado['id'];
                        $_SESSION['user_nivel'] = (int) $userVinculado['nivel'];
                        $_SESSION['user_table'] = 'usuarios';
                    }

                    AuditLog::registrar('PORTAL_LOGIN_MAGIC_LINK', 'associados', (int) $reset['associado_id'], 'via=magic_link');

                    header('Location: ' . SITE_URL . '/portal/area');
                    exit;
                } else {
                    $mensagem = 'Link inválido ou expirado. Solicite um novo.';
                    $mensagem_tipo = 'danger';
                    $etapa = 'login';
                }
            }
        }
    }
}

// ---------------------------------------------------------------------------
// CPF do usuário autenticado: preenche e TRAVA os campos das abas
// "CPF + Senha" e "Link Mágico", para que ele só consiga solicitar acesso
// para si próprio. É apenas UX: o backend revalida a mesma regra em
// PortalAccess (um POST forjado continua bloqueado).
// ---------------------------------------------------------------------------
$cpfVinculadoLogado = '';
$meuCpfFormatado = '';
if ($user->isLoggedIn() || PortalAccess::temSessaoPortal()) {
    $cpfVinculadoLogado = PortalAccess::cpfDoUsuario($user);
    if ($cpfVinculadoLogado !== '') {
        $meuCpfFormatado = PortalAccess::formatarCpf($cpfVinculadoLogado);
    }
}
$temCpfVinculado = ($meuCpfFormatado !== '');
$cpfCampoAtributos = $temCpfVinculado
    ? ' value="' . htmlspecialchars($meuCpfFormatado) . '" readonly aria-readonly="true" data-cpf-vinculado="1"'
    : '';

$page_title = "Portal do Associado - Login";
// Página pública: o associado precisa conseguir pedir o Link Mágico e entrar
// com CPF+senha SEM já estar logado no sistema principal. A autorização de
// cada ação é feita por PortalAccess (bloqueio de CPF de terceiros).
$PERMITIR_ACESSO_ANONIMO = true;
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0"><i class="bi bi-person-circle me-2"></i>Portal do Associado</h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-center text-muted mb-4">Acesse sua área restrita</p>

                    <?php if ($user->isLoggedIn() && !$isAdminOrDiretoriaExecutiva && !$temCpfVinculado): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        Não identificamos um CPF vinculado ao seu usuário. Por segurança, o acesso aos
                        dados de outros associados é bloqueado. Procure a secretaria para regularizar seu cadastro.
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show">
                            <?php echo $mensagem; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Aba 1: Login CPF + Senha -->
                    <?php if ($etapa === 'login'): ?>
                    <div class="mb-3">
                        <ul class="nav nav-tabs justify-content-center" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-cpf" data-bs-toggle="tab" data-bs-target="#pane-cpf" type="button" role="tab">
                                    <i class="bi bi-person-badge me-1"></i> CPF + Senha
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-magic" data-bs-toggle="tab" data-bs-target="#pane-magic" type="button" role="tab">
                                    <i class="bi bi-envelope me-1"></i> Link Mágico
                                </button>
                            </li>
                            <?php if ($isAdminOrDiretoriaExecutiva): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-consulta" data-bs-toggle="tab" data-bs-target="#pane-consulta" type="button" role="tab">
                                    <i class="bi bi-search me-1"></i> Consultar por CPF
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="tab-content" id="loginTabsContent">
                        <!-- CPF + Senha -->
                        <div class="tab-pane fade show active" id="pane-cpf" role="tabpanel">
                            <form method="post" class="mt-3">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <input type="hidden" name="acao" value="login_cpf">
                                <div class="mb-3">
                                    <label class="form-label">CPF</label>
                                    <input type="text" name="cpf" class="form-control cpf-mask" placeholder="000.000.000-00" required autocomplete="username"<?php echo $cpfCampoAtributos; ?>>
                                    <?php if ($temCpfVinculado): ?>
                                    <div class="form-text text-success"><i class="bi bi-lock-fill me-1"></i> CPF vinculado ao seu usuário — campo somente leitura.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Senha</label>
                                    <input type="password" name="senha" class="form-control" placeholder="Sua senha" required autocomplete="current-password">
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i> Entrar</button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">Ainda não tem senha? Use a aba "Link Mágico" para receber acesso por e-mail.</small>
                            </div>
                        </div>
                        
                        <!-- Link Mágico -->
                        <div class="tab-pane fade" id="pane-magic" role="tabpanel">
                            <form method="post" class="mt-3">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <input type="hidden" name="acao" value="solicitar_magic_link">
                                <div class="mb-3">
                                    <label class="form-label">CPF</label>
                                    <input type="text" name="cpf" class="form-control cpf-mask" placeholder="000.000.000-00" required autocomplete="username"<?php echo $cpfCampoAtributos; ?>>
                                    <?php if ($temCpfVinculado): ?>
                                    <div class="form-text text-success"><i class="bi bi-lock-fill me-1"></i> CPF vinculado ao seu usuário — campo somente leitura.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <div class="form-text">
                                        Enviaremos um link de acesso único para o e-mail cadastrado. O link expira em 30 minutos.
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-send me-1"></i> Enviar Link Mágico</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Consultar por CPF (Admin/Diretoria Executiva) -->
                        <?php if ($isAdminOrDiretoriaExecutiva): ?>
                        <div class="tab-pane fade" id="pane-consulta" role="tabpanel">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-shield-lock me-1"></i>
                                <strong>Modo Administrativo:</strong> Acesso ao portal de qualquer associado ativo via CPF.
                                <br><small class="text-muted">Esta ação será registrada em log de auditoria.</small>
                            </div>
                            <form method="post" class="mt-3">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <input type="hidden" name="acao" value="consultar_cpf">
                                <div class="mb-3">
                                    <label class="form-label">CPF do Associado</label>
                                    <input type="text" name="cpf_consulta" class="form-control cpf-mask" placeholder="000.000.000-00" required autocomplete="username">
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-search me-1"></i> Acessar Portal do Associado</button>
                                </div>
                            </form>
                            
                            <?php if ($usuarioSistema): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Logado no sistema como: <strong><?php echo htmlspecialchars($usuarioSistema['nome'] ?? ''); ?></strong>
                                    <?php if ($user->hasPermission(NIVEL_ADMIN)): ?>
                                        <span class="badge bg-danger ms-2">Admin do Sistema</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark ms-2">Diretoria Executiva</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php elseif ($etapa === 'verificar_token'): ?>
                    <!-- Verificar Token (Link Mágico) -->
                    <div class="text-center mb-3">
                        <i class="bi bi-envelope-check text-primary" style="font-size: 3rem;"></i>
                        <h5 class="mt-2">Verificando seu acesso...</h5>
                        <p class="text-muted">Por favor, aguarde enquanto validamos seu link.</p>
                    </div>
                    <form method="post" class="mt-3">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <input type="hidden" name="acao" value="verificar_token">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Confirmar Acesso</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <a href="<?php echo SITE_URL; ?>/portal/login" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
                    </div>
                    
                    <?php elseif ($etapa === 'magic_link'): ?>
                    <!-- Link enviado (sucesso) -->
                    <div class="text-center mb-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-2">Link Enviado!</h5>
                    </div>
                    <div class="text-center">
                        <a href="<?php echo SITE_URL; ?>/portal/login" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar ao Login</a>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    <div class="text-center">
                        <small class="text-muted">
                            <i class="bi bi-shield-lock me-1"></i> Acesso restrito a associados ativos.<br>
                            Dúvidas? Entre em contato com a secretaria.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Máscara CPF simples
document.querySelectorAll('.cpf-mask').forEach(function(input) {
    if (input.readOnly) return; // CPF do próprio usuário: campo travado
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    });
});

// Auto-submit token se veio na URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('etapa') === 'verificar_token' && urlParams.get('token')) {
        // Auto-submit após 1 segundo
        setTimeout(function() {
            document.querySelector('form[method="post"]').submit();
        }, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
