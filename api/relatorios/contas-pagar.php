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

    $where = ["tipo = 'Despesa'"];
    $params = [];

    if (!empty($_GET['dataInicio'])) {
        $where[] = "data >= :dataInicio";
        $params[':dataInicio'] = $_GET['dataInicio'];
    }
    
    if (!empty($_GET['dataFim'])) {
        $where[] = "data <= :dataFim";
        $params[':dataFim'] = $_GET['dataFim'];
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $query = "SELECT id, nome, valor, data FROM contas $whereClause ORDER BY data ASC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach($contas as $c) {
        $total += (float)$c['valor'];
    }
?>

<div class="alert alert-danger shadow-sm border-0 d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-bag-dash fs-3 me-3"></i>
        <div>
            <h5 class="mb-0">Total de Despesas do Período</h5>
            <small class="opacity-75">Somatória das obrigações a pagar.</small>
        </div>
    </div>
    <h3 class="mb-0 fw-bold">R$ <?= number_format($total, 2, ',', '.') ?></h3>
</div>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Data de Vencimento / Pagamento</th>
            <th>Descrição da Despesa</th>
            <th>Valor a Pagar (R$)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($contas as $row): 
            $data = date('d/m/Y', strtotime($row['data']));
        ?>
        <tr>
            <td><?= $data ?></td>
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td class="text-end fw-bold text-danger">R$ <?= number_format((float)$row['valor'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
