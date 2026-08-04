<?php
$page_title = "Relatórios - Caderno de Campo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/ReportManager.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

try {
    $reportManager = new ReportManager();
    
    // Processar filtros do formulário
    $filtros = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $filtros = array_filter([
            'data_inicio' => $_POST['data_inicio'] ?? '',
            'data_fim' => $_POST['data_fim'] ?? '',
            'categoria_id' => $_POST['categoria_id'] ?? '',
            'status' => $_POST['status'] ?? ''
        ]);
    }
    
    // Obter dados para os relatórios
    $relatorios = [
        'atividades' => [
            'titulo' => 'Atividades',
            'dados' => $reportManager->getAtividadesReport($filtros),
            'colunas' => ['Data', 'Atividade', 'Propriedade', 'Lavoura', 'Status', 'Responsável'],
            'campos' => ['data_atividade', 'descricao', 'propriedade', 'lavoura_nome', 'status', 'usuario_nome']
        ],
        'insumos' => [
            'titulo' => 'Insumos',
            'dados' => $reportManager->getInsumosReport($filtros),
            'colunas' => ['Nome', 'Categoria', 'Quantidade', 'Unidade', 'Fornecedor', 'Valor Unitário'],
            'campos' => ['nome', 'categoria_nome', 'quantidade', 'unidade', 'fornecedor', 'valor_unitario']
        ],
        'lotes' => [
            'titulo' => 'Lotes de Produção',
            'dados' => $reportManager->getLotesReport($filtros),
            'colunas' => ['Código', 'Produto', 'Data Início', 'Status', 'Responsável', 'Total Produzido'],
            'campos' => ['codigo', 'produto_nome', 'data_inicio', 'status', 'responsavel_nome', 'total_producoes']
        ],
        'producao' => [
            'titulo' => 'Produção',
            'dados' => $reportManager->getProducaoReport($filtros),
            'colunas' => ['Data', 'Produto', 'Lote', 'Quantidade', 'Responsável'],
            'campos' => ['data_producao', 'produto_nome', 'lote_codigo', 'quantidade', 'responsavel_nome']
        ],
        'propriedade' => [
            'titulo' => 'Propriedades',
            'dados' => $reportManager->getPropriedadesReport($filtros),
            'colunas' => ['Nome', 'Cidade', 'Área Total', 'Responsável', 'Total Lavouras'],
            'campos' => ['nome', 'cidade', 'area_total', 'responsavel_nome', 'total_lavouras']
        ],
        'ocorrencias' => [
            'titulo' => 'Ocorrências',
            'dados' => $reportManager->getOcorrenciasReport($filtros),
            'colunas' => ['Data', 'Tipo', 'Nome Comum', 'Severidade', 'Status', 'Propriedade', 'Produtor'],
            'campos' => ['data_deteccao', 'tipo', 'nome_comum', 'severidade', 'status', 'propriedade_nome', 'associado_nome']
    ];
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erro ao carregar relatórios: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Relatórios</h1>
            <p class="mb-0">Visualize e exporte relatórios do sistema</p>
        </div>
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
            <a href="<?php echo SITE_URL; ?>/relatorios-admin" class="btn btn-outline-primary">
                <i class="bi bi-graph-up me-1"></i> Relatórios Administrativos
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
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
                        <option value="pendente" <?php echo isset($filtros['status']) && $filtros['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="concluido" <?php echo isset($filtros['status']) && $filtros['status'] == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                        <option value="cancelado" <?php echo isset($filtros['status']) && $filtros['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel-fill me-1"></i> Aplicar Filtros
                    </button>
                    <a href="<?php echo SITE_URL; ?>/relatorios" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs de Navegação -->
    <ul class="nav nav-tabs mb-4" id="relatoriosTabs" role="tablist">
        <?php foreach ($relatorios as $chave => $relatorio): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $chave === 'atividades' ? 'active' : ''; ?>" 
                        id="<?php echo $chave; ?>-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#<?php echo $chave; ?>" 
                        type="button" 
                        role="tab" 
                        aria-controls="<?php echo $chave; ?>" 
                        aria-selected="<?php echo $chave === 'atividades' ? 'true' : 'false'; ?>">
                    <i class="bi <?php 
                        $icons = [
                            'atividades' => 'bi-calendar-check-fill',
                            'insumos' => 'bi-box-seam-fill',
                            'lotes' => 'bi-collection-fill',
                            'producao' => 'bi-arrow-up-circle-fill',
                            'ocorrencias' => 'bi-exclamation-triangle-fill'
                        ];
                        echo $icons[$chave] ?? 'bi-house-fill';
                    ?> me-1"></i> 
                    <?php echo $relatorio['titulo']; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Conteúdo das Tabs -->
    <div class="tab-content" id="relatoriosTabsContent">
        <?php foreach ($relatorios as $chave => $relatorio): ?>
            <div class="tab-pane fade <?php echo $chave === 'atividades' ? 'show active' : ''; ?>" 
                 id="<?php echo $chave; ?>" 
                 role="tabpanel" 
                 aria-labelledby="<?php echo $chave; ?>-tab">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo $relatorio['titulo']; ?></h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="exportToExcel('<?php echo $chave; ?>')">
                                <i class="bi bi-file-earmark-excel-fill me-1"></i> Exportar Excel
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="exportToPDF('<?php echo $chave; ?>')">
                                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Exportar PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($relatorio['dados'])): ?>
                            <div class="alert alert-info mb-0">Nenhum registro encontrado com os filtros atuais.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="tabela-<?php echo $chave; ?>" class="table table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <?php foreach ($relatorio['colunas'] as $coluna): ?>
                                                <th><?php echo $coluna; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($relatorio['dados'] as $linha): ?>
                                            <tr>
                                                <?php foreach ($relatorio['campos'] as $campo): ?>
                                                    <td><?php echo $linha[$campo] ?? '-'; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Inicializar DataTables para todas as tabelas
$(document).ready(function() {
    $('table[id^="tabela-"]').DataTable({
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
                    doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                    doc.defaultStyle.fontSize = 10;
                    doc.styles.tableHeader.fontSize = 11;
                }
            },
            {
                extend: 'print',
                customize: function (win) {
                    var css = '@page { size: landscape; }',
                        head = win.document.head || win.document.getElementsByTagName('head')[0],
                        style = win.document.createElement('style');
                    style.type = 'text/css';
                    style.media = 'print';
                    style.appendChild(win.document.createTextNode(css));
                    head.appendChild(style);
                }
            }
        ]
    });
    
    // Ativar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Função para exportar para Excel
function exportToExcel(tabelaId) {
    const relatorioId = tabelaId.replace('tabela-', '');
    window.location.href = `<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=excel&relatorio=${relatorioId}&${$('form').serialize()}`;
}

// Função para exportar para PDF
function exportToPDF(tabelaId) {
    const relatorioId = tabelaId.replace('tabela-', '');
    window.location.href = `<?php echo SITE_URL; ?>/caderno-campo/relatorios/exportar?tipo=pdf&relatorio=${relatorioId}&${$('form').serialize()}`;
}
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
?>
