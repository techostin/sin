<?php
// Iniciar a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Título da página
$pageTitle = 'Relatório Personalizado';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

// Opções de tabelas e campos disponíveis para relatórios
$tabelasCampos = [
    'atividades' => [
        'nome' => 'Atividades',
        'campos' => [
            'id' => 'ID',
            'nome' => 'Nome da Atividade',
            'descricao' => 'Descrição',
            'data_atividade' => 'Data da Atividade',
            'hora_inicio' => 'Hora de Início',
            'hora_fim' => 'Hora de Término',
            'status' => 'Status',
            'observacoes' => 'Observações',
            'criado_em' => 'Data de Criação',
            'atualizado_em' => 'Última Atualização'
        ]
    ],
    'propriedades' => [
        'nome' => 'Propriedades',
        'campos' => [
            'id' => 'ID',
            'nome' => 'Nome da Propriedade',
            'area_total' => 'Área Total (ha)',
            'endereco' => 'Endereço',
            'cidade' => 'Cidade',
            'estado' => 'Estado',
            'cep' => 'CEP',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'status' => 'Status',
            'observacoes' => 'Observações'
        ]
    ],
    'insumos' => [
        'nome' => 'Insumos',
        'campos' => [
            'id' => 'ID',
            'nome' => 'Nome do Insumo',
            'descricao' => 'Descrição',
            'quantidade_estoque' => 'Quantidade em Estoque',
            'unidade_medida' => 'Unidade de Medida',
            'estoque_minimo' => 'Estoque Mínimo',
            'data_validade' => 'Data de Validade',
            'lote' => 'Lote',
            'status' => 'Status',
            'observacoes' => 'Observações'
        ]
    ],
    'financeiro' => [
        'nome' => 'Financeiro',
        'campos' => [
            'id' => 'ID',
            'descricao' => 'Descrição',
            'tipo' => 'Tipo (Receita/Despesa)',
            'valor' => 'Valor',
            'data_vencimento' => 'Data de Vencimento',
            'data_pagamento' => 'Data de Pagamento',
            'status' => 'Status',
            'categoria' => 'Categoria',
            'forma_pagamento' => 'Forma de Pagamento',
            'observacoes' => 'Observações'
        ]
    ],
    'usuarios' => [
        'nome' => 'Usuários',
        'campos' => [
            'id' => 'ID',
            'nome' => 'Nome',
            'email' => 'E-mail',
            'nivel_acesso' => 'Nível de Acesso',
            'telefone' => 'Telefone',
            'data_cadastro' => 'Data de Cadastro',
            'ultimo_acesso' => 'Último Acesso',
            'status' => 'Status'
        ]
    ]
];

// Opções de ordenação
$opcoesOrdenacao = [
    'asc' => 'Crescente (A-Z, 0-9)',
    'desc' => 'Decrescente (Z-A, 9-0)',
    'data_asc' => 'Data (mais antigo primeiro)',
    'data_desc' => 'Data (mais recente primeiro)',
    'valor_asc' => 'Valor (menor para maior)',
    'valor_desc' => 'Valor (maior para menor)'
];
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted">Crie um relatório personalizado selecionando os campos desejados.</p>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="formRelatorio" method="post" action="<?php echo SITE_URL; ?>/api/relatorios_controller.php" target="_blank">
                <input type="hidden" name="tipo" value="personalizado">
                
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-funnel"></i> Filtros</h5>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="data_inicio" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="data_fim" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos os status</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="pendente">Pendente</option>
                            <option value="concluido">Concluído</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-table"></i> Tabelas e Campos</h5>
                        <p class="text-muted">Selecione as tabelas e os campos que deseja incluir no relatório.</p>
                        
                        <div class="row">
                            <?php foreach ($tabelasCampos as $tabela => $dados): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <div class="form-check">
                                                <input class="form-check-input tabela-checkbox" type="checkbox" value="" 
                                                       id="tabela_<?php echo $tabela; ?>" data-tabela="<?php echo $tabela; ?>">
                                                <label class="form-check-label fw-bold" for="tabela_<?php echo $tabela; ?>">
                                                    <?php echo htmlspecialchars($dados['nome']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input selecionar-todos" type="checkbox" 
                                                       id="selecionar_todos_<?php echo $tabela; ?>">
                                                <label class="form-check-label fst-italic" for="selecionar_todos_<?php echo $tabela; ?>">
                                                    Selecionar todos
                                                </label>
                                            </div>
                                            <hr class="my-2">
                                            <div class="campos-container" id="campos_<?php echo $tabela; ?>">
                                                <?php foreach ($dados['campos'] as $campo => $rotulo): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input campo-checkbox" type="checkbox" 
                                                               name="campos[<?php echo $tabela; ?>][]" 
                                                               value="<?php echo $campo; ?>" 
                                                               id="campo_<?php echo $tabela; ?>_<?php echo $campo; ?>">
                                                        <label class="form-check-label" for="campo_<?php echo $tabela; ?>_<?php echo $campo; ?>">
                                                            <?php echo htmlspecialchars($rotulo); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-sort-down"></i> Ordenação</h5>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="ordenar_por" class="form-label">Ordenar por</label>
                        <select class="form-select" id="ordenar_por" name="ordenar_por">
                            <option value="">Selecione um campo para ordenação</option>
                            <?php foreach ($tabelasCampos as $tabela => $dados): ?>
                                <optgroup label="<?php echo htmlspecialchars($dados['nome']); ?>">
                                    <?php foreach ($dados['campos'] as $campo => $rotulo): ?>
                                        <option value="<?php echo $tabela . '.' . $campo; ?>">
                                            <?php echo htmlspecialchars($rotulo); ?> (<?php echo htmlspecialchars($dados['nome']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="tipo_ordenacao" class="form-label">Tipo de Ordenação</label>
                        <select class="form-select" id="tipo_ordenacao" name="tipo_ordenacao">
                            <?php foreach ($opcoesOrdenacao as $valor => $texto): ?>
                                <option value="<?php echo $valor; ?>">
                                    <?php echo htmlspecialchars($texto); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-gear"></i> Configurações</h5>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="limite_registros" class="form-label">Limite de Registros</label>
                        <input type="number" class="form-control" id="limite_registros" name="limite_registros" min="1" max="1000" value="100">
                        <div class="form-text">Número máximo de registros a serem exibidos (máx. 1000)</div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="agrupar_por" class="form-label">Agrupar por</label>
                        <select class="form-select" id="agrupar_por" name="agrupar_por">
                            <option value="">Não agrupar</option>
                            <?php foreach ($tabelasCampos as $tabela => $dados): ?>
                                <optgroup label="<?php echo htmlspecialchars($dados['nome']); ?>">
                                    <?php foreach ($dados['campos'] as $campo => $rotulo): ?>
                                        <option value="<?php echo $tabela . '.' . $campo; ?>">
                                            <?php echo htmlspecialchars($rotulo); ?> (<?php echo htmlspecialchars($dados['nome']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="formato" class="form-label">Formato de Saída</label>
                        <select class="form-select" id="formato" name="formato">
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel (XLSX)</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="incluir_totais" name="incluir_totais" checked>
                            <label class="form-check-label" for="incluir_totais">
                                Incluir totais e subtotais
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="incluir_graficos" name="incluir_graficos" checked>
                            <label class="form-check-label" for="incluir_graficos">
                                Incluir gráficos (quando aplicável)
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="limparFiltros()">
                                <i class="bi bi-arrow-counterclockwise"></i> Limpar Tudo
                            </button>
                            
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" id="btnVisualizar">
                                    <i class="bi bi-eye"></i> Visualizar
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-text"></i> Gerar Relatório
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Visualização -->
    <div class="modal fade" id="modalVisualizacao" tabindex="-1" aria-labelledby="modalVisualizacaoLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisualizacaoLabel">Pré-visualização do Relatório</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-5" id="loadingPreview">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Gerando pré-visualização...</p>
                    </div>
                    <div id="previewContent" style="display: none;">
                        <!-- Conteúdo será carregado via AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnGerarAposVisualizar">
                        <i class="bi bi-file-earmark-text"></i> Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar datas padrão (mês atual)
    const hoje = new Date();
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    document.getElementById('data_inicio').valueAsDate = primeiroDiaMes;
    document.getElementById('data_fim').valueAsDate = ultimoDiaMes;
    
    // Gerenciar seleção/deseleção de todos os campos de uma tabela
    document.querySelectorAll('.tabela-checkbox').forEach(checkbox => {
        const tabela = checkbox.dataset.tabela;
        const container = document.getElementById(`campos_${tabela}`);
        const checkboxes = container.querySelectorAll('.campo-checkbox');
        
        checkbox.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(cb => {
                cb.checked = isChecked;
                cb.dispatchEvent(new Event('change'));
            });
        });
        
        // Atualizar o estado do checkbox da tabela quando os campos forem alterados
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const todosSelecionados = Array.from(checkboxes).every(c => c.checked);
                const algumSelecionado = Array.from(checkboxes).some(c => c.checked);
                
                if (todosSelecionados) {
                    checkbox.checked = true;
                    checkbox.indeterminate = false;
                } else if (algumSelecionado) {
                    checkbox.checked = false;
                    checkbox.indeterminate = true;
                } else {
                    checkbox.checked = false;
                    checkbox.indeterminate = false;
                }
            });
        });
    });
    
    // Gerenciar seleção/deseleção de todos os campos de uma tabela específica
    document.querySelectorAll('.selecionar-todos').forEach(checkbox => {
        const tabelaId = checkbox.id.replace('selecionar_todos_', '');
        const container = document.getElementById(`campos_${tabelaId}`);
        const checkboxes = container.querySelectorAll('.campo-checkbox');
        
        checkbox.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(cb => {
                cb.checked = isChecked;
                cb.dispatchEvent(new Event('change'));
            });
        });
    });
    
    // Validar datas
    const dataInicio = document.getElementById('data_inicio');
    const dataFim = document.getElementById('data_fim');
    
    dataInicio.addEventListener('change', function() {
        if (dataFim.value && this.value > dataFim.value) {
            dataFim.value = this.value;
        }
        dataFim.min = this.value;
    });
    
    dataFim.addEventListener('change', function() {
        if (dataInicio.value && this.value < dataInicio.value) {
            this.value = dataInicio.value;
        }
    });
    
    // Visualizar relatório
    document.getElementById('btnVisualizar').addEventListener('click', function() {
        // Validar se pelo menos um campo foi selecionado
        const camposSelecionados = document.querySelectorAll('.campo-checkbox:checked');
        if (camposSelecionados.length === 0) {
            alert('Selecione pelo menos um campo para gerar o relatório.');
            return;
        }
        
        // Exibir modal de carregamento
        const modal = new bootstrap.Modal(document.getElementById('modalVisualizacao'));
        modal.show();
        
        // Mostrar loading e esconder conteúdo anterior
        document.getElementById('loadingPreview').style.display = 'block';
        document.getElementById('previewContent').style.display = 'none';
        
        // Simular carregamento (substituir por chamada AJAX real)
        setTimeout(() => {
            // Aqui você faria uma chamada AJAX para obter os dados da pré-visualização
            // Por enquanto, vamos apenas exibir uma mensagem de exemplo
            const previewContent = document.getElementById('previewContent');
            previewContent.innerHTML = `
                <div class="alert alert-info">
                    <h5>Pré-visualização do Relatório</h5>
                    <p>Esta é uma prévia estática. A versão final do relatório incluirá todos os dados conforme os filtros selecionados.</p>
                    <hr>
                    <p><strong>Configuração atual:</strong></p>
                    <ul>
                        <li><strong>Período:</strong> ${dataInicio.value} a ${dataFim.value}</li>
                        <li><strong>Campos selecionados:</strong> ${camposSelecionados.length}</li>
                        <li><strong>Ordenação:</strong> ${document.getElementById('ordenar_por').options[document.getElementById('ordenar_por').selectedIndex].text} (${document.getElementById('tipo_ordenacao').options[document.getElementById('tipo_ordenacao').selectedIndex].text})</li>
                        <li><strong>Limite de registros:</strong> ${document.getElementById('limite_registros').value}</li>
                    </ul>
                    <p class="mb-0">Clique em "Gerar Relatório" para baixar o relatório completo.</p>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered">
                        <thead class="table-light">
                            <tr>
                                ${Array.from(camposSelecionados).map(campo => 
                                    `<th>${campo.labels[0].textContent}</th>`
                                ).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                ${Array(camposSelecionados.length).fill(0).map(() => 
                                    `<td>Dado de exemplo</td>`
                                ).join('')}
                            </tr>
                            <tr>
                                ${Array(camposSelecionados.length).fill(0).map(() => 
                                    `<td>Outro dado</td>`
                                ).join('')}
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;
            
            // Esconder loading e mostrar conteúdo
            document.getElementById('loadingPreview').style.display = 'none';
            previewContent.style.display = 'block';
            
        }, 1500);
    });
    
    // Botão de gerar após visualizar
    document.getElementById('btnGerarAposVisualizar').addEventListener('click', function() {
        // Fechar o modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalVisualizacao'));
        modal.hide();
        
        // Submeter o formulário
        document.querySelector('form').submit();
    });
});

// Função para limpar todos os filtros e seleções
function limparFiltros() {
    // Limpar datas
    const hoje = new Date();
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    document.getElementById('data_inicio').valueAsDate = primeiroDiaMes;
    document.getElementById('data_fim').valueAsDate = ultimoDiaMes;
    
    // Limpar seleções
    document.getElementById('status').value = '';
    document.getElementById('ordenar_por').value = '';
    document.getElementById('tipo_ordenacao').value = 'asc';
    document.getElementById('limite_registros').value = '100';
    document.getElementById('agrupar_por').value = '';
    document.getElementById('formato').value = 'pdf';
    document.getElementById('incluir_totais').checked = true;
    document.getElementById('incluir_graficos').checked = true;
    
    // Desmarcar todos os checkboxes de campos
    document.querySelectorAll('.campo-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Atualizar checkboxes de tabela
    document.querySelectorAll('.tabela-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.indeterminate = false;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
