<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Caixa.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Associacao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$f = [
    'associacao_id' => (int) ($_GET['associacao_id'] ?? 0),
];

$caixaModel = new Caixa();
$mensalidadeModel = new Mensalidade();

$associacoes = (new Associacao())->getAll();
$fluxo = $caixaModel->getFluxoPorMes($f['associacao_id'] ?: null, 6);
$inadimplenciaMes = $mensalidadeModel->getInadimplenciaPorMes($f['associacao_id'] ?: null, 12);
$pagamentosForma = $mensalidadeModel->getPagamentosPorForma($f['associacao_id'] ?: null);
$previsao = $mensalidadeModel->getPrevisaoReceita($f['associacao_id'] ?: null, 3);

$resumo = $caixaModel->getSaldo($f['associacao_id'] ?: null);
$totalEntradasFluxo = array_sum(array_column($fluxo, 'entradas'));
$totalSaidasFluxo = array_sum(array_column($fluxo, 'saidas'));

$page_title = "Relatório Financeiro";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Relatório Financeiro</h1>
            <span class="text-muted">Fluxo de caixa, inadimplência, pagamentos por forma e previsão de receita.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/caixa" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtros</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/caixa/relatorio" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Associação</label>
                    <select name="associacao_id" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $f['associacao_id'] === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-success shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Entradas (6 meses)</div>
                    <div class="fs-4 fw-bold text-success">+ R$ <?php echo number_format($totalEntradasFluxo, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Saídas (6 meses)</div>
                    <div class="fs-4 fw-bold text-danger">- R$ <?php echo number_format($totalSaidasFluxo, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Saldo Geral</div>
                    <div class="fs-4 fw-bold text-<?php echo $resumo['saldo'] >= 0 ? 'primary' : 'danger'; ?>">R$ <?php echo number_format($resumo['saldo'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Previsão (3 meses)</div>
                    <div class="fs-4 fw-bold text-warning">R$ <?php echo number_format($previsao['total'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white"><i class="bi bi-bar-chart-line me-1"></i>Fluxo de Caixa (realizado)</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Mês</th><th class="text-end">Entradas</th><th class="text-end">Saídas</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                        <?php foreach ($fluxo as $fx): ?>
                            <tr>
                                <td><?php echo str_pad((string)$fx['mes'], 2, '0', STR_PAD_LEFT) . '/' . $fx['ano']; ?></td>
                                <td class="text-end text-success">R$ <?php echo number_format($fx['entradas'], 2, ',', '.'); ?></td>
                                <td class="text-end text-danger">R$ <?php echo number_format($fx['saidas'], 2, ',', '.'); ?></td>
                                <td class="text-end fw-bold text-<?php echo $fx['saldo'] >= 0 ? 'primary' : 'danger'; ?>">R$ <?php echo number_format($fx['saldo'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white"><i class="bi bi-exclamation-triangle me-1"></i>Inadimplência por Mês</div>
                <div class="card-body table-responsive">
                    <?php if (empty($inadimplenciaMes)): ?>
                        <span class="text-muted small">Nenhuma inadimplência no período.</span>
                    <?php else: ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Referência</th><th class="text-center">Contas</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                        <?php foreach ($inadimplenciaMes as $i): ?>
                            <tr>
                                <td><?php echo str_pad((string)$i['mes'], 2, '0', STR_PAD_LEFT) . '/' . $i['ano']; ?></td>
                                <td class="text-center"><?php echo $i['qtd']; ?></td>
                                <td class="text-end fw-bold text-danger">R$ <?php echo number_format($i['valor'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white"><i class="bi bi-credit-card me-1"></i>Pagamentos por Forma</div>
                <div class="card-body table-responsive">
                    <?php if (empty($pagamentosForma)): ?>
                        <span class="text-muted small">Nenhum pagamento registrado.</span>
                    <?php else: ?>
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Forma</th><th class="text-center">Qtd</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                        <?php foreach ($pagamentosForma as $pg): ?>
                            <tr>
                                <td><?php echo $pg['metodo_pagamento'] === 'Transferencia' ? 'Transferência' : htmlspecialchars($pg['metodo_pagamento'] ?? '—'); ?></td>
                                <td class="text-center"><?php echo $pg['qtd']; ?></td>
                                <td class="text-end fw-bold text-success">R$ <?php echo number_format((float)$pg['valor'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-warning text-dark"><i class="bi bi-calendar-range me-1"></i>Previsão de Receita (3 meses)</div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Mês</th><th class="text-end">A Receber</th></tr></thead>
                        <tbody>
                        <?php foreach ($previsao['meses'] as $pv): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pv['mes_nome']); ?></td>
                                <td class="text-end fw-bold">R$ <?php echo number_format($pv['valor'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><th>Total</th><th class="text-end">R$ <?php echo number_format($previsao['total'], 2, ',', '.'); ?></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>