<?php
/**
 * Dashboard de Regionalização RS
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/MicrorregiaoRS.php';

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$microrregiao = new MicrorregiaoRS();
$estatisticas = $microrregiao->getEstatisticasPorMicrorregiao();
$mesorregioes = $microrregiao->listMesorregioes();

$page_title = "Regionalização RS";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Regionalização RS</h1>
            <span class="text-muted">Mesorregiões, microrregiões e indicadores por região</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/regionalizacao/mapa" class="btn btn-primary">
            <i class="bi bi-map me-1"></i>Mapa de Calor
        </a>
    </div>

    <!-- Cards de Resumo -->
    <div class="row g-3 mb-4">
        <?php
        $total_cidades = array_sum(array_column($estatisticas, 'qtd_cidades'));
        $total_associados = array_sum(array_column($estatisticas, 'qtd_associados'));
        $total_ativos = array_sum(array_column($estatisticas, 'qtd_ativos'));
        $total_inadimplentes = array_sum(array_column($estatisticas, 'qtd_inadimplentes'));
        ?>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Municípios RS</h6>
                            <h3 class="mb-0"><?php echo number_format($total_cidades); ?></h3>
                        </div>
                        <i class="bi bi-geo-alt text-primary fs-1 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Associados RS</h6>
                            <h3 class="mb-0"><?php echo number_format($total_associados); ?></h3>
                        </div>
                        <i class="bi bi-people text-success fs-1 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Ativos</h6>
                            <h3 class="mb-0"><?php echo number_format($total_ativos); ?></h3>
                        </div>
                        <i class="bi bi-check-circle text-info fs-1 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Inadimplentes</h6>
                            <h3 class="mb-0 text-danger"><?php echo number_format($total_inadimplentes); ?></h3>
                        </div>
                        <i class="bi bi-exclamation-triangle text-danger fs-1 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela por Mesorregião -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Indicadores por Mesorregião e Microrregião</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mesorregião</th>
                            <th>Microrregião</th>
                            <th class="text-center">Municípios</th>
                            <th class="text-center">Associados</th>
                            <th class="text-center">Ativos</th>
                            <th class="text-center">Inadimplentes</th>
                            <th class="text-center">% Inadimplência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mesoAtual = '';
                        foreach ($estatisticas as $row): 
                            if ($row['mesorregiao'] !== $mesoAtual) {
                                $mesoAtual = $row['mesorregiao'];
                                echo '<tr class="table-secondary"><td colspan="7"><strong>' . htmlspecialchars($mesoAtual) . '</strong></td></tr>';
                            }
                            $pctInad = $row['qtd_ativos'] > 0 
                                ? round(($row['qtd_inadimplentes'] / $row['qtd_ativos']) * 100, 1) 
                                : 0;
                        ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($row['microrregiao']); ?></td>
                            <td class="text-center"><?php echo $row['qtd_cidades']; ?></td>
                            <td class="text-center"><?php echo $row['qtd_associados']; ?></td>
                            <td class="text-center"><?php echo $row['qtd_ativos']; ?></td>
                            <td class="text-center"><?php echo $row['qtd_inadimplentes']; ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $pctInad > 20 ? 'bg-danger' : ($pctInad > 10 ? 'bg-warning' : 'bg-success'); ?>">
                                    <?php echo $pctInad; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2">TOTAL</td>
                            <td class="text-center"><?php echo $total_cidades; ?></td>
                            <td class="text-center"><?php echo $total_associados; ?></td>
                            <td class="text-center"><?php echo $total_ativos; ?></td>
                            <td class="text-center"><?php echo $total_inadimplentes; ?></td>
                            <td class="text-center">
                                <?php echo $total_ativos > 0 ? round(($total_inadimplentes / $total_ativos) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="row g-3 mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-download me-2"></i>Exportar Dados</h6>
                    <p class="text-muted small">Baixar relatório completo em CSV/Excel</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="exportarCSV()">Exportar CSV</button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-gear me-2"></i>Configurar Clusters</h6>
                    <p class="text-muted small">Definir grupos de comunicação por região</p>
                    <a href="<?php echo SITE_URL; ?>/admin/clusters" class="btn btn-outline-secondary btn-sm">Gerenciar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportarCSV() {
    // Implementar exportação
    alert('Funcionalidade de exportação a ser implementada');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>