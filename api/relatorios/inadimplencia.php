<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Pagamento.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autorizacao (relatorio financeiro nivel 2 - Admin/Gestor).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirStaff(false);

try {
    $associacaoId = !empty($_GET['associacao']) ? (int) $_GET['associacao'] : null;
    $associadoId = !empty($_GET['filtro_associado_id']) ? (int) $_GET['filtro_associado_id'] : null;

    $porMes = Pagamento::getInadimplenciaPorMes($associacaoId, 12);

    $db = Database::getInstance()->getConnection();
    $sql = "SELECT a.tipo_associado AS categoria,
                   COUNT(*) AS qtd,
                   COALESCE(SUM(m.valor_cobrado), 0) AS valor
            FROM mensalidades m
            INNER JOIN associados a ON a.id = m.associado_id
            WHERE m.status_mensalidade IN ('pendente', 'atrasado')
              AND m.data_vencimento < :hoje";
    $params = [':hoje' => date('Y-m-d')];
    if (!empty($associacaoId)) {
        $sql .= " AND m.associacao_id = :assoc";
        $params[':assoc'] = $associacaoId;
    }
    if (!empty($associadoId)) {
        $sql .= " AND m.associado_id = :associd";
        $params[':associd'] = $associadoId;
    }
    $sql .= " GROUP BY a.tipo_associado ORDER BY valor DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $porCategoria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalQtd = array_sum(array_column($porMes, 'qtd'));
    $totalValor = array_sum(array_map(fn($r) => (float) $r['valor'], $porMes));

    $mesesPt = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-danger text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Mensalidades em Aberto</h6>
                <h3 class="fw-bold mb-0"><?php echo $totalQtd; ?> cobranças</h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-warning text-dark border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Valor Total Inadimplente</h6>
                <h3 class="fw-bold mb-0">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <h6 class="fw-bold mb-3">Inadimplência por Mês de Referência</h6>
        <table id="reportTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Qtd. em Aberto</th>
                    <th>Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($porMes as $r):
                    $nomeMes = $mesesPt[(int) $r['mes']] . '/' . $r['ano'];
                ?>
                <tr>
                    <td><?php echo $nomeMes; ?></td>
                    <td class="text-center"><span class="badge bg-danger"><?php echo (int) $r['qtd']; ?></span></td>
                    <td class="text-end fw-bold text-danger">R$ <?php echo number_format((float) $r['valor'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-end">Total</th>
                    <th class="text-center"><?php echo $totalQtd; ?></th>
                    <th class="text-end">R$ <?php echo number_format($totalValor, 2, ',', '.'); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="col-lg-5">
        <h6 class="fw-bold mb-3">Por Categoria de Associado</h6>
        <?php if (empty($porCategoria)): ?>
            <span class="text-muted small">Nenhuma cobrança inadimplente no momento.</span>
        <?php else: ?>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Qtd.</th>
                        <th>Valor (R$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porCategoria as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(ucfirst($r['categoria'] ?: 'Efetivo')); ?></td>
                        <td class="text-center"><?php echo (int) $r['qtd']; ?></td>
                        <td class="text-end">R$ <?php echo number_format((float) $r['valor'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>