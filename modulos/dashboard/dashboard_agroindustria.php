<?php
$page_title = "Painel de Agroindústria";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Restringir ao perfil Agroindústria: gestor/admin sempre; associados que
// possuem agroindústria (somente agroindústria OU agricultor com agroindústria).
$nivelAtual = (int)($_SESSION['user_nivel'] ?? 0);
$podeAcessar = $nivelAtual <= NIVEL_GESTOR || ($nivelAtual === NIVEL_USUARIO && $user->temAgroindustria());
if (!$podeAcessar) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Este painel é exclusivo de Produtores de Agroindústria.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db        = Database::getInstance()->getConnection();
$assoc_id  = $user->getAssociadoId();
$isGestor  = $user->hasPermission(NIVEL_GESTOR);

$KPI = [
    'total_ordens'         => 0,
    'ordens_aprovadas'     => 0,
    'ordens_alerta'        => 0,
    'receitas'             => 0,
    'higienizacoes'        => 0,
    'higienizacao_24h'     => 0,
    'filtro_assoc'         => $isGestor ? null : $assoc_id,
];

try {
    $where   = $isGestor ? 'WHERE o.excluido = 0' : 'WHERE o.excluido = 0 AND o.associado_id = ?';
    $params  = $isGestor ? [] : [(int)$assoc_id];

    $stmt = $db->prepare("SELECT COUNT(*) FROM ordens_producao o {$where}");
    $stmt->execute($params);
    $KPI['total_ordens'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM ordens_producao o {$where} AND o.status_auditoria = 'APROVADO'");
    $stmt->execute($params);
    $KPI['ordens_aprovadas'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM ordens_producao o {$where} AND o.status_auditoria = 'ALERTA_FRAUDE'");
    $stmt->execute($params);
    $KPI['ordens_alerta'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM receitas_agroindustria r WHERE r.excluido = 0" . ($isGestor ? '' : ' AND r.associado_id = ?'));
    $stmt->execute($isGestor ? [] : [(int)$assoc_id]);
    $KPI['receitas'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM controle_higienizacao h WHERE h.excluido = 0" . ($isGestor ? '' : ' AND h.associado_id = ?'));
    $stmt->execute($isGestor ? [] : [(int)$assoc_id]);
    $KPI['higienizacoes'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM controle_higienizacao h WHERE h.excluido = 0 AND h.data_hora_fim IS NOT NULL AND h.data_hora_inicio >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" . ($isGestor ? '' : ' AND h.associado_id = ?'));
    $stmt->execute($isGestor ? [] : [(int)$assoc_id]);
    $KPI['higienizacao_24h'] = (int)$stmt->fetchColumn();

    // Ordens recentes
    $stmt = $db->prepare("SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0" . ($isGestor ? '' : ' AND o.associado_id = ?') . " ORDER BY o.data_processamento DESC LIMIT 5");
    $stmt->execute($isGestor ? [] : [(int)$assoc_id]);
    $KPI['ordens_recentes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro no painel de agroindústria: " . $e->getMessage());
    $KPI['ordens_recentes'] = [];
}

$statusBadge = [
    'APROVADO'     => 'bg-success',
    'ALERTA_FRAUDE'=> 'bg-danger',
    'EM_ANALISE'   => 'bg-warning text-dark',
];
?>
<h1 class="mt-4"><i class="bi bi-buildings text-success me-2"></i>Painel de Agroindústria</h1>
<p class="text-muted">Acompanhe o processamento, a rastreabilidade e a conformidade da sua produção.</p>

<div class="row mb-4">
    <?php
    $cards = [
        ['Total de Ordens', number_format($KPI['total_ordens']), 'bi-clipboard-check', 'bg-primary', '/agroindustria/ordens'],
        ['Ordens Aprovadas', number_format($KPI['ordens_aprovadas']), 'bi-check-circle', 'bg-success', '/agroindustria/ordens'],
        ['Alertas de Balanço', number_format($KPI['ordens_alerta']), 'bi-exclamation-triangle', 'bg-danger', '/agroindustria/ordens'],
        ['Receitas', number_format($KPI['receitas']), 'bi-journal-richtext', 'bg-info', '/agroindustria/receitas'],
        ['Higienizações', number_format($KPI['higienizacoes']), 'bi-droplet', 'bg-secondary', '/agroindustria/higienizacao'],
        ['Higienização (24h)', number_format($KPI['higienizacao_24h']), 'bi-shield-check', 'bg-warning text-dark', '/agroindustria/higienizacao'],
    ];
    ?>
    <?php foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-lg-2 mb-3">
        <a href="<?php echo SITE_URL . $c[4]; ?>" class="text-decoration-none">
            <div class="card <?php echo $c[3]; ?> text-white h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi <?php echo $c[2]; ?> display-4"></i>
                    <div class="fs-3 fw-bold"><?php echo $c[1]; ?></div>
                    <div class="small"><?php echo $c[0]; ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Ordens recentes -->
<div class="card mt-2">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-check text-success me-1"></i> Ordens de Produção Recentes</span>
        <a href="<?= SITE_URL; ?>/agroindustria/ordens/nova" class="btn btn-success btn-sm">Nova Ordem</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Lote Processado</th><th>Produto Final</th><th>Processamento</th><th>Qtde</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (!$KPI['ordens_recentes']): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma ordem de produção registrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($KPI['ordens_recentes'] as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['lote_processado_final']); ?></strong></td>
                    <td><?= htmlspecialchars($o['nome_produto_final']); ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($o['data_processamento'])); ?></td>
                    <td><?= number_format((float)$o['quantidade_final_obtida'], 2, ',', '.') . ' ' . htmlspecialchars($o['unidade_medida']); ?></td>
                    <td><span class="badge <?= $statusBadge[$o['status_auditoria']] ?? 'bg-secondary'; ?>"><?= $o['status_auditoria']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <?php include __DIR__ . '/../../includes/widget_eventos.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>