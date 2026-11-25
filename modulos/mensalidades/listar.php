<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}
$podeGerenciarMensalidades = $user->hasPermission(NIVEL_GESTOR);
// Botão "voltar" para o Dashboard da Diretoria: mesma permissão exigida pela
// propria pagina (/dashboard/diretoria) — Admin ou membro do quadro diretivo.
$podeAcessarDashboardDiretoria = $user->podeAcessarDiretoria();

$f = [
    'associacao_id' => (int) ($_GET['associacao_id'] ?? 0),
    'ano' => (int) ($_GET['ano'] ?? 0),
    'mes' => (int) ($_GET['mes'] ?? 0),
    'status' => trim((string) ($_GET['status'] ?? '')),
];

$mensalidadeModel = new Mensalidade();
$itens = $mensalidadeModel->getAll(array_filter($f));
$associacoes = (new Associacao())->getAll();

$page_title = "Mensalidades";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cash-stack text-success me-2"></i>Mensalidades</h1>
            <span class="text-muted">Gestão de cobranças: lançamento, geração de lotes e baixas.</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($podeAcessarDashboardDiretoria): ?>
            <a href="<?php echo SITE_URL; ?>/dashboard/diretoria" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard da Diretoria</a>
            <?php endif; ?>
            <?php if ($podeGerenciarMensalidades): ?>
            <a href="<?php echo SITE_URL; ?>/mensalidade/gerar" class="btn btn-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Gerar Lote do Mês</a>
            <a href="<?php echo SITE_URL; ?>/mensalidade/novo" class="btn btn-info btn-sm"><i class="bi bi-plus-circle me-1"></i>Lançamento Manual</a>
            <a href="<?php echo SITE_URL; ?>/planos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>Planos</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/caixa/cobranca" class="btn btn-outline-danger btn-sm"><i class="bi bi-exclamation-triangle me-1"></i>Cobrança</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtros</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/mensalidades" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Associação</label>
                    <select name="associacao_id" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $f['associacao_id'] === (int) $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Ano</label>
                    <select name="ano" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $f['ano'] === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Mês</label>
                    <select name="mes" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $f['mes'] === $m ? 'selected' : ''; ?>><?php echo str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Situação</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="pendente" <?php echo $f['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $f['status'] === 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="atrasado" <?php echo $f['status'] === 'atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i>Lançamentos</span>
            <span class="badge bg-light text-dark"><?php echo count($itens); ?></span>
        </div>
        <div class="card-body table-responsive">
            <table id="mensalidadesTable" class="table table-striped table-hover small">
                <thead>
                    <tr>
                        <th>Associado</th><th>Ref.</th><th>Associação</th><th>Plano</th>
                        <th>Vencimento</th><th>Valor</th><th>Situação</th><th>Pagamento</th><?php if ($podeGerenciarMensalidades): ?><th>Ações</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($itens)): ?>
                    <tr><td colspan="9" class="text-center text-muted">Nenhuma mensalidade encontrada.</td></tr>
                <?php else: foreach ($itens as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['associado_nome'] ?? '—'); ?></td>
                        <td><?php echo str_pad((string)$m['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $m['ano_referencia']; ?></td>
                        <td><?php echo $m['associacao_sigla'] ? htmlspecialchars($m['associacao_sigla']) : ($m['associacao_nome'] ? htmlspecialchars($m['associacao_nome']) : '—'); ?></td>
                        <td><?php echo $m['plano_nome'] ? htmlspecialchars($m['plano_nome']) : '—'; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($m['data_vencimento'])); ?></td>
                        <td>R$ <?php echo number_format((float)$m['valor_cobrado'], 2, ',', '.'); ?></td>
                        <td>
                            <?php $cor = $m['status_mensalidade'] === 'pago' ? 'success' : ($m['status_mensalidade'] === 'atrasado' ? 'danger' : 'warning text-dark'); ?>
                            <span class="badge bg-<?php echo $cor; ?>"><?php echo ucfirst(htmlspecialchars($m['status_mensalidade'])); ?></span>
                        </td>
                        <td><?php echo $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—'; ?></td>
                        <?php if ($podeGerenciarMensalidades): ?>
                        <td class="text-nowrap">
                            <?php if ($m['status_mensalidade'] === 'pago'): ?>
                                <a href="<?php echo SITE_URL; ?>/mensalidade/<?php echo $m['id']; ?>/estornar" class="btn btn-warning btn-xs btn-sm" title="Estornar pagamento"><i class="bi bi-arrow-counterclockwise"></i></a>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/mensalidade/<?php echo $m['id']; ?>/baixa" class="btn btn-success btn-xs btn-sm" title="Dar baixa"><i class="bi bi-check2-circle"></i></a>
                            <?php endif; ?>
                            <?php if ($m['fonte'] === 'manual'): ?>
                                <form action="<?php echo SITE_URL; ?>/mensalidade/<?php echo $m['id']; ?>/excluir" method="post" style="display:inline;" onsubmit="return confirm('Excluir este lançamento?')">
                                    <?php echo CSRFProtection::getTokenField(); ?>
                                    <button class="btn btn-outline-danger btn-sm" title="Excluir"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#mensalidadesTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "order": [[1, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [{ "targets": -1, "orderable": false }]
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>