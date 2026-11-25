<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Caixa.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguranca: exige nivel 2 (fonte de verdade: relatorios.php -> reportCategories['associacao']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(2, false); // false = resposta em texto, pois o endpoint devolve HTML

try {
    $associacaoId = !empty($_GET['associacao']) ? (int) $_GET['associacao'] : null;
    $associadoId = !empty($_GET['filtro_associado_id']) ? (int) $_GET['filtro_associado_id'] : null;

    $caixaReal = (new Caixa())->getFluxoPorMes($associacaoId, 3);
    $previsao = (new Mensalidade())->getPrevisaoReceita($associacaoId, 3);

    $realPorMes = [];
    foreach ($caixaReal as $row) {
        $key = $row['ano'] . '-' . str_pad((string) $row['mes'], 2, '0', STR_PAD_LEFT);
        $realPorMes[$key] = $row;
    }
    $prevPorMes = [];
    foreach ($previsao['meses'] as $item) {
        $prevPorMes[$item['chave']] = $item['valor'];
    }

    $totalEntradas = array_sum(array_map(fn($r) => (float) $r['entradas'], $caixaReal));
    $totalSaidas = array_sum(array_map(fn($r) => (float) $r['saidas'], $caixaReal));
    $totalSaldo = $totalEntradas - $totalSaidas;
    $totalPrevisto = (float) $previsao['total'];
    $totalRealizado = $totalEntradas;
    $diferenca = $totalRealizado - $totalPrevisto;

    $mesesPt = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

    $linhas = [];
    foreach ($caixaReal as $row) {
        $key = $row['ano'] . '-' . str_pad((string) $row['mes'], 2, '0', STR_PAD_LEFT);
        $linhas[$key] = [
            'mes' => $mesesPt[(int) $row['mes']] . '/' . $row['ano'],
            'entradas' => (float) $row['entradas'],
            'saidas' => (float) $row['saidas'],
            'saldo' => (float) $row['saldo'],
            'previsto' => isset($prevPorMes[$key]) ? (float) $prevPorMes[$key] : null,
        ];
    }
    foreach ($previsao['meses'] as $item) {
        if (!isset($linhas[$item['chave']])) {
            $linhas[$item['chave']] = [
                'mes' => $item['mes_nome'],
                'entradas' => null,
                'saidas' => null,
                'saldo' => null,
                'previsto' => (float) $item['valor'],
            ];
        }
    }
?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Realizado (Entradas)</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Realizado (Saídas)</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card <?php echo $totalSaldo >= 0 ? 'bg-primary' : 'bg-warning text-dark'; ?> text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Saldo Realizado</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalSaldo, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Previsto (Próx. 3 meses)</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalPrevisto, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="alert <?php echo $diferenca >= 0 ? 'alert-success' : 'alert-warning'; ?> py-2 small mb-4">
    <strong>Realizado × Previsto:</strong> total realizado de <strong>R$ <?php echo number_format($totalRealizado, 2, ',', '.'); ?></strong> versus previsão de <strong>R$ <?php echo number_format($totalPrevisto, 2, ',', '.'); ?></strong>
    (<?php echo $diferenca >= 0 ? 'superávit' : 'déficit'; ?> de R$ <?php echo number_format(abs($diferenca), 2, ',', '.'); ?>).
</div>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Mês</th>
            <th>Entradas Realizadas</th>
            <th>Saídas Realizadas</th>
            <th>Saldo Realizado</th>
            <th>Receita Prevista</th>
            <th>Diferença</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($linhas as $linha): ?>
        <tr>
            <td class="fw-bold"><?php echo htmlspecialchars($linha['mes']); ?></td>
            <td class="text-end"><?php echo $linha['entradas'] !== null ? 'R$ ' . number_format($linha['entradas'], 2, ',', '.') : '—'; ?></td>
            <td class="text-end"><?php echo $linha['saidas'] !== null ? '<span class="text-danger">- R$ ' . number_format($linha['saidas'], 2, ',', '.') . '</span>' : '—'; ?></td>
            <td class="text-end fw-bold <?php echo ($linha['saldo'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo $linha['saldo'] !== null ? 'R$ ' . number_format($linha['saldo'], 2, ',', '.') : '—'; ?>
            </td>
            <td class="text-end"><?php echo $linha['previsto'] !== null ? 'R$ ' . number_format($linha['previsto'], 2, ',', '.') : '—'; ?></td>
            <td class="text-end">
                <?php if ($linha['entradas'] !== null && $linha['previsto'] !== null):
                    $diff = $linha['entradas'] - $linha['previsto']; ?>
                    <span class="badge bg-<?php echo $diff >= 0 ? 'success' : 'danger'; ?>">
                        R$ <?php echo number_format($diff, 2, ',', '.'); ?>
                    </span>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th class="text-end">Total</th>
            <th class="text-end">R$ <?php echo number_format($totalEntradas, 2, ',', '.'); ?></th>
            <th class="text-end">R$ <?php echo number_format($totalSaidas, 2, ',', '.'); ?></th>
            <th class="text-end">R$ <?php echo number_format($totalSaldo, 2, ',', '.'); ?></th>
            <th class="text-end">R$ <?php echo number_format($totalPrevisto, 2, ',', '.'); ?></th>
            <th class="text-end">R$ <?php echo number_format($diferenca, 2, ',', '.'); ?></th>
        </tr>
    </tfoot>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>