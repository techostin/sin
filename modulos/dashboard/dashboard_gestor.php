<?php
$page_title = "Painel do Gestor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Restringir a Gestor/Admin (NIVEL_GESTOR e menores)
if (!((int)($_SESSION['user_nivel'] ?? 0) <= NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Painel exclusivo de Gestores e Técnicos.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$KPI = ['associados' => 0, 'certificacoes_ativas' => 0, 'planos_analise' => 0,
        'visitas_agendadas' => 0, 'nc_abertas' => 0, 'insumos_baixo' => 0, 'propriedades' => 0,
        'atividades_semana' => 0];

try {
    $q = function (string $sql, array $p = []) use ($db) {
        $s = $db->prepare($sql);
        $s->execute($p);
        return (int)$s->fetchColumn();
    };

    $KPI['associados']= $q("SELECT COUNT(*) FROM associados WHERE status = 'ativo'");
    $KPI['certificacoes_ativas']= $q("SELECT COUNT(*) FROM certificacao_participativa WHERE status = 'Ativa' AND excluido = 0");
    $KPI['planos_analise']      = $q("SELECT COUNT(*) FROM plano_manejo WHERE status = 'Em Análise'");
    $KPI['visitas_agendadas']   = $q("SELECT COUNT(*) FROM visitas_tecnicas WHERE status = 'Agendada'");
    $KPI['nc_abertas']          = $q("SELECT COUNT(*) FROM nao_conformidades WHERE status IN ('Aberta','Em Correção')");
    $KPI['insumos_baixo']        = $q("SELECT COUNT(*) FROM insumos WHERE quantidade_estoque < 10 AND excluido = 0");
    $KPI['propriedades']        = $q("SELECT COUNT(*) FROM propriedades");
    $KPI['atividades_semana']   = $q("SELECT COUNT(*) FROM atividades_diarias WHERE excluido = 0 AND YEARWEEK(data_atividade,1) = YEARWEEK(CURDATE(),1)");
    $KPI['animais']             = $q("SELECT COUNT(*) FROM animais WHERE excluido = 0");
    $KPI['lotes_animais']       = $q("SELECT COUNT(*) FROM animal_lotes WHERE excluido = 0");

    try {
        $KPI['cnpo']            = $q("SELECT COUNT(*) FROM cnpo");
    } catch (Throwable $e) {
        $KPI['cnpo'] = 0;
    }

    // Visitas agendadas mais próximas
    $s = $db->query("SELECT vt.id, vt.data_visita, vt.status, vt.ciente, vt.ciente_em,
                            vt.ciente_recusada, vt.ciente_recusada_motivo, vt.ciente_recusada_em,
                            a.nome, a.sobrenome
                     FROM visitas_tecnicas vt
                     JOIN associados a ON vt.associado_id = a.id
                     WHERE vt.status = 'Agendada' ORDER BY vt.data_visita ASC, vt.ciente_recusada ASC, vt.id DESC LIMIT 5");
    $KPI['proximas_visitas'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro no painel do gestor: " . $e->getMessage());
    $KPI['proximas_visitas'] = [];
}
?>
<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<div class="gestor-hero">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
        <div>
            <h1 class="mb-1"><i class="bi bi-clipboard-check me-2" style="color:var(--gm-primary);"></i>Painel do Gestor / Técnico</h1>
            <p class="mb-0">Fiscalização, inspeção e acompanhamento técnico da produção orgânica.</p>
        </div>
        <a href="<?= SITE_URL; ?>/visita-tecnica/agendar" class="btn btn-assoc-primary btn-sm">
            <i class="bi bi-calendar-plus me-1"></i> Agendar Visita
        </a>
    </div>
    <div class="gestor-stats">
        <span class="gestor-stat">
            <span class="icone" style="background:linear-gradient(135deg,#4f5bd5,#5a6acf);"><i class="bi bi-people-fill"></i></span>
            <span><span class="num"><?= $KPI['associados']; ?></span> associados ativos</span>
        </span>
        <span class="gestor-stat">
            <span class="icone" style="background:linear-gradient(135deg,#2f9db3,#41b3c9);"><i class="bi bi-calendar-check-fill"></i></span>
            <span><span class="num"><?= $KPI['visitas_agendadas']; ?></span> visitas agendadas</span>
        </span>
        <span class="gestor-stat">
            <span class="icone" style="background:linear-gradient(135deg,#ef7a67,#e4634f);"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <span><span class="num"><?= $KPI['nc_abertas']; ?></span> não conformidades</span>
        </span>
    </div>
</div>

<div class="row mb-4 g-3">
    <?php
    $cards = [
        ['Associados Ativos', $KPI['associados'], 'bi-people-fill', 'linear-gradient(135deg,#4f5bd5,#6a5fd0)', '/associados'],
        ['Certificações Ativas', $KPI['certificacoes_ativas'], 'bi-award', 'linear-gradient(135deg,#2f9db3,#4fc4d0)', '/certificacoes'],
        ['Planos em Análise', $KPI['planos_analise'], 'bi-journal-text', 'linear-gradient(135deg,#5a6acf,#7a6fd0)', '/planos-manejo'],
        ['Visitas Agendadas', $KPI['visitas_agendadas'], 'bi-calendar-check', 'linear-gradient(135deg,#3f86c4,#5aa6e0)', '/visitas-tecnicas'],
        ['NCs Abertas', $KPI['nc_abertas'], 'bi-exclamation-triangle', 'linear-gradient(135deg,#ef7a67,#e4634f)', '/nao-conformidades'],
        ['Insumos em Falta', $KPI['insumos_baixo'], 'bi-exclamation-diamond', 'linear-gradient(135deg,#f0b95c,#eda24a)', '/insumos'],
        ['Propriedades', $KPI['propriedades'], 'bi-house-door', 'linear-gradient(135deg,#7c8aa5,#93a2b8)', '/propriedades'],
        ['Atividades (semana)', $KPI['atividades_semana'], 'bi-calendar-check-fill', 'linear-gradient(135deg,#41b3c9,#61c4d6)', '/atividades-diarias'],
        ['Rebanho (animais)', $KPI['animais'], 'bi-egg-fill', 'linear-gradient(135deg,#4c9e6a,#6ec49a)', '/producao-animal'],
        ['Lotes de Animais', $KPI['lotes_animais'], 'bi-boxes', 'linear-gradient(135deg,#8a6ec4,#a68ade)', '/producao-animal/lotes'],
        ['Produtores no CNPO', $KPI['cnpo'] ?? 0, 'bi-patch-check', 'linear-gradient(135deg,#2e8b57,#43a06a)', '/consulta-cnpo'],
    ];
    ?>
    <?php foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?php echo SITE_URL . $c[4]; ?>" class="text-decoration-none kpi-link">
            <div class="gm-kpi-card gm-kpi card h-100" style="background:<?php echo $c[3]; ?>;">
                <div class="card-body position-relative overflow-hidden">
                    <i class="bi <?php echo $c[2]; ?> kpi-ico"></i>
                    <div class="fs-4 fw-bold"><?php echo $c[1]; ?></div>
                    <div class="small opacity-75"><?php echo $c[0]; ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Próximas visitas -->
<div class="gm-card mt-2">
    <div class="card-head d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-calendar-event me-1" style="color:var(--gm-teal);"></i> Próximas Visitas &amp; Inspeções</span>
        <a href="<?= SITE_URL; ?>/visita-tecnica/agendar" class="btn btn-info btn-sm">Agendar Visita</a>
    </div>
    <div class="p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Agricultor</th><th>Data</th><th>Status</th><th>Confirmação</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$KPI['proximas_visitas']): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma visita agendada.</td></tr>
                <?php endif; ?>
                <?php foreach ($KPI['proximas_visitas'] as $v): ?>
                <tr>
                    <td><?= htmlspecialchars(trim(($v['nome'] ?? '') . ' ' . ($v['sobrenome'] ?? ''))); ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['data_visita'])); ?></td>
                    <td><span class="badge bg-info text-white"><?= $v['status']; ?></span></td>
                    <td>
                        <?php if (!empty($v['ciente'])): ?>
                        <span class="badge bg-success" title="Confirmado em <?= htmlspecialchars($v['ciente_em'] ?? ''); ?>"><i class="bi bi-check-circle me-1"></i>Ciente</span>
                        <?php elseif (!empty($v['ciente_recusada'])): ?>
                        <span class="badge bg-danger" title="Recusou em <?= htmlspecialchars($v['ciente_recusada_em'] ?? ''); ?> — Motivo: <?= htmlspecialchars($v['ciente_recusada_motivo'] ?? 'não informado'); ?>"><i class="bi bi-calendar-x me-1"></i>Recusou</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark" title="Agricultor ainda não confirmou ciência"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= SITE_URL; ?>/visitas-tecnicas" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="row mt-4 g-3">
    <div class="col-md-6">
        <div class="gm-card h-100">
            <div class="card-head"><i class="bi bi-tools me-1" style="color:var(--gm-primary);"></i> Ações de Fiscalização</div>
            <div class="list-group list-group-flush border-0">
                <a href="<?= SITE_URL; ?>/roteiros-inspecao" class="list-group-item list-group-item-action"><i class="bi bi-card-checklist me-2" style="color:var(--gm-primary);"></i>Roteiros de Inspeção</a>
                <a href="<?= SITE_URL; ?>/visita-tecnica/agendar" class="list-group-item list-group-item-action"><i class="bi bi-calendar-plus me-2" style="color:var(--gm-teal);"></i>Agendar Visita</a>
                <a href="<?= SITE_URL; ?>/certificadoras_list.php" class="list-group-item list-group-item-action"><i class="bi bi-building me-2" style="color:var(--gm-muted);"></i>Certificadoras</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="gm-card h-100">
            <div class="card-head"><i class="bi bi-calendar-event-fill me-1" style="color:var(--gm-primary);"></i> Eventos</div>
            <div class="card-body">
                <?php include __DIR__ . '/../../includes/widget_eventos.php'; ?>
            </div>
        </div>
    </div>
</div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>