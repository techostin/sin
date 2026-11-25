<?php
/**
 * Relatório: Entradas x Saídas no Mês (Fluxo de Caixa)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor do sistema OU membro da Diretoria (Presidente, Vice-Presidente,
// Diretor, Secretário, Tesoureiro e comissões/conselhos) da associação.
if (!$user->hasPermission(NIVEL_GESTOR) && !$user->isMembroDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();

// Filtros
$filtro_mes = $_GET['mes'] ?? (int) date('m');
$filtro_ano = $_GET['ano'] ?? (int) date('Y');
$filtro_associacao = $_GET['associacao_id'] ?? '';

// Buscar associações
$associacoes = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Período
$dataInicio = sprintf('%04d-%02d-01', $filtro_ano, $filtro_mes);
$dataFim = date('Y-m-t', strtotime($dataInicio));

// WHERE base
$whereAssoc = '';
$params = [':ini' => $dataInicio, ':fim' => $dataFim];
if ($filtro_associacao) {
    $whereAssoc = ' AND associacao_id = :assoc';
    $params[':assoc'] = (int) $filtro_associacao;
}

// ENTRADAS
$entradasSql = "
    SELECT 
        'Mensalidade' as categoria,
        COALESCE(SUM(valor), 0) as total,
        COUNT(*) as qtd
    FROM pagamentos
    WHERE status_pagamento = 'pago'
      AND data_pagamento BETWEEN :ini AND :fim
      AND metodo_pagamento IN ('PIX', 'Boleto', 'Cartao', 'Transferencia', 'Dinheiro')
    $whereAssoc
    UNION ALL
    SELECT 
        'Outras Entradas' as categoria,
        COALESCE(SUM(valor), 0) as total,
        COUNT(*) as qtd
    FROM caixa
    WHERE tipo = 'Entrada'
      AND data BETWEEN :ini AND :fim
      AND categoria != 'Mensalidade'
    $whereAssoc
";

$stmt = $db->prepare($entradasSql);
$stmt->execute($params);
$entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SAÍDAS
$saidasSql = "
    SELECT 
        categoria,
        COALESCE(SUM(valor), 0) as total,
        COUNT(*) as qtd
    FROM caixa
    WHERE tipo = 'Saida'
      AND data BETWEEN :ini AND :fim
    $whereAssoc
    GROUP BY categoria
    ORDER BY total DESC
";

$stmt = $db->prepare($saidasSql);
$stmt->execute($params);
$saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais
$totalEntradas = array_sum(array_column($entradas, 'total'));
$totalSaidas = array_sum(array_column($saidas, 'total'));
$saldo = $totalEntradas - $totalSaidas;

// Detalhamento mensalidades por forma de pagamento
$formasSql = "
    SELECT 
        metodo_pagamento as forma,
        COALESCE(SUM(valor), 0) as total,
        COUNT(*) as qtd
    FROM pagamentos
    WHERE status_pagamento = 'pago'
      AND data_pagamento BETWEEN :ini AND :fim
    $whereAssoc
    GROUP BY metodo_pagamento
    ORDER BY total DESC
";
$stmt = $db->prepare($formasSql);
$stmt->execute($params);
$pagamentosForma = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Detalhamento caixa por categoria
$caixaSql = "
    SELECT 
        tipo,
        categoria,
        COALESCE(SUM(valor), 0) as total,
        COUNT(*) as qtd
    FROM caixa
    WHERE data BETWEEN :ini AND :fim
    $whereAssoc
    GROUP BY tipo, categoria
    ORDER BY tipo DESC, total DESC
";
$stmt = $db->prepare($caixaSql);
$stmt->execute($params);
$caixaCategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fluxo_caixa_' . $filtro_ano . '_' . str_pad($filtro_mes, 2, '0', STR_PAD_LEFT) . '.csv"');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Relatório', 'Fluxo de Caixa', $dataInicio, 'a', $dataFim]);
    fputcsv($output, []);
    fputcsv($output, ['Tipo', 'Categoria', 'Quantidade', 'Valor (R$)']);
    
    foreach ($entradas as $e) {
        fputcsv($output, ['Entrada', $e['categoria'], $e['qtd'], number_format($e['total'], 2, ',', '.')]);
    }
    foreach ($saidas as $s) {
        fputcsv($output, ['Saída', $s['categoria'], $s['qtd'], number_format($s['total'], 2, ',', '.')]);
    }
    fputcsv($output, ['SALDO', '', '', number_format($saldo, 2, ',', '.')]);
    fputcsv($output, []);
    fputcsv($output, ['Pagamentos por Forma', '', '', '']);
    fputcsv($output, ['Forma', 'Quantidade', 'Valor (R$)']);
    foreach ($pagamentosForma as $p) {
        fputcsv($output, [$p['forma'], $p['qtd'], number_format($p['total'], 2, ',', '.')]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Caixa Detalhado', '', '', '']);
    fputcsv($output, ['Tipo', 'Categoria', 'Quantidade', 'Valor (R$)']);
    foreach ($caixaCategorias as $c) {
        fputcsv($output, [$c['tipo'], $c['categoria'], $c['qtd'], number_format($c['total'], 2, ',', '.')]);
    }
    
    fclose($output);
    exit;
}

$mesesNomes = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

$page_title = "Entradas x Saídas - Fluxo de Caixa";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cash-stack text-success me-2"></i>Fluxo de Caixa</h1>
            <span class="text-muted">Entradas x Saídas - <?php echo $mesesNomes[$filtro_mes]; ?>/<?php echo $filtro_ano; ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv&mes=<?php echo $filtro_mes; ?>&ano=<?php echo $filtro_ano; ?><?php echo $filtro_associacao ? '&associacao_id=' . $filtro_associacao : ''; ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Exportar CSV</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Associação</label>
                    <select name="associacao_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $filtro_associacao == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome'] . ' (' . $a['sigla'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mês</label>
                    <select name="mes" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $filtro_mes == $m ? 'selected' : ''; ?>><?php echo $mesesNomes[$m]; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ano</label>
                    <select name="ano" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filtro_ano == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/entradas_saidas" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Entradas</h6>
                    <h2 class="mb-0 text-success">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></h2>
                    <small class="text-muted"><?php echo array_sum(array_column($entradas, 'qtd')); ?> transações</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Total Saídas</h6>
                    <h2 class="mb-0 text-danger">R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?></h2>
                    <small class="text-muted"><?php echo array_sum(array_column($saidas, 'qtd')); ?> transações</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm <?php echo $saldo >= 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-1">Saldo do Período</h6>
                    <h2 class="mb-0 <?php echo $saldo >= 0 ? 'text-success' : 'text-danger'; ?>">
                        R$ <?php echo number_format($saldo, 2, ',', '.'); ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico simples (CSS) -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Composição Visual</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label small text-success fw-bold">Entradas (R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?>)</label>
                            <div class="progress" style="height: 30px;">
                                <?php foreach ($entradas as $e): ?>
                                <?php $pct = $totalEntradas > 0 ? ($e['total'] / $totalEntradas) * 100 : 0; ?>
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php if ($pct > 5): ?>
                                    <?php echo htmlspecialchars($e['categoria']); ?>: <?php echo number_format($e['total'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-danger fw-bold">Saídas (R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?>)</label>
                            <div class="progress" style="height: 30px;">
                                <?php foreach ($saidas as $s): ?>
                                <?php $pct = $totalSaidas > 0 ? ($s['total'] / $totalSaidas) * 100 : 0; ?>
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php if ($pct > 5): ?>
                                    <?php echo htmlspecialchars($s['categoria']); ?>: <?php echo number_format($s['total'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela Entradas -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-down-circle me-1"></i>Entradas Detalhadas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Categoria</th><th class="text-center">Qtd</th><th class="text-end">Valor (R$)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entradas as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['categoria']); ?></td>
                                    <td class="text-center"><?php echo $e['qtd']; ?></td>
                                    <td class="text-end text-success fw-bold">R$ <?php echo number_format($e['total'], 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL ENTRADAS</td>
                                    <td class="text-center"><?php echo array_sum(array_column($entradas, 'qtd')); ?></td>
                                    <td class="text-end text-success">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela Saídas -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-1"></i>Saídas Detalhadas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Categoria</th><th class="text-center">Qtd</th><th class="text-end">Valor (R$)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saidas as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['categoria']); ?></td>
                                    <td class="text-center"><?php echo $s['qtd']; ?></td>
                                    <td class="text-end text-danger fw-bold">R$ <?php echo number_format($s['total'], 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($saidas)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">Nenhuma saída no período</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL SAÍDAS</td>
                                    <td class="text-center"><?php echo array_sum(array_column($saidas, 'qtd')); ?></td>
                                    <td class="text-end text-danger">R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagamentos por Forma -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Pagamentos por Forma</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaFormas">
                    <thead class="table-light">
                        <tr><th>Forma de Pagamento</th><th class="text-center">Qtd</th><th class="text-end">Valor (R$)</th><th class="text-center">%</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentosForma as $p): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($p['forma']); ?></span></td>
                            <td class="text-center"><?php echo $p['qtd']; ?></td>
                            <td class="text-end">R$ <?php echo number_format($p['total'], 2, ',', '.'); ?></td>
                            <td class="text-center">
                                <?php echo $totalEntradas > 0 ? round(($p['total'] / $totalEntradas) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Caixa Detalhado -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lançamentos de Caixa por Categoria</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaCaixa">
                    <thead class="table-light">
                        <tr><th>Tipo</th><th>Categoria</th><th class="text-center">Qtd</th><th class="text-end">Valor (R$)</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $tipoAtual = '';
                        foreach ($caixaCategorias as $c):
                            if ($c['tipo'] !== $tipoAtual) {
                                $tipoAtual = $c['tipo'];
                                $cor = $tipoAtual === 'Entrada' ? 'success' : 'danger';
                                echo '<tr class="table-' . ($cor === 'success' ? 'secondary' : 'secondary') . '"><td colspan="4"><strong><span class="badge bg-' . $cor . '">' . $tipoAtual . '</span></strong></td></tr>';
                            }
                        ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($c['categoria']); ?></td>
                            <td class="text-center"><?php echo $c['qtd']; ?></td>
                            <td class="text-end <?php echo $c['tipo'] === 'Entrada' ? 'text-success' : 'text-danger'; ?> fw-bold">
                                R$ <?php echo number_format($c['total'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaFormas').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 10,
        "order": [[2, 'desc']]
    });
    $('#tabelaCaixa').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "order": [[0, 'desc']]
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>