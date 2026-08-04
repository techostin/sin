<?php
$page_title = "Configurações";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';

// Processar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tema = $_POST['tema'] ?? 'light';
    $notificacoes = isset($_POST['notificacoes']) ? 1 : 0;
    $idioma = $_POST['idioma'] ?? 'pt-br';
    
    // Salvar configurações na sessão (em um sistema real, salvaria no banco)
    $_SESSION['user_config'] = [
        'tema' => $tema,
        'notificacoes' => $notificacoes,
        'idioma' => $idioma
    ];
    
    $mensagem = '<div class="alert alert-success">Configurações salvas com sucesso!</div>';
}

// Carregar configurações atuais
$config = $_SESSION['user_config'] ?? [
    'tema' => 'light',
    'notificacoes' => 1,
    'idioma' => 'pt-br'
];
?>

<h1 class="mt-4">Configurações</h1>
<p>Personalize suas preferências no sistema.</p>

<?php if ($mensagem) echo $mensagem; ?>

<div class="row justify-content-center">
    <!-- Linha 1: Ações Rápidas + Informações do Sistema -->
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Ações Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/usuario/perfil" class="btn btn-outline-primary">
                        <i class="bi bi-person-circle"></i> Editar Perfil
                    </a>
                    <button class="btn btn-outline-secondary" disabled>
                        <i class="bi bi-download"></i> Exportar Dados
                    </button>
                    <button class="btn btn-outline-info" disabled>
                        <i class="bi bi-question-circle"></i> Ajuda
                    </button>
                    <button class="btn btn-outline-danger" disabled>
                        <i class="bi bi-trash"></i> Excluir Conta
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Informações do Sistema</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Versão do Sistema:</small><br>
                    <strong>1.0.0</strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Última Atualização:</small><br>
                    <strong><?php echo date('d/m/Y'); ?></strong>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Suporte:</small><br>
                    <strong>suporte@sisagro.com</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Linha 2: Preferências Gerais + Segurança -->
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Preferências Gerais</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="tema" class="form-label">Tema da Interface</label>
                        <select class="form-select" id="tema" name="tema">
                            <option value="light" <?php echo $config['tema'] === 'light' ? 'selected' : ''; ?>>Claro</option>
                            <option value="dark" <?php echo $config['tema'] === 'dark' ? 'selected' : ''; ?>>Escuro</option>
                            <option value="auto" <?php echo $config['tema'] === 'auto' ? 'selected' : ''; ?>>Automático</option>
                        </select>
                        <div class="form-text">Escolha o tema visual da interface.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="idioma" class="form-label">Idioma</label>
                        <select class="form-select" id="idioma" name="idioma">
                            <option value="pt-br" <?php echo $config['idioma'] === 'pt-br' ? 'selected' : ''; ?>>Português (Brasil)</option>
                            <option value="en" <?php echo $config['idioma'] === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="es" <?php echo $config['idioma'] === 'es' ? 'selected' : ''; ?>>Español</option>
                        </select>
                        <div class="form-text">Idioma preferido para a interface.</div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                        <a href="<?php echo SITE_URL; ?>/" class="btn btn-secondary">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-5 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Segurança</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6>Autenticação de Dois Fatores</h6>
                    <p class="text-muted">Adicione uma camada extra de segurança à sua conta.</p>
                    <button class="btn btn-outline-primary btn-sm" disabled>Configurar 2FA</button>
                    <small class="text-muted d-block mt-1">Funcionalidade em desenvolvimento</small>
                </div>
                
                <div class="mb-3">
                    <h6>Sessões Ativas</h6>
                    <p class="text-muted">Gerencie suas sessões ativas no sistema.</p>
                    <button class="btn btn-outline-warning btn-sm" disabled>Ver Sessões</button>
                    <small class="text-muted d-block mt-1">Funcionalidade em desenvolvimento</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Linha 3: Configurações de Notificações (largura total) -->
    <div class="col-md-10 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configurações de Notificações</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo SITE_URL; ?>/usuario/salvar_notificacoes">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notificacoes_ativas" name="notificacoes_ativas" checked>
                                    <label class="form-check-label" for="notificacoes_ativas">
                                        Ativar notificações de eventos
                                    </label>
                                </div>
                                <div class="form-text">Receber lembretes sobre eventos próximos.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="dias_antecedencia" class="form-label">Dias de Antecedência para Eventos</label>
                                <select class="form-select" id="dias_antecedencia" name="dias_antecedencia">
                                    <option value="1">1 dia antes</option>
                                    <option value="3">3 dias antes</option>
                                    <option value="7" selected>7 dias antes</option>
                                    <option value="14">14 dias antes</option>
                                    <option value="30">30 dias antes</option>
                                </select>
                                <div class="form-text">Com quantos dias de antecedência você quer ser notificado sobre eventos.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <h6>Tipos de Notificação</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_eventos_hoje" name="notif_eventos_hoje" checked>
                                    <label class="form-check-label" for="notif_eventos_hoje">Eventos de hoje</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_eventos_proximos" name="notif_eventos_proximos" checked>
                                    <label class="form-check-label" for="notif_eventos_proximos">Eventos próximos</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_eventos_atrasados" name="notif_eventos_atrasados" checked>
                                    <label class="form-check-label" for="notif_eventos_atrasados">Eventos atrasados</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">Salvar Notificações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 