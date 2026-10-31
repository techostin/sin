<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguranca: exige nivel 3 (fonte de verdade: relatorios.php -> reportCategories['atividades']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(3, false); // false = resposta em texto, pois o endpoint devolve HTML

try {
    $db = Database::getInstance()->getConnection();

    $where = ["a.excluido = 0", "a.quantidade_colhida IS NOT NULL", "a.quantidade_colhida > 0"];
    $params = [];

    // IDOR: usuario de nivel 3 (comum) so pode ver a produtividade do proprio associado.
    // Mesma regra de ReportManager::getAtividadesReport (nivel > NIVEL_GESTOR filtra por associado).
    if (ApiAuth::nivel() > NIVEL_GESTOR) {
        $where[] = "a.associado_id = :associado_id";
        $params[':associado_id'] = ApiAuth::associadoId();
    }

    if (!empty($_GET['dataInicio'])) {
        $where[] = "a.data_atividade >= :dataInicio";
        $params[':dataInicio'] = $_GET['dataInicio'];
    }
    
    if (!empty($_GET['dataFim'])) {
        $where[] = "a.data_atividade <= :dataFim";
        $params[':dataFim'] = $_GET['dataFim'];
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $query = "SELECT 
                a.id, 
                a.data_atividade,
                a.titulo,
                p.nome as propriedade_nome,
                lp.identificacao_lote,
                a.quantidade_colhida,
                a.unidade_medida
              FROM atividades_diarias a
              LEFT JOIN propriedades p ON a.propriedade_id = p.id
              LEFT JOIN lotes_producao lp ON a.lote_id = lp.id
              $whereClause
              ORDER BY a.data_atividade DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $produtividade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcula o total geral
    $totais_unidades = [];
    foreach ($produtividade as $p) {
        $u = $p['unidade_medida'] ?: 'unidades';
        if (!isset($totais_unidades[$u])) {
            $totais_unidades[$u] = 0;
        }
        $totais_unidades[$u] += (float)$p['quantidade_colhida'];
    }
?>

<?php if(!empty($totais_unidades)): ?>
<div class="row mb-4">
    <?php foreach($totais_unidades as $unidade => $total): ?>
    <div class="col-md-3">
        <div class="card bg-light border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase mb-1">Total Produzido (<?= htmlspecialchars($unidade) ?>)</h6>
                <h3 class="fw-bold mb-0 text-success"><?= number_format($total, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Data da Colheita</th>
            <th>Atividade</th>
            <th>Propriedade</th>
            <th>LoteProdução</th>
            <th>Quantidade</th>
            <th>Unidade</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($produtividade as $row): 
            $data = date('d/m/Y', strtotime($row['data_atividade']));
            $quantidade = number_format((float)$row['quantidade_colhida'], 2, ',', '.');
        ?>
        <tr>
            <td><?= $data ?></td>
            <td><?= htmlspecialchars($row['titulo']) ?></td>
            <td><?= htmlspecialchars($row['propriedade_nome'] ?? 'Não especificada') ?></td>
            <td><?= htmlspecialchars($row['identificacao_lote'] ?? 'Nenhum') ?></td>
            <td class="text-end fw-bold text-success"><?= $quantidade ?></td>
            <td><?= htmlspecialchars($row['unidade_medida'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
