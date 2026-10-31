<?php
/**
 * Gerenciamento de Clusters de Comunicação
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Associado.php';

$user = new User();
if (!$user->hasPermission(NIVEL_ADMIN)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();

// Definir clusters por mesorregião (mesmo mapa do update_associados_regiao.php)
$clusterPorMeso = [
    1 => 'METROPOLE',
    2 => 'SERRA_NORDESTE',
    3 => 'CENTRO_OESTE',
    4 => 'NOROESTE_CENTRAL',
    5 => 'FRONTEIRA_OESTE',
    6 => 'SUL_LITORAL',
    7 => 'NOROESTE',
];

$mesorregioes = [];
$stmt = $db->query("SELECT id, nome FROM mesorregioes_rs ORDER BY nome");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $mesorregioes[$row['id']] = $row['nome'];
}

// Buscar estatísticas por cluster
$stats = [];
$stmt = $db->query("
    SELECT 
        a.cluster_comunicacao,
        mr.nome as mesorregiao,
        mi.nome as microrregiao,
        COUNT(a.id) as qtd_associados,
        COUNT(CASE WHEN a.status = 'ativo' THEN 1 END) as qtd_ativos
    FROM associados a
    LEFT JOIN microrregioes_rs mi ON a.microrregiao_id = mi.id
    LEFT JOIN mesorregioes_rs mr ON mi.mesorregiao_id = mr.id
    WHERE a.estado = 23 AND a.cluster_comunicacao IS NOT NULL
    GROUP BY a.cluster_comunicacao, mr.nome, mi.nome
    ORDER BY mr.nome, mi.nome
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cluster = $row['cluster_comunicacao'];
    if (!isset($stats[$cluster])) {
        $stats[$cluster] = [
            'mesorregiao' => $row['mesorregiao'],
            'total' => 0,
            'ativos' => 0,
            'microrregioes' => []
        ];
    }
    $stats[$cluster]['total'] += $row['qtd_associados'];
    $stats[$cluster]['ativos'] += $row['qtd_ativos'];
    $stats[$cluster]['microrregioes'][] = [
        'nome' => $row['microrregiao'],
        'qtd' => $row['qtd_associados'],
        'ativos' => $row['qtd_ativos']
    ];
}

// Associados sem cluster
$semCluster = $db->query("
    SELECT COUNT(*) as total
    FROM associados
    WHERE estado = 23 AND cluster_comunicacao IS NULL
")->fetchColumn();

// Ação: Atribuir clusters automaticamente
$msg = '';
if (isset($_POST['atribuir_clusters'])) {
    require_once __DIR__ . '/../../classes/CSRFProtection.php';
    if (CSRFProtection::validatePostRequest()) {
        // Buscar mapa cidade -> microrregiao/mesorregiao
        $stmt = $db->query("
            SELECT c.id as cidade_id, c.microrregiao_id, mi.mesorregiao_id
            FROM cidades c
            JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
            JOIN estados e ON e.id = c.estado
            WHERE e.uf = 'RS' AND c.microrregiao_id IS NOT NULL
        ");
        $cidadeMap = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cidadeMap[$row['cidade_id']] = [
                'microrregiao_id' => $row['microrregiao_id'],
                'mesorregiao_id' => $row['mesorregiao_id']
            ];
        }

        $stmt = $db->query("
            SELECT id, cidade FROM associados 
            WHERE estado = 23 AND (microrregiao_id IS NULL OR cluster_comunicacao IS NULL)
        ");
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $atualizados = 0;
        $upd = $db->prepare("
            UPDATE associados SET microrregiao_id = ?, cluster_comunicacao = ? WHERE id = ?
        ");

        foreach ($associados as $assoc) {
            $cidadeId = (int)$assoc['cidade'];
            $info = $cidadeMap[$cidadeId] ?? null;
            if ($info) {
                $cluster = $clusterPorMeso[$info['mesorregiao_id']] ?? 'OUTRO';
                $upd->execute([$info['microrregiao_id'], $cluster, $assoc['id']]);
                $atualizados++;
            }
        }

        $msg = "<div class='alert alert-success'>Clusters atribuídos a $atualizados associados.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Token CSRF inválido.</div>";
    }
}

$page_title = "Clusters de Comunicação";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people text-primary me-2"></i>Clusters de Comunicação</h1>
            <span class="text-muted">Gerenciar grupos de comunicação por região (RS)</span>
        </div>
    </div>

    <?php echo $msg; ?>

    <!-- Ações -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Ações Rápidas</h5>
        </div>
        <div class="card-body">
            <form method="POST" class="d-inline">
                <?php echo CSRFProtection::getTokenField(); ?>
                <button type="submit" name="atribuir_clusters" class="btn btn-primary" onclick="return confirm('Atribuir clusters automaticamente aos associados do RS sem cluster?')">
                    <i class="bi bi-arrow-repeat me-1"></i> Atribuir Clusters Automaticamente
                </button>
            </form>
            <span class="text-muted ms-3 small">Baseado na mesorregião da cidade do associado (RS)</span>
        </div>
    </div>

    <!-- Resumo por Cluster -->
    <div class="row g-3 mb-4">
        <?php foreach ($clusterPorMeso as $mesoId => $clusterNome): 
            $stat = $stats[$clusterNome] ?? ['total' => 0, 'ativos' => 0, 'mesorregiao' => $mesorregioes[$mesoId] ?? ''];
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($stat['mesorregiao']); ?></h6>
                    <small class="text-muted"><?php echo htmlspecialchars($clusterNome); ?></small>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="h4 mb-0 fw-bold"><?php echo number_format($stat['total']); ?></span>
                        <span class="badge bg-primary"><?php echo $stat['ativos']; ?> ativos</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <?php if ($stat['total'] > 0): ?>
                            <div class="progress-bar bg-success" style="width: <?php echo round(($stat['ativos'] / $stat['total']) * 100); ?>%"></div>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">
                        <?php echo count($stat['microrregioes'] ?? []); ?> microrregiões
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <!-- Sem cluster -->
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100 border-warning">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 text-warning"><i class="bi bi-question-circle me-1"></i>Sem Cluster</h6>
                </div>
                <div class="card-body">
                    <div class="h4 mb-0 fw-bold text-warning"><?php echo number_format($semCluster); ?></div>
                    <small class="text-muted">Associados RS sem cluster atribuído</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalhamento por Cluster -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Detalhamento por Cluster</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="clustersTable">
                    <thead class="table-light">
                        <tr>
                            <th>Cluster</th>
                            <th>Mesorregião</th>
                            <th>Microrregião</th>
                            <th class="text-center">Associados</th>
                            <th class="text-center">Ativos</th>
                            <th class="text-center">% Ativos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clusterPorMeso as $mesoId => $clusterNome): 
                            $stat = $stats[$clusterNome] ?? ['mesorregiao' => $mesorregioes[$mesoId] ?? '', 'microrregioes' => []];
                            foreach ($stat['microrregioes'] as $micro):
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($clusterNome); ?></span></td>
                            <td><?php echo htmlspecialchars($stat['mesorregiao']); ?></td>
                            <td><?php echo htmlspecialchars($micro['nome']); ?></td>
                            <td class="text-center"><?php echo $micro['qtd']; ?></td>
                            <td class="text-center"><?php echo $micro['ativos']; ?></td>
                            <td class="text-center">
                                <?php echo $micro['qtd'] > 0 ? round(($micro['ativos'] / $micro['qtd']) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <?php endforeach; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#clustersTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "order": [[1, 'asc'], [2, 'asc']]
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>