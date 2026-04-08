<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = Database::getInstance()->getConnection();

    $where = [];
    $params = [];

    if (!empty($_GET['dataInicio'])) {
        $where[] = "li.data_aplicacao >= :dataInicio";
        $params[':dataInicio'] = $_GET['dataInicio'];
    }
    
    if (!empty($_GET['dataFim'])) {
        $where[] = "li.data_aplicacao <= :dataFim";
        $params[':dataFim'] = $_GET['dataFim'];
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT 
                li.id, 
                li.data_aplicacao,
                i.nome as insumo_nome,
                i.fabricante,
                lp.identificacao_lote,
                li.quantidade_utilizada,
                li.unidade_medida
              FROM lote_insumo li
              INNER JOIN insumos i ON li.insumo_id = i.id
              INNER JOIN lotes_producao lp ON li.lote_id = lp.id
              $whereClause
              ORDER BY li.data_aplicacao DESC, li.id DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Nota Histórica:</strong> O relatório de movimentações no momento acompanha o <strong>histórico de saídas (usos aplicados nos lotes)</strong>. Futuras implementações registrarão também logs isolados de entradas no almoxarifado.
</div>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Data da Aplicação</th>
            <th>Tipo Movimento</th>
            <th>Insumo</th>
            <th>Fabricante</th>
            <th>Destino (Lote)</th>
            <th>Quantidade Utilizada</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($movimentacoes as $row): 
            $data = date('d/m/Y', strtotime($row['data_aplicacao']));
            $quantidade = number_format((float)$row['quantidade_utilizada'], 2, ',', '.');
        ?>
        <tr>
            <td><?= $data ?></td>
            <td><span class="badge bg-danger"><i class="bi bi-arrow-down-right"></i> Saída (Uso)</span></td>
            <td><?= htmlspecialchars($row['insumo_nome']) ?></td>
            <td><?= htmlspecialchars($row['fabricante'] ?? '-') ?></td>
            <td>Lote: <?= htmlspecialchars($row['identificacao_lote']) ?></td>
            <td class="text-end fw-bold text-danger">-<?= $quantidade ?> <?= htmlspecialchars($row['unidade_medida'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
