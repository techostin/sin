<?php
$page_title = "Dashboard de Relatórios - Caderno de Campo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Buscar estatísticas do banco de dados
$stats = [];
try {
    $db = Database::getInstance()->getConnection();
    
    // Total de atividades
    $stmt = $db->query("SELECT COUNT(*) as total FROM atividades_diarias");
    $stats['total_atividades'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Atividades por status
    $stmt = $db->query("SELECT status, COUNT(*) as total FROM atividades_diarias GROUP BY status");
    $stats['atividades_por_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de propriedades
    $stmt = $db->query("SELECT COUNT(*) as total FROM propriedades");
    $stats['total_propriedades'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de lavouras
    $stmt = $db->query("SELECT COUNT(*) as total FROM lavouras");
    $stats['total_lavouras'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de insumos
    $stmt = $db->query("SELECT COUNT(*) as total FROM insumos");
    $stats['total_insumos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de produtos
    $stmt = $db->query("SELECT COUNT(*) as total FROM produtos");
    $stats['total_produtos'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de lotes
    $stmt = $db->query("SELECT COUNT(*) as total FROM lotes_producao");
    $stats['total_lotes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Atividades por tipo
    $stmt = $db->query("SELECT tipo_atividade, COUNT(*) as total FROM atividades_diarias GROUP BY tipo_atividade ORDER BY total DESC");
    $stats['atividades_por_tipo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Atividades dos últimos 30 dias
    $stmt = $db->query("SELECT COUNT(*) as total FROM atividades_diarias WHERE data_atividade >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['atividades_30_dias'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Atividades por mês (últimos 6 meses)
    $stmt = $db->query("SELECT 
        DATE_FORMAT(data_atividade, '%Y-%m') as mes,
        COUNT(*) as total 
        FROM atividades_diarias 
        WHERE data_atividade >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(data_atividade, '%Y-%m')
        ORDER BY mes DESC");
    $stats['atividades_por_mes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 propriedades com mais atividades
    $stmt = $db->query("SELECT 
        p.propriedade,
        COUNT(ad.id) as total_atividades
        FROM propriedades p
        LEFT JOIN atividades_diarias ad ON p.id = ad.propriedade_id
        GROUP BY p.id, p.propriedade
        ORDER BY total_atividades DESC
        LIMIT 5");
    $stats['top_propriedades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 5 lavouras com mais atividades
    $stmt = $db->query("SELECT 
        l.nome as lavoura,
        l.tipo_cultura as cultura,
        COUNT(ad.id) as total_atividades
        FROM lavouras l
        LEFT JOIN atividades_diarias ad ON l.id = ad.lavoura_id
        GROUP BY l.id, l.nome, l.cultura
        ORDER BY total_atividades DESC
        LIMIT 5");
    $stats['top_lavouras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Insumos mais utilizados
    $stmt = $db->query("SELECT 
        insumo_utilisado,
        COUNT(*) as total_uso
        FROM atividades_diarias 
        WHERE insumo_utilisado IS NOT NULL AND insumo_utilisado != ''
        GROUP BY insumo_utilisado
        ORDER BY total_uso DESC
        LIMIT 10");
    $stats['insumos_mais_utilizados'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = [];
}
?>

<!-- CSS adicional para o dashboard -->
<style>
.stats-card {
    transition: transform 0.2s;
    border-left: 4px solid;
}
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.stats-card.primary { border-left-color: #007bff; }
.stats-card.success { border-left-color: #28a745; }
.stats-card.info { border-left-color: #17a2b8; }
.stats-card.warning { border-left-color: #ffc107; }
.stats-card.danger { border-left-color: #dc3545; }
.stats-card.secondary { border-left-color: #6c757d; }
.stats-card.dark { border-left-color: #343a40; }

.chart-container {
    position: relative;
    height: 300px;
    margin: 20px 0;
}

.progress-ring {
    width: 120px;
    height: 120px;
    margin: 0 auto;
}

.progress-ring-circle {
    stroke: #007bff;
    stroke-width: 8;
    fill: transparent;
    stroke-linecap: round;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.progress-ring-bg {
    stroke: #e9ecef;
    stroke-width: 8;
    fill: transparent;
}

.quick-actions {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
}

.filter-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
}
</style>

<div class="container-fluid">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard de Relatórios</h1>
            <p class="text-muted">Análise completa do Caderno de Campo</p>
        </div>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
        </div>
    </div>

    <!-- Estatísticas Principais -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total de Atividades
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_atividades'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Propriedades
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_propriedades'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-house-door-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Lavouras
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_lavouras'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-tree-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Últimos 30 Dias
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['atividades_30_dias'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="quick-actions">
        <h4 class="text-white mb-3">Ações Rápidas</h4>
        <div class="row">
            <div class="col-md-3 mb-2">
                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/geral" class="btn btn-light btn-sm w-100" target="_blank">
                    <i class="bi bi-file-earmark-text"></i> Relatório Geral
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/form_atividades.php" class="btn btn-light btn-sm w-100">
                    <i class="bi bi-calendar-check"></i> Relatório de Atividades
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/insumos" class="btn btn-light btn-sm w-100" target="_blank">
                    <i class="bi bi-droplet-half"></i> Relatório de Insumos
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/producao" class="btn btn-light btn-sm w-100" target="_blank">
                    <i class="bi bi-box-seam"></i> Relatório de Produção
                </a>
            </div>
        </div>
    </div>

    <!-- Filtros Avançados -->
    <div class="filter-section">
        <h5 class="mb-3">Filtros Avançados</h5>
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim">
            </div>
            <div class="col-md-3">
                <label for="tipo_atividade" class="form-label">Tipo de Atividade</label>
                <select class="form-select" id="tipo_atividade" name="tipo_atividade">
                    <option value="">Todos</option>
                    <option value="adubação">Adubação</option>
                    <option value="capina">Capina</option>
                    <option value="irrigação">Irrigação</option>
                    <option value="encanteramento">Encanteramento</option>
                    <option value="aplicação de bio insumo">Aplicação de Bio Insumo</option>
                    <option value="colheita">Colheita</option>
                    <option value="plantio">Plantio</option>
                    <option value="poda">Poda</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="Pendente">Pendente</option>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Concluído">Concluído</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                    <i class="bi bi-arrow-clockwise"></i> Limpar
                </button>
                <button type="button" class="btn btn-success" onclick="exportarRelatorio()">
                    <i class="bi bi-download"></i> Exportar PDF
                </button>
            </div>
        </form>
    </div>

    <!-- Gráficos e Análises -->
    <div class="row">
        <!-- Gráfico de Atividades por Status -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Atividades por Status</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Atividades por Tipo -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Atividades por Tipo</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="tipoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Atividades por Mês -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Evolução de Atividades (Últimos 6 Meses)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="evolucaoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Propriedades -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 Propriedades</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['top_propriedades'])): ?>
                        <?php foreach ($stats['top_propriedades'] as $prop): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-sm"><?php echo htmlspecialchars($prop['propriedade']); ?></span>
                                <span class="badge bg-primary"><?php echo $prop['total_atividades']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Nenhuma propriedade encontrada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatórios Específicos -->
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">Relatórios Específicos</h4>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 text-center border-primary">
                <div class="card-body">
                    <i class="bi bi-house-door-fill display-4 text-primary"></i>
                    <h5 class="card-title mt-2">Por Propriedade</h5>
                    <p class="card-text">Relatório detalhado por propriedade rural.</p>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/propriedade" class="btn btn-primary btn-sm">Gerar PDF</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 text-center border-success">
                <div class="card-body">
                    <i class="bi bi-tree-fill display-4 text-success"></i>
                    <h5 class="card-title mt-2">Por Lavoura</h5>
                    <p class="card-text">Relatório filtrado por lavoura e cultura.</p>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/lavoura" class="btn btn-success btn-sm">Gerar PDF</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 text-center border-info">
                <div class="card-body">
                    <i class="bi bi-calendar-range display-4 text-info"></i>
                    <h5 class="card-title mt-2">Por Período</h5>
                    <p class="card-text">Relatório filtrado por período específico.</p>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/periodo" class="btn btn-info btn-sm">Gerar PDF</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 text-center border-warning">
                <div class="card-body">
                    <i class="bi bi-grid-3x3-gap-fill display-4 text-warning"></i>
                    <h5 class="card-title mt-2">Lotes de Produção</h5>
                    <p class="card-text">Relatório de rastreabilidade de lotes.</p>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/lotes" class="btn btn-warning btn-sm">Gerar PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados para os gráficos
const statusData = <?php echo json_encode($stats['atividades_por_status'] ?? []); ?>;
const tipoData = <?php echo json_encode($stats['atividades_por_tipo'] ?? []); ?>;
const evolucaoData = <?php echo json_encode($stats['atividades_por_mes'] ?? []); ?>;

// Gráfico de Status
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(item => item.status),
        datasets: [{
            data: statusData.map(item => item.total),
            backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Gráfico de Tipo
const tipoCtx = document.getElementById('tipoChart').getContext('2d');
new Chart(tipoCtx, {
    type: 'bar',
    data: {
        labels: tipoData.map(item => item.tipo_atividade),
        datasets: [{
            label: 'Quantidade',
            data: tipoData.map(item => item.total),
            backgroundColor: '#007bff',
            borderColor: '#0056b3',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de Evolução
const evolucaoCtx = document.getElementById('evolucaoChart').getContext('2d');
new Chart(evolucaoCtx, {
    type: 'line',
    data: {
        labels: evolucaoData.map(item => {
            const [year, month] = item.mes.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Atividades',
            data: evolucaoData.map(item => item.total),
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Funções para filtros
function limparFiltros() {
    document.getElementById('filterForm').reset();
}

function exportarRelatorio() {
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    // Garantir parâmetros obrigatórios para o despachante de exportação
    params.set('tipo', 'pdf');
    params.set('relatorio', 'geral');
    window.open(`<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?${params.toString()}`, '_blank');
}

// Auto-submit do formulário de filtros
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Aqui você pode implementar a lógica de filtro em tempo real
    console.log('Filtros aplicados:', new FormData(this));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>