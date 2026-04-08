<?php
$page_title = "Relatórios Administrativos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/ReportManager.php';

// Verifica se o usuário tem permissão (admin ou gestor)
if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
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
            'status' => $_POST['status'] ?? '',
            'tipo' => $_POST['tipo'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'tipo_fornecedor' => $_POST['tipo_fornecedor'] ?? ''
        ]);
    }
    
    // Obter dados para os relatórios
    $relatorios = [
        'usuarios' => [
            'titulo' => 'Relatório de Usuários',
            'dados' => $reportManager->getUsuariosReport($filtros),
            'colunas' => ['ID', 'Nome', 'E-mail', 'Tipo', 'Status', 'Último Acesso'],
            'campos' => ['id', 'nome', 'email', 'tipo', 'status', 'ultimo_acesso']
        ],
        'associados' => [
            'titulo' => 'Relatório de Associados',
            'dados' => $reportManager->getAssociadosReport($filtros),
            'colunas' => ['ID', 'Nome', 'CPF', 'E-mail', 'Cidade', 'Status', 'Associação'],
            'campos' => ['id', 'nome', 'cpf', 'email', 'cidade', 'status', 'associacao_nome']
        ],
        'associacoes' => [
            'titulo' => 'Relatório de Associações',
            'dados' => $reportManager->getAssociacoesReport(),
            'colunas' => ['ID', 'Nome', 'CNPJ', 'Cidade', 'Total Associados', 'Total Propriedades'],
            'campos' => ['id', 'nome', 'cnpj', 'cidade', 'total_associados', 'total_propriedades']
        ],
        'fornecedores' => [
            'titulo' => 'Relatório de Fornecedores',
            'dados' => $reportManager->getFornecedoresReport($filtros),
            'colunas' => ['ID', 'Razão Social', 'Nome Fantasia', 'CNPJ/CPF', 'Cidade', 'Telefone', 'E-mail'],
            'campos' => ['id', 'razao_social', 'nome_fantasia', 'cnpj_cpf', 'cidade', 'telefone', 'email']
        ],
        'contas' => [
            'titulo' => 'Relatório de Contas',
            'dados' => $reportManager->getContasReport($filtros),
            'colunas' => ['ID', 'Descrição', 'Tipo', 'Valor', 'Vencimento', 'Status', 'Responsável'],
            'campos' => ['id', 'descricao', 'tipo_formatado', 'valor', 'data_vencimento', 'status_formatado', 'responsavel_nome']
        ]
    ];
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erro ao gerar relatórios: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Relatórios Administrativos</h1>
            <p class="mb-0">Visualize e exporte relatórios completos do sistema</p>
        </div>
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
                        <option value="ativo" <?php echo isset($filtros['status']) && $filtros['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo isset($filtros['status']) && $filtros['status'] == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        <option value="pendente" <?php echo isset($filtros['status']) && $filtros['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="admin" <?php echo isset($filtros['tipo']) && $filtros['tipo'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="gestor" <?php echo isset($filtros['tipo']) && $filtros['tipo'] == 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                        <option value="usuario" <?php echo isset($filtros['tipo']) && $filtros['tipo'] == 'usuario' ? 'selected' : ''; ?>>Usuário</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel-fill me-1"></i> Aplicar Filtros
                    </button>
                    <a href="<?php echo SITE_URL; ?>/relatorios-admin" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs de Navegação -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab" aria-controls="usuarios" aria-selected="true">
                <i class="bi bi-people-fill me-1"></i> Usuários
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="associados-tab" data-bs-toggle="tab" data-bs-target="#associados" type="button" role="tab" aria-controls="associados" aria-selected="false">
                <i class="bi bi-person-badge-fill me-1"></i> Associados
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="associacoes-tab" data-bs-toggle="tab" data-bs-target="#associacoes" type="button" role="tab" aria-controls="associacoes" aria-selected="false">
                <i class="bi bi-building-fill me-1"></i> Associações
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="fornecedores-tab" data-bs-toggle="tab" data-bs-target="#fornecedores" type="button" role="tab" aria-controls="fornecedores" aria-selected="false">
                <i class="bi bi-truck me-1"></i> Fornecedores
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="contas-tab" data-bs-toggle="tab" data-bs-target="#contas" type="button" role="tab" aria-controls="contas" aria-selected="false">
                <i class="bi bi-cash-stack me-1"></i> Contas
            </button>
        </li>
    </ul>

    <!-- Conteúdo das Tabs -->
    <div class="tab-content" id="adminTabsContent">
        <?php foreach ($relatorios as $chave => $relatorio): ?>
            <div class="tab-pane fade <?php echo $chave === 'usuarios' ? 'show active' : ''; ?>" id="<?php echo $chave; ?>" role="tabpanel" aria-labelledby="<?php echo $chave; ?>-tab">
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
                            <div class="alert alert-info mb-0">Nenhum registro encontrado.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="tabela-<?php echo $chave; ?>" class="table table-striped table-hover">
                                    <thead>
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

<!-- Modal de Exportação -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Exportar Relatório</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="exportFormat" class="form-label">Formato de Exportação</label>
                    <select class="form-select" id="exportFormat">
                        <option value="excel">Excel (XLSX)</option>
                        <option value="pdf">PDF</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="exportFiltros" checked>
                    <label class="form-check-label" for="exportFiltros">
                        Incluir filtros aplicados
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmExport">Exportar</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<!-- Scripts para exportação -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>

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
