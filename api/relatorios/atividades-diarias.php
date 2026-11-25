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

    $where = ["a.excluido = 0"];
    $params = [];

    // IDOR: usuario de nivel 3 (comum) so pode ver as atividades do proprio associado.
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

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "SELECT 
                a.id, 
                a.data_atividade,
                a.titulo,
                p.nome as propriedade_nome,
                lp.identificacao_lote,
                a.status
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
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<table id="reportTable" class="table table-striped table-bordered" style="width:100%">
    <thead>
        <tr>
            <th>Data</th>
            <th>Título da Atividade</th>
            <th>Propriedade</th>
            <th>Lote Produção</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($atividades as $row): 
            $data = date('d/m/Y', strtotime($row['data_atividade']));
            
            $statusBadge = 'secondary';
            if ($row['status'] === 'Concluído') $statusBadge = 'success';
            elseif ($row['status'] === 'Em andamento') $statusBadge = 'info';
            elseif ($row['status'] === 'Pendente') $statusBadge = 'warning';
        ?>
        <tr>
            <td><?= $data ?></td>
            <td><?= htmlspecialchars($row['titulo']) ?></td>
            <td><?= htmlspecialchars($row['propriedade_nome'] ?? 'Não especificada') ?></td>
            <td><?= htmlspecialchars($row['identificacao_lote'] ?? 'Nenhum') ?></td>
            <td><span class="badge bg-<?= $statusBadge ?>"><?= htmlspecialchars($row['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
