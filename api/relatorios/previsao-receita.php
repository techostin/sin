<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguranca: exige nivel 2 (fonte de verdade: relatorios.php -> reportCategories['associacao']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(2, false); // false = resposta em texto, pois o endpoint devolve HTML

try {
    $associadoId = !empty($_GET['filtro_associado_id']) ? (int) $_GET['filtro_associado_id'] : null;

    $previsao = (new Mensalidade())->getPrevisaoReceita(null, 3);
    $meses = $previsao['meses'];
    $total = (float) $previsao['total'];
    $media = count($meses) > 0 ? $total / count($meses) : 0;
?>

<div class="row mb-4">
    <?php foreach ($meses as $m): ?>
        <div class="col-md-4">
            <div class="card border-success shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small text-uppercase"><?php echo htmlspecialchars($m['mes_nome']); ?></div>
                    <div class="fs-4 fw-bold text-success">R$ <?php echo number_format((float) $m['valor'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Previsão Total (3 meses)</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($total, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-info text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Média Mensal Prevista</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($media, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Mês</th>
            <th>Valor Previsto (R$)</th>
            <th>% da Previsão Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($meses as $m):
            $pct = $total > 0 ? round(((float) $m['valor'] / $total) * 100, 1) : 0;
        ?>
        <tr>
            <td class="fw-bold"><?php echo htmlspecialchars($m['mes_nome']); ?></td>
            <td class="text-end">R$ <?php echo number_format((float) $m['valor'], 2, ',', '.'); ?></td>
            <td>
                <div class="progress" style="height:18px;">
                    <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th class="text-end">Total</th>
            <th class="text-end">R$ <?php echo number_format($total, 2, ',', '.'); ?></th>
            <th>100%</th>
        </tr>
    </tfoot>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>