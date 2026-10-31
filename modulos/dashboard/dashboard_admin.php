<?php
$page_title = "Painel Administrativo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Restringir a Admin (NIVEL_ADMIN)
if ((int)($_SESSION['user_nivel'] ?? 0) !== NIVEL_ADMIN) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Painel exclusivo do Administrador.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$KPI = ['associados' => 0, 'associados_pendentes' => 0, 'usuarios' => 0, 'associacoes' => 0,
        'certificacoes_ativas' => 0, 'planos_analise' => 0, 'visitas_agendadas' => 0, 'nc_abertas' => 0];

try {
    $q = function (string $sql, array $p = []) use ($db) {
        $s = $db->prepare($sql);
        $s->execute($p);
        return (int)$s->fetchColumn();
    };

    $KPI['associados']          = $q("SELECT COUNT(*) FROM associados WHERE status = 'ativo'");
    $KPI['associados_pendentes']= $q("SELECT (SELECT COUNT(*) FROM associados WHERE status = 'pendente') + (SELECT COUNT(*) FROM usuarios WHERE status = 'pendente')");
    $KPI['usuarios']            = $q("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'");
    $KPI['associacoes']         = $q("SELECT COUNT(*) FROM associacoes");
    $KPI['certificacoes_ativas']= $q("SELECT COUNT(*) FROM certificacao_participativa WHERE status = 'Ativa' AND excluido = 0");
    $KPI['planos_analise']      = $q("SELECT COUNT(*) FROM plano_manejo WHERE status = 'Em Análise'");
    $KPI['visitas_agendadas']   = $q("SELECT COUNT(*) FROM visitas_tecnicas WHERE status = 'Agendada'");
    $KPI['nc_abertas']          = $q("SELECT COUNT(*) FROM nao_conformidades WHERE status IN ('Aberta','Em Correção')");
    $KPI['animais']             = $q("SELECT COUNT(*) FROM animais WHERE excluido = 0");
    try {
        $KPI['cnpo']            = $q("SELECT COUNT(*) FROM cnpo");
    } catch (Throwable $e) {
        $KPI['cnpo'] = 0;
    }
} catch (PDOException $e) {
    error_log("Erro no painel administrativo: " . $e->getMessage());
}
?>
<div class="container-fluid px-4 py-3">
    <h1 class="h3 mb-1"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Painel Administrativo</h1>
    <p class="text-muted mb-4">Visão consolidada do sistema SINPORG.</p>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Associados Ativos', $KPI['associados'], 'bi-people-fill', 'bg-primary', '/associados'],
            ['Pendentes de Ativação', $KPI['associados_pendentes'], 'bi-person-fill-exclamation', 'bg-warning text-dark', '/associados/pendentes'],
            ['Usuários', $KPI['usuarios'], 'bi-person-badge-fill', 'bg-info', '/usuarios'],
            ['Associações', $KPI['associacoes'], 'bi-building-fill', 'bg-secondary', '/associacoes'],
            ['Certificações Ativas', $KPI['certificacoes_ativas'], 'bi-award', 'bg-success', '/certificacoes'],
            ['Planos em Análise', $KPI['planos_analise'], 'bi-journal-text', 'bg-primary', '/planos-manejo'],
            ['Visitas Agendadas', $KPI['visitas_agendadas'], 'bi-calendar-check', 'bg-info', '/visitas-tecnicas'],
            ['NCs Abertas', $KPI['nc_abertas'], 'bi-exclamation-triangle', 'bg-danger', '/nao-conformidades'],
            ['Rebanho (animais)', $KPI['animais'], 'bi-egg-fill', 'bg-success', '/producao-animal'],
            ['Produtores no CNPO', $KPI['cnpo'] ?? 0, 'bi-patch-check', 'bg-success', '/consulta-cnpo'],
        ];
        ?>
        <?php foreach ($cards as $c): ?>
        <div class="col-6 col-md-4 col-lg-3 mb-3">
            <a href="<?php echo SITE_URL . $c[4]; ?>" class="text-decoration-none">
                <div class="card <?php echo $c[3]; ?> text-white h-100 shadow-sm">
                    <div class="card-body text-center py-3">
                        <i class="bi <?php echo $c[2]; ?> display-6"></i>
                        <div class="fs-5 fw-bold"><?php echo $c[1]; ?></div>
                        <div class="small"><?php echo $c[0]; ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white"><i class="bi bi-gear-fill text-primary me-1"></i> Gestão do Sistema</div>
                <div class="list-group list-group-flush">
                    <a href="<?= SITE_URL; ?>/relatorios-admin" class="list-group-item list-group-item-action"><i class="bi bi-file-earmark-bar-graph text-info me-2"></i>Relatórios Administrativos</a>
                    <a href="<?= SITE_URL; ?>/relatorios-conformidade" class="list-group-item list-group-item-action"><i class="bi bi-shield-check text-success me-2"></i>Relatórios de Conformidade</a>
                    <a href="<?= SITE_URL; ?>/certificadoras_list.php" class="list-group-item list-group-item-action"><i class="bi bi-building me-2"></i>Certificadoras</a>
                    <a href="<?= SITE_URL; ?>/contas" class="list-group-item list-group-item-action"><i class="bi bi-cash-stack text-success me-2"></i>Contas</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-calendar-event-fill text-primary me-1"></i> Eventos</div>
                <div class="card-body">
                    <?php include __DIR__ . '/../../includes/widget_eventos.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>