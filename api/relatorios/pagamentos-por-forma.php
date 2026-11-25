<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Pagamento.php';

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
    $dataInicio = !empty($_GET['dataInicio']) ? $_GET['dataInicio'] : null;
    $dataFim = !empty($_GET['dataFim']) ? $_GET['dataFim'] : null;

    $formas = Pagamento::getPagamentosPorForma($associacaoId, $dataInicio, $dataFim, $associadoId);
    $totalQtd = array_sum(array_column($formas, 'qtd'));
    $totalValor = array_sum(array_map(fn($r) => (float) $r['valor'], $formas));

    $nomesForma = [
        'PIX' => 'PIX',
        'Boleto' => 'Boleto',
        'Cartao' => 'Cartão',
        'Cartão' => 'Cartão',
        'Dinheiro' => 'Dinheiro',
        'Transferencia' => 'Transferência',
    ];
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Pagamentos no Período</h6>
                <h3 class="fw-bold mb-0"><?php echo $totalQtd; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Total Recebido</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Ticket Médio</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalQtd > 0 ? $totalValor / $totalQtd : 0, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (empty($formas)): ?>
    <div class="alert alert-secondary">Nenhum pagamento encontrado para os filtros informados.</div>
<?php else: ?>
<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Forma de Pagamento</th>
            <th>Quantidade</th>
            <th>Valor (R$)</th>
            <th>% do Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($formas as $r):
            $nome = $nomesForma[$r['metodo_pagamento']] ?? $r['metodo_pagamento'];
            $pct = $totalValor > 0 ? round(((float) $r['valor'] / $totalValor) * 100, 1) : 0;
        ?>
        <tr>
            <td><span class="badge rounded-pill text-white" style="background:linear-gradient(135deg,#2f9e6f,#2b9d8f);"><?php echo htmlspecialchars($nome); ?></span></td>
            <td class="text-center"><?php echo (int) $r['qtd']; ?></td>
            <td class="text-end fw-bold">R$ <?php echo number_format((float) $r['valor'], 2, ',', '.'); ?></td>
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
            <th class="text-center"><?php echo $totalQtd; ?></th>
            <th class="text-end">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></th>
            <th>100%</th>
        </tr>
    </tfoot>
</table>
<?php endif; ?>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>