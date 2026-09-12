<?php
$page_title = "Painel do Agricultor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Restringir o Painel do Agricultor a usuários do tipo agricultor (NIVEL_USUARIO)
if ((int)($_SESSION['user_nivel'] ?? 0) !== NIVEL_USUARIO) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Você não tem permissão para acessar este recurso.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$associado_id = $user->getAssociadoId();

if (!$associado_id) {
    try {
        $stmt = $db->query("SELECT id FROM associados LIMIT 1");
        $first = $stmt->fetch();
        $associado_id = $first ? $first['id'] : 1;
    } catch (PDOException $e) {
        $associado_id = 1;
    }
}

$dados = [
    'total_atividades' => 0,
    'total_insumos' => 0,
    'total_ocorrencias' => 0,
    'status_certificacao' => 'Nenhuma',
    'validade_certificacao' => '-',
    'proxima_visita' => null,
    'atividades' => [],
    'conformidade_score' => 0,
    'nc_abertas' => 0,
    'pendentes_hoje' => 0,
    'atividades_semana' => 0,
    'insumos_baixo_estoque' => 0,
    'ultima_visita' => null,
    'total_animais' => 0,
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM atividades_diarias WHERE associado_id = ? AND excluido = 0");
    $stmt->execute([$associado_id]);
    $dados['total_atividades'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM insumos WHERE associado_id = ? AND excluido = 0");
    $stmt->execute([$associado_id]);
    $dados['total_insumos'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM ocorrencias_manejo WHERE associado_id = ? AND excluido = 0");
    $stmt->execute([$associado_id]);
    $dados['total_ocorrencias'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT status, data_validade FROM certificacao_participativa WHERE associado_id = ? AND excluido = 0 ORDER BY data_validade DESC LIMIT 1");
    $stmt->execute([$associado_id]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cert) {
        $dados['status_certificacao'] = $cert['status'];
        $dados['validade_certificacao'] = $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '-';
    }

    $stmt = $db->prepare("SELECT data_visita, status FROM visitas_tecnicas WHERE associado_id = ? AND status = 'Agendada' ORDER BY data_visita ASC LIMIT 1");
    $stmt->execute([$associado_id]);
    $dados['proxima_visita'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT data_visita, pontuacao, conformidade FROM visitas_tecnicas WHERE associado_id = ? AND status = 'Realizada' ORDER BY data_visita DESC LIMIT 1");
    $stmt->execute([$associado_id]);
    $dados['ultima_visita'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT COUNT(*) FROM nao_conformidades nc JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id WHERE vt.associado_id = ? AND nc.status IN ('Aberta', 'Em Correção')");
    $stmt->execute([$associado_id]);
    $dados['nc_abertas'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM atividades_diarias WHERE associado_id = ? AND excluido = 0 AND data_atividade = CURDATE()");
    $stmt->execute([$associado_id]);
    $dados['pendentes_hoje'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM atividades_diarias WHERE associado_id = ? AND excluido = 0 AND YEARWEEK(data_atividade, 1) = YEARWEEK(CURDATE(), 1)");
    $stmt->execute([$associado_id]);
    $dados['atividades_semana'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM insumos WHERE associado_id = ? AND excluido = 0 AND quantidade_estoque < 10");
    $stmt->execute([$associado_id]);
    $dados['insumos_baixo_estoque'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM animais WHERE associado_id = ? AND excluido = 0");
    $stmt->execute([$associado_id]);
    $dados['total_animais'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT ad.*, p.propriedade, l.nome AS lavoura_nome FROM atividades_diarias ad
        LEFT JOIN propriedades p ON ad.propriedade_id = p.id
        LEFT JOIN lavouras l ON ad.lavoura_id = l.id
        WHERE ad.associado_id = ? AND ad.excluido = 0 ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC LIMIT 5");
    $stmt->execute([$associado_id]);
    $dados['atividades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $score = 100;
    $itens_peso = [
        'certificacao' => 30,
        'nc_abertas' => 25,
        'atividades_semana' => 20,
        'insumos_baixo' => 15,
        'ocorrencias' => 10,
    ];
    if ($dados['status_certificacao'] !== 'Ativa') $score -= $itens_peso['certificacao'];
    $score -= min($dados['nc_abertas'] * 8, $itens_peso['nc_abertas']);
    if ($dados['atividades_semana'] < 2) $score -= $itens_peso['atividades_semana'];
    $score -= min($dados['insumos_baixo_estoque'] * 5, $itens_peso['insumos_baixo']);
    $dados['conformidade_score'] = max(0, $score);

} catch (PDOException $e) {
    error_log("Erro no dashboard do agricultor: " . $e->getMessage());
}

$cert_badge_class = 'bg-secondary';
if ($dados['status_certificacao'] === 'Ativa') $cert_badge_class = 'bg-success';
elseif ($dados['status_certificacao'] === 'Vencida') $cert_badge_class = 'bg-danger';
elseif ($dados['status_certificacao'] === 'Em Conversão') $cert_badge_class = 'bg-warning text-dark';

$score_color = $dados['conformidade_score'] >= 80 ? 'success' : ($dados['conformidade_score'] >= 50 ? 'warning' : 'danger');
?>

<div class="container-fluid px-3 px-md-4 py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="h2 text-dark font-weight-bold">Olá, <?php echo htmlspecialchars($_SESSION['user_nome']); ?>!</h1>
            <p class="text-muted mb-0">Acompanhe a conformidade orgânica e gerencie suas atividades no campo.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge rounded-pill <?php echo $cert_badge_class; ?> px-3 py-2 fs-6 me-2">
                <i class="bi bi-award"></i> <?php echo htmlspecialchars($dados['status_certificacao']); ?>
            </span>
            <span class="badge rounded-pill bg-<?php echo $score_color; ?> px-3 py-2 fs-6">
                <i class="bi bi-graph-up"></i> Score: <?php echo $dados['conformidade_score']; ?>%
            </span>
        </div>
    </div>

    <h3 class="h5 mb-3 text-secondary"><i class="bi bi-lightning-charge-fill text-warning"></i> Ações Rápidas</h3>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/novo" class="card h-100 text-decoration-none bg-gradient-success text-white border-0 shadow-sm transition-hover">
                <div class="card-body text-center py-4">
                    <i class="bi bi-calendar-plus display-5 mb-2 d-block"></i>
                    <span class="fw-bold d-block">Nova Atividade</span>
                    <small class="opacity-75">Registrar tarefa diária</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo" class="card h-100 text-decoration-none bg-gradient-danger text-white border-0 shadow-sm transition-hover">
                <div class="card-body text-center py-4">
                    <i class="bi bi-exclamation-triangle-fill display-5 mb-2 d-block"></i>
                    <span class="fw-bold d-block">Registrar Ocorrência</span>
                    <small class="opacity-75">Praga, doença ou clima</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/novo" class="card h-100 text-decoration-none bg-gradient-info text-white border-0 shadow-sm transition-hover">
                <div class="card-body text-center py-4">
                    <i class="bi bi-droplet-fill display-5 mb-2 d-block"></i>
                    <span class="fw-bold d-block">Registrar Insumo</span>
                    <small class="opacity-75">Entrada de fertilizantes/outros</small>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos/validacao" class="card h-100 text-decoration-none text-white border-0 shadow-sm transition-hover" style="background: linear-gradient(135deg, #0dcaf0 0%, #055160 100%);">
                <div class="card-body text-center py-4">
                    <i class="bi bi-shield-check display-5 mb-2 d-block"></i>
                    <span class="fw-bold d-block">Validar Insumo</span>
                    <small class="opacity-75">Verificar aprovação orgânica</small>
                </div>
            </a>
        </div>
    </div>

    <!-- Banner de alerta de sincronização offline (aparece só quando offline) -->
    <div id="offline-alert" class="alert alert-warning d-flex align-items-center gap-2 mb-4" style="display:none!important; border-radius:12px;">
        <i class="bi bi-wifi-off fs-5"></i>
        <div><strong>Modo offline ativo.</strong> Seus registros estão sendo salvos localmente e serão sincronizados automaticamente.</div>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/sincronizacao" class="btn btn-sm btn-warning ms-auto">Ver fila</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 bg-light-success p-3 rounded-3 me-3 text-success">
                        <i class="bi bi-calendar-check-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Atividades</h6>
                        <h4 class="mb-0 fw-bold"><?php echo $dados['total_atividades']; ?></h4>
                        <small class="text-muted"><?php echo $dados['atividades_semana']; ?> esta semana</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 bg-light-info p-3 rounded-3 me-3 text-info">
                        <i class="bi bi-basket3-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Insumos</h6>
                        <h4 class="mb-0 fw-bold"><?php echo $dados['total_insumos']; ?></h4>
                        <?php if ($dados['insumos_baixo_estoque'] > 0): ?>
                            <small class="text-danger"><?php echo $dados['insumos_baixo_estoque']; ?> com estoque baixo</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 bg-light-danger p-3 rounded-3 me-3 text-danger">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Não Conformidades</h6>
                        <h4 class="mb-0 fw-bold"><?php echo $dados['nc_abertas']; ?></h4>
                        <small class="text-muted">pendentes de correção</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 bg-light-warning p-3 rounded-3 me-3 text-warning">
                        <i class="bi bi-shield-lock-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Próxima Inspeção</h6>
                        <h5 class="mb-0 fw-bold">
                            <?php echo $dados['proxima_visita'] ? date('d/m/Y', strtotime($dados['proxima_visita']['data_visita'])) : 'Nenhuma agendada'; ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-shrink-0 bg-light-success p-3 rounded-3 me-3 text-success">
                        <i class="bi bi-egg-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Rebanho</h6>
                        <h4 class="mb-0 fw-bold"><?php echo $dados['total_animais']; ?></h4>
                        <small class="text-muted"><a href="<?php echo SITE_URL; ?>/producao-animal">produção animal</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clipboard-data text-success me-2"></i>Indicadores de Conformidade</h5>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="small text-muted">Score de Conformidade</label>
                        <div class="progress" style="height:24px;">
                            <div class="progress-bar bg-<?php echo $score_color; ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $dados['conformidade_score']; ?>%;">
                                <?php echo $dados['conformidade_score']; ?>%
                            </div>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-award me-2 text-success"></i>Certificação</span>
                            <span class="badge <?php echo $cert_badge_class; ?> rounded-pill"><?php echo htmlspecialchars($dados['status_certificacao']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-calendar-check me-2 text-primary"></i>Atividades na Semana</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $dados['atividades_semana']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-exclamation-triangle me-2 text-danger"></i>NCs Abertas</span>
                            <span class="badge bg-<?php echo $dados['nc_abertas'] > 0 ? 'danger' : 'success'; ?> rounded-pill"><?php echo $dados['nc_abertas']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-droplet me-2 text-info"></i>Insumos Estoque Baixo</span>
                            <span class="badge bg-<?php echo $dados['insumos_baixo_estoque'] > 0 ? 'warning' : 'secondary'; ?> rounded-pill"><?php echo $dados['insumos_baixo_estoque']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="bi bi-shield-check me-2 text-secondary"></i>Última Visita</span>
                            <span class="text-muted small">
                                <?php echo $dados['ultima_visita'] ? date('d/m/Y', strtotime($dados['ultima_visita']['data_visita'])) . ' - ' . ($dados['ultima_visita']['pontuacao'] ?? 'N/A') . '%' : 'Nenhuma'; ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check text-warning me-2"></i>Tarefas e Pendências</h5>
                </div>
                <div class="card-body pt-0">
                    <?php
                    $tarefas = [];
                    if ($dados['pendentes_hoje'] > 0) {
                        $tarefas[] = ['icon' => 'bi-calendar-day', 'color' => 'primary', 'text' => "{$dados['pendentes_hoje']} atividade(s) para hoje"];
                    }
                    if ($dados['nc_abertas'] > 0) {
                        $tarefas[] = ['icon' => 'bi-exclamation-triangle', 'color' => 'danger', 'text' => "{$dados['nc_abertas']} não conformidade(s) em aberto"];
                    }
                    if ($dados['insumos_baixo_estoque'] > 0) {
                        $tarefas[] = ['icon' => 'bi-basket3', 'color' => 'warning', 'text' => "{$dados['insumos_baixo_estoque']} insumo(s) com estoque baixo"];
                    }
                    if ($dados['status_certificacao'] === 'Vencida') {
                        $tarefas[] = ['icon' => 'bi-award', 'color' => 'danger', 'text' => 'Certificação vencida. Renove o mais breve possível.'];
                    }
                    if ($dados['status_certificacao'] === 'Em Conversão') {
                        $tarefas[] = ['icon' => 'bi-hourglass-split', 'color' => 'warning', 'text' => 'Certificação em conversão. Mantenha o caderno atualizado.'];
                    }
                    if ($dados['atividades_semana'] === 0) {
                        $tarefas[] = ['icon' => 'bi-journal-plus', 'color' => 'info', 'text' => 'Nenhuma atividade registrada esta semana'];
                    }
                    if ($dados['proxima_visita']) {
                        $dias = floor((strtotime($dados['proxima_visita']['data_visita']) - time()) / 86400);
                        if ($dias <= 7) {
                            $tarefas[] = ['icon' => 'bi-calendar-event', 'color' => 'success', 'text' => "Visita técnica agendada para " . date('d/m/Y', strtotime($dados['proxima_visita']['data_visita'])) . ($dias <= 1 ? ' (Amanhã!)' : " ($dias dia(s))")];
                        }
                    }
                    if (empty($tarefas)) {
                        echo '<div class="text-center py-4"><i class="bi bi-emoji-smile fs-1 text-success mb-2 d-block"></i><span class="text-muted">Tudo em dia! Nenhuma pendência encontrada.</span></div>';
                    } else {
                        echo '<div class="list-group list-group-flush">';
                        foreach ($tarefas as $t) {
                            echo '<div class="list-group-item d-flex align-items-center px-0 border-0 border-bottom">';
                            echo '<i class="bi ' . $t['icon'] . ' text-' . $t['color'] . ' me-3 fs-5"></i>';
                            echo '<span class="small">' . $t['text'] . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event text-info me-2"></i>Próximos Eventos & Visitas</h5>
                </div>
                <div class="card-body pt-0">
                    <?php
                    try {
                        $stmt = $db->prepare("SELECT e.id, e.nome, e.data_evento, e.local, e.status, 
                            COALESCE(p.status_presenca, 'Não Confirmado') as status_presenca
                            FROM eventos e
                            LEFT JOIN evento_presenca p ON p.evento_id = e.id AND p.associado_id = ?
                            WHERE e.data_evento >= CURDATE() AND e.status = 'Agendado'
                            ORDER BY e.data_evento ASC LIMIT 5");
                        $stmt->execute([$associado_id]);
                        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $eventos = [];
                    }

                    if (!empty($eventos)): ?>
                        <div class="list-group list-group-flush">
                        <?php foreach ($eventos as $evt): ?>
                            <div class="list-group-item px-0 border-0 border-bottom">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 small fw-bold"><?php echo htmlspecialchars($evt['nome']); ?></h6>
                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($evt['data_evento'])); ?></small>
                                </div>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($evt['local'] ?? 'A definir'); ?>
                                </p>
                                <span class="badge bg-<?php echo $evt['status_presenca'] === 'Confirmado' ? 'success' : ($evt['status_presenca'] === 'Presente' ? 'info' : 'secondary'); ?> btn-sm mt-1">
                                    <?php echo $evt['status_presenca']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <a href="<?php echo SITE_URL; ?>/eventos" class="btn btn-outline-primary btn-sm w-100">Ver Todos os Eventos</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-plus fs-1 text-muted mb-2 d-block"></i>
                            <span class="text-muted">Nenhum evento agendado nos próximos dias.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-task text-success me-2"></i>Últimas Atividades</h5>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/atividades-diarias" class="btn btn-outline-success btn-sm">Ver tudo</a>
                </div>
                <div class="card-body pt-0">
                    <?php if (empty($dados['atividades'])): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-journal-x fs-1 text-muted mb-2 d-block"></i>
                            <span class="text-muted">Nenhum registro encontrado. Comece clicando em <strong>Nova Atividade</strong>!</span>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Atividade</th>
                                        <th>Data</th>
                                        <th>Propriedade</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dados['atividades'] as $ativ): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($ativ['nome']); ?></td>
                                            <td><?php echo $ativ['data_atividade'] ? date('d/m/Y', strtotime($ativ['data_atividade'])) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($ativ['propriedade'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-light-success text-success border border-success-subtle px-2 py-1">
                                                    <?php echo htmlspecialchars($ativ['status'] ?? 'Concluído'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/modulos/caderno_campo/atividade_diaria/editar.php?id=<?php echo $ativ['id']; ?>" class="btn btn-sm btn-light border" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-light-success-subtle mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-lightbulb-fill text-success me-2"></i>Resumo da Certificação</h5>
                    <p class="mb-2"><strong>Situação:</strong> <?php echo htmlspecialchars($dados['status_certificacao']); ?></p>
                    <p class="mb-3"><strong>Válido até:</strong> <?php echo htmlspecialchars($dados['validade_certificacao']); ?></p>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($dados['status_certificacao'] === 'Ativa') ? '100%' : '50%'; ?>;"></div>
                    </div>
                    <small class="text-muted">Mantenha seu Caderno de Campo atualizado para evitar suspensões.</small>
                    <div class="mt-3 d-grid gap-1">
                        <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-sm btn-outline-success"><i class="bi bi-award me-1"></i>Gerenciar Certificação</a>
                        <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-sm btn-outline-primary"><i class="bi bi-clipboard-check me-1"></i>Visitas Técnicas</a>
                        <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="btn btn-sm btn-outline-warning"><i class="bi bi-exclamation-circle me-1"></i>Não Conformidades</a>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="bi bi-question-circle text-info me-2"></i>Precisa de Ajuda?</h5>
                    <p class="text-muted small">Caso identifique dúvidas nas normas da Portaria 52/2021 ou no uso do sistema, consulte o manual ou contate o técnico do seu grupo SPG/OCS.</p>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/sincronizacao" class="btn btn-sm btn-outline-secondary w-100 mb-2">
                        <i class="bi bi-arrow-repeat me-1"></i>Ver Status Offline
                    </a>
                    <a href="<?php echo SITE_URL; ?>/relatorios" class="btn btn-sm btn-outline-secondary w-100">Manual de Uso</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-success { background: linear-gradient(135deg, #198754 0%, #20c997 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
.bg-gradient-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
.bg-gradient-secondary { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
.bg-light-success { background-color: #d1e7dd !important; }
.bg-light-info { background-color: #cff4fc !important; }
.bg-light-warning { background-color: #fff3cd !important; }
.bg-light-danger { background-color: #f8d7da !important; }
.bg-light-success-subtle { background-color: #f3fcf7 !important; border: 1px solid #d1e7dd; }
.transition-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.transition-hover:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15) !important; }

/* === FAB Mobile === */
#fab-trigger {
    display: none;
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #198754, #20c997);
    color: #fff;
    border: none;
    font-size: 1.6rem;
    box-shadow: 0 6px 20px rgba(25,135,84,.45);
    z-index: 1050;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    transition: transform .2s, box-shadow .2s;
}
#fab-trigger:hover { transform: scale(1.1); box-shadow: 0 10px 28px rgba(25,135,84,.55); }
#fab-menu {
    display: none;
    position: fixed;
    bottom: 5rem;
    right: 1.5rem;
    z-index: 1049;
    flex-direction: column;
    gap: .6rem;
    align-items: flex-end;
}
#fab-menu.open { display: flex; }
.fab-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    text-decoration: none;
    animation: fabIn .2s ease;
}
.fab-item span {
    background: #212529;
    color: #fff;
    padding: .3rem .8rem;
    border-radius: 20px;
    font-size: .85rem;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.fab-item .fab-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 3px 12px rgba(0,0,0,.25);
}
@keyframes fabIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (max-width: 768px) {
    #fab-trigger { display: flex; }
}
</style>

<!-- FAB Mobile (visível apenas em smartphones) -->
<button id="fab-trigger" aria-label="Ações rápidas">
    <i class="bi bi-plus-lg" id="fab-icon"></i>
</button>
<div id="fab-menu" role="menu">
    <a class="fab-item" href="<?php echo SITE_URL; ?>/atividade-diaria/novo">
        <span>Nova Atividade</span>
        <div class="fab-icon" style="background:linear-gradient(135deg,#198754,#20c997);color:#fff;"><i class="bi bi-calendar-plus"></i></div>
    </a>
    <a class="fab-item" href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo">
        <span>Registrar Ocorrência</span>
        <div class="fab-icon" style="background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;"><i class="bi bi-bug"></i></div>
    </a>
    <a class="fab-item" href="<?php echo SITE_URL; ?>/insumo/novo">
        <span>Cadastrar Insumo</span>
        <div class="fab-icon" style="background:linear-gradient(135deg,#0dcaf0,#0d6efd);color:#fff;"><i class="bi bi-droplet-fill"></i></div>
    </a>
    <a class="fab-item" href="<?php echo SITE_URL; ?>/caderno-campo/insumos/validacao">
        <span>Validar Insumo</span>
        <div class="fab-icon" style="background:linear-gradient(135deg,#20c997,#0d6efd);color:#fff;"><i class="bi bi-shield-check"></i></div>
    </a>
</div>
<script>
(function() {
    var btn = document.getElementById('fab-trigger');
    var menu = document.getElementById('fab-menu');
    var icon = document.getElementById('fab-icon');
    var open = false;
    if (!btn) return;
    btn.addEventListener('click', function() {
        open = !open;
        menu.classList.toggle('open', open);
        icon.className = open ? 'bi bi-x-lg' : 'bi bi-plus-lg';
        btn.style.background = open
            ? 'linear-gradient(135deg, #495057, #212529)'
            : 'linear-gradient(135deg, #198754, #20c997)';
    });
    document.addEventListener('click', function(e) {
        if (open && !btn.contains(e.target) && !menu.contains(e.target)) {
            open = false;
            menu.classList.remove('open');
            icon.className = 'bi bi-plus-lg';
            btn.style.background = 'linear-gradient(135deg, #198754, #20c997)';
        }
    });
    // Mostrar banner offline
    var offlineAlert = document.getElementById('offline-alert');
    function updateOffline() {
        if (offlineAlert) offlineAlert.style.display = navigator.onLine ? 'none' : 'flex';
    }
    updateOffline();
    window.addEventListener('online', updateOffline);
    window.addEventListener('offline', updateOffline);
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
