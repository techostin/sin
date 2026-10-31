<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguranca: exige nivel 3 (fonte de verdade: relatorios.php -> reportCategories['estoque']['level']).
// Nivel 1 = Admin, 2 = Admin/Gestor, 3 = qualquer usuario logado (semantica de User::hasPermission).
require_once __DIR__ . '/../../classes/ApiAuth.php';
ApiAuth::exigirNivel(3, false); // false = resposta em texto, pois o endpoint devolve HTML

try {
    $db = Database::getInstance()->getConnection();

    // Filtros de Estoque Atual geralmente não precisam de data, 
    // pois o status é uma "foto do momento". 
    // Só mostramos o status atual em banco.

    // IDOR: usuario de nivel 3 (comum) so pode ver os insumos do proprio associado.
    // Mesma regra de ReportManager::getInsumosReport (nivel > NIVEL_GESTOR filtra por associado).
    $where = '';
    $params = [];
    if (ApiAuth::nivel() > NIVEL_GESTOR) {
        $where = ' WHERE associado_id = :associado_id';
        $params[':associado_id'] = ApiAuth::associadoId();
    }

    $query = "SELECT 
                id, 
                nome,
                tipo_insumo,
                fabricante,
                quantidade_estoque,
                unidade_medida,
                estoque_minimo,
                status
              FROM insumos
              $where
              ORDER BY nome ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Nome do Insumo</th>
            <th>Fabricante</th>
            <th>Tipo</th>
            <th>Estoque Atual</th>
            <th>Mínimo</th>
            <th>Situação</th>
            <th>Status Cadastro</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($insumos as $row): 
            $atual = (float)$row['quantidade_estoque'];
            $minimo = (float)$row['estoque_minimo'];
            
            $situacao = 'Normal';
            $badgeColor = 'success';
            
            if ($atual <= 0) {
                $situacao = 'Esgotado';
                $badgeColor = 'danger';
            } elseif ($atual <= $minimo) {
                $situacao = 'Crítico / Comprar';
                $badgeColor = 'warning';
            }
        ?>
        <tr class="<?= $badgeColor === 'danger' ? 'table-danger' : ($badgeColor === 'warning' ? 'table-warning' : '') ?>">
            <td><?= htmlspecialchars($row['nome']) ?></td>
            <td><?= htmlspecialchars($row['fabricante'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['tipo_insumo'] ?? '-') ?></td>
            <td class="text-end fw-bold"><?= number_format($atual, 2, ',', '.') ?> <?= htmlspecialchars($row['unidade_medida']) ?></td>
            <td class="text-end"><?= number_format($minimo, 2, ',', '.') ?></td>
            <td><span class="badge bg-<?= $badgeColor ?>"><?= $situacao ?></span></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
