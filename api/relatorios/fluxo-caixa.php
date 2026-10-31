<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguranca: exige nivel 2 (fonte de verdade: relatorios.php -> reportCategories['financeiro']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(2, false); // false = resposta em texto, pois o endpoint devolve HTML

try {
    $db = Database::getInstance()->getConnection();

    $where = [];
    $params = [];

    if (!empty($_GET['dataInicio'])) {
        $where[] = "data >= :dataInicio";
        $params[':dataInicio'] = $_GET['dataInicio'];
    }
    
    if (!empty($_GET['dataFim'])) {
        $where[] = "data <= :dataFim";
        $params[':dataFim'] = $_GET['dataFim'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT id, nome, tipo, valor, data FROM contas $whereClause ORDER BY data DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_receitas = 0;
    $total_despesas = 0;

    foreach ($contas as $c) {
        if ($c['tipo'] === 'Receita') $total_receitas += (float)$c['valor'];
        if ($c['tipo'] === 'Despesa') $total_despesas += (float)$c['valor'];
    }
    
    $saldo = $total_receitas - $total_despesas;
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Entradas (Receitas)</h6>
                <h3 class="fw-bold mb-0">R$ <?= number_format($total_receitas, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Saídas (Despesas)</h6>
                <h3 class="fw-bold mb-0">R$ <?= number_format($total_despesas, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card <?= $saldo >= 0 ? 'bg-primary' : 'bg-warning text-dark' ?> text-white border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-uppercase mb-1 opacity-75">Saldo Líquido</h6>
                <h3 class="fw-bold mb-0">R$ <?= number_format($saldo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Data</th>
            <th>Descrição</th>
            <th>Tipo</th>
            <th>Valor (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($contas as $row): 
            $data = date('d/m/Y', strtotime($row['data']));
            $isReceita = $row['tipo'] === 'Receita';
        ?>
        <tr>
            <td><?= $data ?></td>
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td>
                <?php if($isReceita): ?>
                    <span class="badge bg-success"><i class="bi bi-arrow-up-right"></i> Receita</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="bi bi-arrow-down-right"></i> Despesa</span>
                <?php endif; ?>
            </td>
            <td class="text-end fw-bold <?= $isReceita ? 'text-success' : 'text-danger' ?>">
                <?= $isReceita ? '' : '-' ?>R$ <?= number_format((float)$row['valor'], 2, ',', '.') ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
