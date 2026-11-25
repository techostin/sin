<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/config/error_config.php';

// Incluir arquivos necessários
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/ReportManager.php';

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Obter o nível do usuário
$userLevel = $_SESSION['user_nivel'] ?? 3; // Padrão para usuário comum

// Inicializar o gerenciador de relatórios
$reportManager = new ReportManager();

// Processar filtros
$filtros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtros = $_POST;
}

// Determinar qual relatório exibir
$relatorio = $_GET['relatorio'] ?? 'dashboard';

// Configurações da página
$titulo = 'Relatórios';
$paginaAtiva = 'relatorios';
$pageTitle = $titulo;
$pageDescription = 'Acesse todos os relatórios do sistema';

// Incluir cabeçalho
require_once 'includes/header.php';
require_once 'includes/menu.php';

// Verificar se o usuário tem permissão para acessar a página
// Permissão de acesso básico (todos os usuários logados podem acessar a central)
// Os dados serão filtrados internamente no ReportManager basedo no seu nível e ID.

// Definir as categorias de relatórios
$reportCategories = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'speedometer2',
        'reports' => [
            'visao-geral' => [
                'title' => 'Visão Geral',
                'description' => 'Visão geral do sistema com os principais indicadores',
                'icon' => 'graph-up',
                'level' => 1, // Nível de acesso necessário
                'url' => 'relatorios/dashboard/visao-geral',
                'badge' => 'Novo'
            ]
        ]
    ],
    'associados' => [
        'title' => 'Associados',
        'icon' => 'people',
        'reports' => [
            'lista-associados' => [
                'title' => 'Lista de Associados',
                'description' => 'Lista completa de todos os associados',
                'icon' => 'list-ul',
                'level' => 2, // Apenas gestores e administradores
                'url' => 'relatorios/associados/lista'
            ],
            'status-associados' => [
                'title' => 'Status dos Associados',
                'description' => 'Relatório de status e adimplência',
                'icon' => 'clipboard-check',
                'level' => 2,
                'url' => 'relatorios/associados/status'
            ]
        ]
    ],
    'atividades' => [
        'title' => 'Atividades',
        'icon' => 'calendar-check',
        'reports' => [
            'atividades-diarias' => [
                'title' => 'Atividades Diárias',
                'description' => 'Relatório de atividades realizadas',
                'icon' => 'calendar-day',
                'level' => 3,
                'url' => 'relatorios/atividades/diarias'
            ],
            'produtividade' => [
                'title' => 'Produtividade',
                'description' => 'Análise de produtividade por período',
                'icon' => 'bar-chart-line',
                'level' => 3,
                'url' => 'relatorios/atividades/produtividade'
            ]
        ]
    ],
    'estoque' => [
        'title' => 'Estoque',
        'icon' => 'box-seam',
        'reports' => [
            'estoque-atual' => [
                'title' => 'Estoque Atual',
                'description' => 'Situação atual do estoque de insumos',
                'icon' => 'clipboard-data',
                'level' => 3,
                'url' => 'relatorios/estoque/atual'
            ],
            'movimentacoes' => [
                'title' => 'Movimentações',
                'description' => 'Histórico de entradas e saídas',
                'icon' => 'arrow-left-right',
                'level' => 2,
                'url' => 'relatorios/estoque/movimentacoes'
            ]
        ]
    ],
    'financeiro' => [
        'title' => 'Financeiro',
        'icon' => 'cash-stack',
        'reports' => [
            'fluxo-caixa' => [
                'title' => 'Fluxo de Caixa',
                'description' => 'Análise de receitas e despesas',
                'icon' => 'graph-up',
                'level' => 2,
                'url' => 'relatorios/financeiro/fluxo-caixa'
            ],
            'contas-pagar' => [
                'title' => 'Contas a Pagar',
                'description' => 'Relatório de contas a pagar',
                'icon' => 'receipt',
                'level' => 2,
                'url' => 'relatorios/financeiro/contas-pagar'
            ],
            'contas-receber' => [
                'title' => 'Contas a Receber',
                'description' => 'Relatório de contas a receber',
                'icon' => 'wallet2',
                'level' => 2,
                'url' => 'relatorios/financeiro/contas-receber'
            ]
        ]
    ],
    'associacao' => [
        'title' => 'Associação',
        'icon' => 'people-fill',
        'reports' => [
            'inadimplencia' => [
                'title' => 'Inadimplência',
                'description' => 'Inadimplência por mês e por categoria de associado',
                'icon' => 'exclamation-triangle',
                'level' => 2,
                'url' => 'relatorios/associacao/inadimplencia'
            ],
            'pagamentos-por-forma' => [
                'title' => 'Pagamentos por Forma',
                'description' => 'Receitas por forma de pagamento (PIX, boleto, etc.)',
                'icon' => 'credit-card-2-front',
                'level' => 2,
                'url' => 'relatorios/associacao/pagamentos-por-forma'
            ],
            'previsao-receita' => [
                'title' => 'Previsão de Receita',
                'description' => 'Previsão de receitas das mensalidades (3 meses)',
                'icon' => 'graph-up-arrow',
                'level' => 2,
                'url' => 'relatorios/associacao/previsao-receita'
            ],
            'fluxo-caixa-realizado-previsto' => [
                'title' => 'Fluxo de Caixa Realizado × Previsto',
                'description' => 'Comparativo entre o realizado e o previsto das mensalidades e caixa',
                'icon' => 'arrow-left-right',
                'level' => 2,
                'url' => 'relatorios/associacao/fluxo-caixa-realizado-previsto'
            ]
        ]
    ]
];

// Verificar se há um relatório específico sendo solicitado
$requestedReport = $_GET['relatorio'] ?? null;
$reportData = null;

if ($requestedReport) {
    foreach ($reportCategories as $category) {
        if (isset($category['reports'][$requestedReport])) {
            $reportData = $category['reports'][$requestedReport];
            $pageTitle = $reportData['title'] . ' - Relatórios';
            
            // Verificar permissão do usuário para o relatório
            if ($userLevel > $reportData['level']) {
                echo '<div class="container mt-4">
                        <div class="alert alert-warning">Você não tem permissão para acessar este relatório.</div>
                      </div>';
                require_once 'includes/footer.php';
                exit();
            }
            
            break;
        }
    }
    
    // Se o relatório não for encontrado
    if (!$reportData) {
        echo '<div class="container mt-4">
                <div class="alert alert-danger">Relatório não encontrado.</div>
              </div>';
        require_once 'includes/footer.php';
        exit();
    }
}

// Página de relatório específico: filtros + conteúdo carregado via AJAX
if ($requestedReport && $reportData) {
    ?>
    <div class="container-fluid px-4 py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i><?php echo htmlspecialchars($reportData['title']); ?></h1>
                <span class="text-muted"><?php echo htmlspecialchars($reportData['description']); ?></span>
            </div>
            <a href="<?php echo SITE_URL; ?>/relatorios" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Todos os relatórios</a>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtros</div>
            <div class="card-body">
                <form id="reportFilters" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Data Início</label>
                        <input type="date" name="dataInicio" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Data Fim</label>
                        <input type="date" name="dataFim" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Associado</label>
                        <select name="filtro_associado_id" id="filtro_associado_id" class="form-select form-select-sm">
                            <option value="">Todos os associados</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="reportContent">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Carregando dados do relatório...</p>
            </div>
        </div>
    </div>

    <link href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script>
    $(document).ready(function() {
        var reportUrl = 'api/relatorios/<?php echo $requestedReport; ?>.php';

        function loadReportData() {
            $.ajax({
                url: reportUrl,
                method: 'GET',
                data: $('#reportFilters').serialize(),
                beforeSend: function() {
                    $('#reportContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Carregando dados do relatório...</p></div>');
                },
                success: function(response) {
                    $('#reportContent').html(response);
                    if ($.fn.DataTable.isDataTable('#reportTable')) {
                        $('#reportTable').DataTable().destroy();
                    }
                    $('#reportTable').DataTable({
                        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' },
                        responsive: true,
                        dom: 'Bfrtip',
                        buttons: ['copy', 'csv', 'excel', {
                            extend: 'pdfHtml5', orientation: 'landscape', pageSize: 'A4',
                            customize: function(doc) {
                                if (doc.content[1] && doc.content[1].table) {
                                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                                }
                                doc.defaultStyle.fontSize = 10;
                                doc.styles.tableHeader.fontSize = 11;
                            }
                        }, 'print']
                    });
                },
                error: function() {
                    $('#reportContent').html('<div class="alert alert-danger">Ocorreu um erro ao carregar o relatório. Tente novamente mais tarde.</div>');
                }
            });
        }

        $.ajax({
            url: 'api/associados/listar.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data && response.data.length > 0) {
                    var options = '<option value="">Todos os associados</option>';
                    $.each(response.data, function(index, associado) {
                        options += '<option value="' + associado.id + '">' + (associado.nome || '') + '</option>';
                    });
                    $('#filtro_associado_id').html(options);
                }
            },
            error: function() {
                console.error('Erro ao carregar associados para o filtro.');
            }
        });

        $('#reportFilters').on('submit', function(e) {
            e.preventDefault();
            loadReportData();
        });
        loadReportData();
    });
    </script>
    <?php
    require_once 'includes/footer.php';
    exit();
}

?>

<?php
$resumo = $reportManager->getResumoGeral();
?>

<style>
    .report-card { transition: transform 0.2s, box-shadow 0.2s; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .report-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .bg-soft-blue { background-color: #e7f1ff; color: #0d6efd; }
    .bg-soft-green { background-color: #e8f5e9; color: #2e7d32; }
    .bg-soft-yellow { background-color: #fff9c4; color: #fbc02d; }
    .bg-soft-purple { background-color: #f3e5f5; color: #7b1fa2; }
    .stat-card { border-radius: 12px; border: none; overflow: hidden; }
    .stat-card .card-body { padding: 1.5rem; }
    .stat-value { font-size: 2.5rem; font-weight: 800; line-height: 1; margin-bottom: 0.5rem; }
    .stat-label { text-transform: uppercase; font-size: 0.75rem; font-weight: 600; letter-spacing: 1px; opacity: 0.8; }
    .btn-report { padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 6px; }
</style>

<div class="container-fluid py-4">
    <!-- Visão Geral -->
    <h4 class="mb-4 fw-bold">Visão Geral</h4>
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white shadow">
                <div class="card-body">
                    <div class="stat-label">Associados</div>
                    <div class="stat-value"><?php echo $resumo['total_associados']; ?></div>
                    <div class="d-flex gap-2 mb-2">
                        <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=produtores" class="btn btn-sm btn-light btn-report">Gerar PDF</a>
                        <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=produtores" class="btn btn-sm btn-light btn-report">Excel</a>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/associados" class="text-white-50 text-decoration-none small">Ver detalhes</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white shadow">
                <div class="card-body">
                    <div class="stat-label">Propriedades</div>
                    <div class="stat-value"><?php echo $resumo['total_propriedades']; ?></div>
                    <div class="d-flex gap-2 mb-2">
                        <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=propriedades" class="btn btn-sm btn-light btn-report">Gerar PDF</a>
                        <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=propriedades" class="btn btn-sm btn-light btn-report">Excel</a>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/propriedades" class="text-white-50 text-decoration-none small">Ver detalhes</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark shadow">
                <div class="card-body">
                    <div class="stat-label">Atividades Hoje</div>
                    <div class="stat-value"><?php echo $resumo['atividades_hoje']; ?></div>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/atividades-diarias" class="text-dark text-opacity-50 text-decoration-none small mt-3 d-block">Ver atividades</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white shadow">
                <div class="card-body">
                    <div class="stat-label">Insumos Críticos</div>
                    <div class="stat-value"><?php echo $resumo['insumos_criticos']; ?></div>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="text-white-50 text-decoration-none small mt-3 d-block">Ver itens</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Categorias de Relatórios -->
    <h4 class="mb-4 fw-bold">Categorias de Relatórios</h4>
    <div class="row g-4 mb-5">
        <?php foreach ($reportCategories as $catKey => $cat): ?>
        <div class="col-md-4">
            <div class="card report-card h-100">
                <div class="card-header bg-dark text-white py-2">
                    <i class="bi bi-<?php echo $cat['icon']; ?> me-1"></i> <?php echo $cat['title']; ?>
                </div>
                <div class="card-body p-2">
                    <div class="list-group list-group-flush">
                        <?php foreach ($cat['reports'] as $slug => $rep):
                            if ($userLevel > $rep['level']) { continue; }
                        ?>
                        <a href="<?php echo SITE_URL; ?>/<?php echo $rep['url']; ?>" class="list-group-item list-group-item-action py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><i class="bi bi-<?php echo $rep['icon']; ?> me-2 text-primary"></i><?php echo $rep['title']; ?></span>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </div>
                            <div class="small text-muted mt-1"><?php echo htmlspecialchars($rep['description']); ?></div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Gerar Relatórios -->
    <h4 class="mb-4 fw-bold">Gerar Relatórios</h4>
    <div class="row g-4">
        <!-- Relatório de Associados -->
        <?php if ($userLevel <= 2): ?>
        <div class="col-md-4">
            <div class="card report-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-soft-blue p-2 rounded me-3">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <h5 class="mb-0 card-title">Relatório de Associados</h5>
                    </div>
                    <p class="text-muted small mb-4">Gere relatórios completos com informações dos associados, incluindo dados pessoais, contatos e situação cadastral.</p>
                    
                    <form action="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar" method="GET" id="formAssociados">
                        <input type="hidden" name="relatorio" value="produtores">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome da Associação</label>
                            <input type="text" name="associacao" class="form-control form-control-sm" placeholder="Ex: Associação dos Produtores Rurais">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Responsável</label>
                            <input type="text" name="responsavel" class="form-control form-control-sm" placeholder="Opcional - Nome do responsável">
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <button type="submit" name="tipo" value="pdf" class="btn btn-outline-danger w-100 btn-sm">Gerar PDF</button>
                            </div>
                            <div class="col-6">
                                <button type="submit" name="tipo" value="excel" class="btn btn-outline-success w-100 btn-sm">Exportar Excel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Relatório de Propriedades -->
        <div class="col-md-4">
            <div class="card report-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-soft-green p-2 rounded me-3">
                            <i class="bi bi-house-door-fill fs-4"></i>
                        </div>
                        <h5 class="mb-0 card-title">Relatório de Propriedades</h5>
                    </div>
                    <p class="text-muted small mb-4">Relatório detalhado das propriedades rurais, incluindo localização, área total, culturas e situação cadastral.</p>
                    
                    <div class="mt-auto">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=propriedades" class="btn btn-outline-danger w-100 btn-sm">Gerar PDF</a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=propriedades" class="btn btn-outline-success w-100 btn-sm">Exportar Excel</a>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios" class="btn btn-link btn-sm text-decoration-none">Filtrar <i class="bi bi-funnel"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Relatório de Atividades -->
        <div class="col-md-4">
            <div class="card report-card h-100 text-center d-flex flex-column justify-content-center py-4">
                <div class="bg-soft-yellow p-3 rounded-circle mx-auto mb-3" style="width: fit-content;">
                    <i class="bi bi-calendar-check-fill fs-3"></i>
                </div>
                <h5 class="mb-2 card-title">Relatório de Atividades</h5>
                <p class="text-muted small mb-4 px-3">Registro completo das atividades diárias realizadas nas propriedades.</p>
                <div class="px-4 gap-2 d-grid">
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=atividades" class="btn btn-outline-primary btn-sm rounded-pill">Gerar PDF</a>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=atividades" class="btn btn-outline-success btn-sm rounded-pill">Exportar Excel</a>
                </div>
            </div>
        </div>

        <!-- Relatório de Insumos -->
        <div class="col-md-4">
            <div class="card report-card h-100 text-center d-flex flex-column justify-content-center py-4">
                <div class="bg-soft-blue p-3 rounded-circle mx-auto mb-3" style="width: fit-content;">
                    <i class="bi bi-box-seam-fill fs-3 text-info"></i>
                </div>
                <h5 class="mb-2 card-title">Relatório de Insumos</h5>
                <p class="text-muted small mb-4 px-3">Controle de estoque de insumos, incluindo níveis críticos e histórico de movimentação.</p>
                <div class="px-4 gap-2 d-grid">
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=insumos" class="btn btn-outline-primary btn-sm rounded-pill">Gerar PDF</a>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=insumos" class="btn btn-outline-success btn-sm rounded-pill">Exportar Excel</a>
                </div>
            </div>
        </div>

        <!-- Relatório de Produção -->
        <div class="col-md-4">
            <div class="card report-card h-100 text-center d-flex flex-column justify-content-center py-4">
                <div class="bg-soft-purple p-3 rounded-circle mx-auto mb-3" style="width: fit-content;">
                    <i class="bi bi-brightness-high-fill fs-3 text-primary"></i>
                </div>
                <h5 class="mb-2 card-title">Relatório de Produção</h5>
                <p class="text-muted small mb-4 px-3">Acompanhamento da produção agrícola por cultura, safra e propriedade.</p>
                <div class="px-4 gap-2 d-grid">
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=producao" class="btn btn-outline-primary btn-sm rounded-pill">Gerar PDF</a>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=producao" class="btn btn-outline-success btn-sm rounded-pill">Exportar Excel</a>
                </div>
            </div>
        </div>

        <!-- Relatório Personalizado -->
        <div class="col-md-4">
            <div class="card report-card h-100 text-center d-flex flex-column justify-content-center py-4">
                <div class="bg-soft-yellow p-3 rounded-circle mx-auto mb-3" style="width: fit-content; background-color: #fff8e1;">
                    <i class="bi bi-gear-fill fs-3 text-warning"></i>
                </div>
                <h5 class="mb-2 card-title">Relatório Personalizado</h5>
                <p class="text-muted small mb-4 px-3">Crie um relatório personalizado com os filtros e colunas desejadas.</p>
                <div class="px-4 d-grid">
                    <button class="btn btn-outline-dark btn-sm" disabled>Personalizar Relatório</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ... scripts e modal ...
?>

<!-- Modal para visualização de relatórios rápidos -->
<div class="modal fade" id="quickReportModal" tabindex="-1" aria-labelledby="quickReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickReportModalLabel">Relatório Rápido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="quickReportContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando relatório...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="printQuickReport">
                    <i class="bi bi-printer me-1"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
require_once 'includes/footer.php';
?>

<!-- Scripts necessários -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Manipular clique nos relatórios rápidos
    $('.quick-report').on('click', function(e) {
        e.preventDefault();
        const reportType = $(this).data('report');
        const modal = new bootstrap.Modal(document.getElementById('quickReportModal'));
        
        // Atualizar título do modal
        $('#quickReportModalLabel').text($(this).find('h6').text());
        
        // Carregar conteúdo do relatório via AJAX
        $.ajax({
            url: 'api/relatorios/rapido.php',
            method: 'GET',
            data: { tipo: reportType },
            beforeSend: function() {
                $('#quickReportContent').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando relatório...</p>
                    </div>
                `);
            },
            success: function(response) {
                $('#quickReportContent').html(response);
                
                // Inicializar DataTable se existir uma tabela
                if ($.fn.DataTable.isDataTable('#quickReportTable')) {
                    $('#quickReportTable').DataTable({
                        language: {
                            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
                        },
                        responsive: true
                    });
                }
            },
            error: function() {
                $('#quickReportContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Ocorreu um erro ao carregar o relatório. Tente novamente mais tarde.
                    </div>
                `);
            }
        });
        
        modal.show();
    });
    
    // Manipular impressão do relatório rápido
    $('#printQuickReport').on('click', function() {
        window.print();
    });
    
    // Se for uma página de relatório específico, carregar os dados
    <?php if ($requestedReport && $reportData): ?>
    function loadReportData() {
        $.ajax({
            url: 'api/relatorios/<?php echo $requestedReport; ?>.php',
            method: 'GET',
            data: $('#reportFilters').serialize(),
            beforeSend: function() {
                $('#reportContent').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando dados do relatório...</p>
                    </div>
                `);
            },
            success: function(response) {
                $('#reportContent').html(response);
                
                // Inicializar DataTable se existir uma tabela
                if ($.fn.DataTable.isDataTable('#reportTable')) {
                    $('#reportTable').DataTable().destroy();
                }
                $('#reportTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
                    },
                    responsive: true,
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel',
                        {
                            extend: 'pdfHtml5',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            customize: function(doc) {
                                if (doc.content[1] && doc.content[1].table) {
                                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                                }
                                doc.defaultStyle.fontSize = 10;
                                doc.styles.tableHeader.fontSize = 11;
                            }
                        },
                        'print'
                    ]
                });
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar relatório:', error);
                $('#reportContent').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Ocorreu um erro ao carregar o relatório. Tente novamente mais tarde.
                        <div class="mt-2 small text-muted">${xhr.status}: ${xhr.statusText}</div>
                    </div>
                `);
            }
        });
    }
    
    // Carregar dados iniciais
    loadReportData();
    
    // Atualizar relatório ao enviar o formulário de filtros
    $('#reportFilters').on('submit', function(e) {
        e.preventDefault();
        loadReportData();
    });
    
    // Exportar para Excel
    $('#exportExcel').on('click', function() {
        // Implementar lógica de exportação para Excel
        alert('Exportando para Excel...');
    });
    
    // Imprimir relatório
    $('#printReport').on('click', function() {
        window.print();
    });
    
    // Carregar lista de associados para o filtro (se necessário)
    <?php if ($userLevel <= 2): // Carregar lista de associados para o filtro (apenas para administradores e gestores) ?>
    if ($userLevel <= 2) {
        $.ajax({
            url: 'api/associados/listar.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.data && response.data.length > 0) {
                    var options = '<option value="">Todos os associados</option>';
                    $.each(response.data, function(index, associado) {
                        options += '<option value="' + associado.id + '">' + (associado.nome || '') + '</option>';
                    });
                    $('#filtro_associado_id').html(options);
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar associados:', error);
                $('#filtro_associado_id').html('<option value="">Erro ao carregar associados</option>');
            }
        });
    }
    <?php endif; ?>
    
    <?php endif; ?>
});

// Função para exportar tabela para Excel
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Relatório'});
    
    // Gerar nome do arquivo com data atual
    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];
    const finalFilename = `${filename}_${dateStr}.xlsx`;
    
    // Baixar o arquivo
    XLSX.writeFile(wb, finalFilename);
}
</script>

<!-- Seção duplicada removida -->
                
                <!-- Relatório de Associados (Apenas gestores e administradores) -->
                <?php if ($userLevel <= 2): ?>
                <div class="tab-pane fade" id="associados" role="tabpanel" aria-labelledby="associados-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Relatório de Associados</h4>
                        <button class="btn btn-primary" onclick="window.open('relatorio_associados.php', '_blank')">
                            <i class="fas fa-external-link-alt me-1"></i> Abrir em Nova Aba
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaAssociados">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Telefone</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $associados = $reportManager->getAssociadosReport($filtros);
                                    foreach ($associados as $associado):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($associado['id']); ?></td>
                                    <td><?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?></td>
                                    <td><?php echo htmlspecialchars($associado['email']); ?></td>
                                    <td><?php echo htmlspecialchars($associado['telefone']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $associado['status'] === 'ativo' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($associado['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="associados/visualizar.php?id=<?php echo $associado['id']; ?>" class="btn btn-sm btn-info" title="Visualizar" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar associados: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Relatório de Atividades -->
                <div class="tab-pane fade" id="atividades" role="tabpanel" aria-labelledby="atividades-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Relatório de Atividades</h4>
                        <button class="btn btn-primary" onclick="window.open('relatorio_atividades.php', '_blank')">
                            <i class="fas fa-external-link-alt me-1"></i> Abrir em Nova Aba
                        </button>
                    </div>
                    
                    <!-- Filtros -->
                    <form method="POST" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="data_inicio" class="form-label">Data Início</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $filtros['data_inicio'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="data_fim" class="form-label">Data Fim</label>
                                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $filtros['data_fim'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Todos</option>
                                    <option value="Pendente" <?php echo (isset($filtros['status']) && $filtros['status'] === 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="Em andamento" <?php echo (isset($filtros['status']) && $filtros['status'] === 'Em andamento') ? 'selected' : ''; ?>>Em andamento</option>
                                    <option value="Concluído" <?php echo (isset($filtros['status']) && $filtros['status'] === 'Concluído') ? 'selected' : ''; ?>>Concluído</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <a href="relatorios.php?relatorio=atividades" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaAtividades">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Atividade</th>
                                    <th>Propriedade</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $atividades = $reportManager->getAtividadesReport($filtros);
                                    foreach ($atividades as $atividade):
                                        $statusClass = [
                                            'Pendente' => 'warning',
                                            'Em andamento' => 'info',
                                            'Concluído' => 'success'
                                        ][$atividade['status']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($atividade['data_atividade'])); ?></td>
                                    <td><?php echo htmlspecialchars($atividade['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($atividade['propriedade_nome'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $atividade['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="modulos/caderno_campo/atividade_diaria/visualizar.php?id=<?php echo $atividade['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Visualizar" 
                                           target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="5" class="text-center text-danger">Erro ao carregar atividades: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Relatório de Insumos -->
                <div class="tab-pane fade" id="insumos" role="tabpanel" aria-labelledby="insumos-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Relatório de Insumos</h4>
                        <div>
                            <button class="btn btn-primary me-2" onclick="window.open('relatorio_insumos.php', '_blank')">
                                <i class="fas fa-external-link-alt me-1"></i> Abrir em Nova Aba
                            </button>
                            <button class="btn btn-success" onclick="exportarParaExcel('tabelaInsumos', 'relatorio_insumos')">
                                <i class="fas fa-file-excel me-1"></i> Exportar Excel
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaInsumos">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Fornecedor</th>
                                    <th>Estoque</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $insumos = $reportManager->getInsumosReport($filtros);
                                    foreach ($insumos as $insumo):
                                ?>
                                <tr class="<?php echo ($insumo['quantidade_estoque'] <= $insumo['estoque_minimo']) ? 'table-warning' : ''; ?>">
                                    <td><?php echo htmlspecialchars($insumo['id']); ?></td>
                                    <td><?php echo htmlspecialchars($insumo['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($insumo['descricao'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($insumo['fornecedor_nome'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php
                                            $porcentagem = min(100, ($insumo['quantidade_estoque'] / ($insumo['estoque_maximo'] ?? 100)) * 100);
                                            $cor = 'bg-success';
                                            if ($insumo['quantidade_estoque'] <= $insumo['estoque_minimo']) {
                                                $cor = 'bg-danger';
                                            } elseif ($insumo['quantidade_estoque'] <= ($insumo['estoque_minimo'] * 1.5)) {
                                                $cor = 'bg-warning';
                                            }
                                            ?>
                                            <div class="progress-bar <?php echo $cor; ?>" role="progressbar" 
                                                 style="width: <?php echo $porcentagem; ?>%" 
                                                 aria-valuenow="<?php echo $porcentagem; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $insumo['quantidade_estoque']; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="modulos/estoque/visualizar.php?id=<?php echo $insumo['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Visualizar" 
                                           target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } catch (Exception $e) {
                                    echo '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar insumos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Relatório Financeiro (Apenas gestores e administradores) -->
                <?php if ($userLevel <= 2): ?>
                <div class="tab-pane fade" id="financeiro" role="tabpanel" aria-labelledby="financeiro-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Relatório Financeiro</h4>
                        <div>
                            <button class="btn btn-primary me-2" onclick="window.open('relatorio_financeiro.php', '_blank')">
                                <i class="fas fa-external-link-alt me-1"></i> Abrir em Nova Aba
                            </button>
                            <button class="btn btn-success" onclick="exportarParaExcel('tabelaFinanceiro', 'relatorio_financeiro')">
                                <i class="fas fa-file-excel me-1"></i> Exportar Excel
                            </button>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Receitas do Mês</h6>
                                    <h3 class="mb-0">R$ 15.250,00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Despesas do Mês</h6>
                                    <h3 class="mb-0">R$ 8.750,00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Saldo Atual</h6>
                                    <h3 class="mb-0">R$ 6.500,00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Contas a Pagar</h6>
                                    <h3 class="mb-0">R$ 3.200,00</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="tabelaFinanceiro">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>05/11/2025</td>
                                    <td>Venda de produtos</td>
                                    <td>Vendas</td>
                                    <td class="text-success">R$ 1.250,00</td>
                                    <td><span class="badge bg-success">Receita</span></td>
                                    <td><span class="badge bg-success">Pago</span></td>
                                </tr>
                                <tr>
                                    <td>04/11/2025</td>
                                    <td>Compra de insumos</td>
                                    <td>Insumos</td>
                                    <td class="text-danger">- R$ 850,00</td>
                                    <td><span class="badge bg-danger">Despesa</span></td>
                                    <td><span class="badge bg-warning">Pendente</span></td>
                                </tr>
                                <tr>
                                    <td>03/11/2025</td>
                                    <td>Manutenção de equipamentos</td>
                                    <td>Manutenção</td>
                                    <td class="text-danger">- R$ 1.200,00</td>
                                    <td><span class="badge bg-danger">Despesa</span></td>
                                    <td><span class="badge bg-success">Pago</span></td>
                                </tr>
                                <tr>
                                    <td>02/11/2025</td>
                                    <td>Venda de produtos</td>
                                    <td>Vendas</td>
                                    <td class="text-success">R$ 2.150,00</td>
                                    <td><span class="badge bg-success">Receita</span></td>
                                    <td><span class="badge bg-success">Pago</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir rodapé
require_once 'includes/footer.php';
?>
