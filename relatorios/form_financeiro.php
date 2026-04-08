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
$pageTitle = 'Relatório Financeiro';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

try {
    // Conectar ao banco
    $db = Database::getInstance()->getConnection();
    
    // Buscar categorias de receitas
    $stmt = $db->query("SELECT id, nome FROM categorias_financeiro WHERE tipo = 'receita' ORDER BY nome");
    $categoriasReceita = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar categorias de despesas
    $stmt = $db->query("SELECT id, nome FROM categorias_financeiro WHERE tipo = 'despesa' ORDER BY nome");
    $categoriasDespesa = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar contas
    $stmt = $db->query("SELECT id, nome, tipo FROM contas ORDER BY tipo, nome");
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar contas por tipo
    $contasPorTipo = [
        'conta_corrente' => [],
        'poupanca' => [],
        'investimento' => [],
        'outros' => []
    ];
    
    foreach ($contas as $conta) {
        $tipo = $conta['tipo'] ?? 'outros';
        unset($conta['tipo']);
        $contasPorTipo[$tipo][] = $conta;
    }
    
    // Buscar formas de pagamento
    $formasPagamento = [
        'dinheiro' => 'Dinheiro',
        'cartao_credito' => 'Cartão de Crédito',
        'cartao_debito' => 'Cartão de Débito',
        'transferencia' => 'Transferência Bancária',
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'cheque' => 'Cheque',
        'outro' => 'Outro'
    ];
    
} catch (PDOException $e) {
    $error = "Erro ao carregar dados: " . $e->getMessage();
}

// Definir período padrão (mês atual)
$dataInicio = date('Y-m-01'); // Primeiro dia do mês atual
$dataFim = date('Y-m-t');     // Último dia do mês atual
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted">Preencha os filtros abaixo para gerar o relatório financeiro.</p>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="formRelatorio" method="post" action="<?php echo SITE_URL; ?>/api/relatorios_controller.php" target="_blank">
                <input type="hidden" name="tipo" value="financeiro">
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                               value="<?php echo $dataInicio; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" 
                               value="<?php echo $dataFim; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="receita">Receitas</option>
                            <option value="despesa">Despesas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago" selected>Pago/Recebido</option>
                            <option value="vencido">Vencido</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id">
                            <option value="">Todas as categorias</option>
                            <optgroup label="Receitas">
                                <?php foreach ($categoriasReceita as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>">
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Despesas">
                                <?php foreach ($categoriasDespesa as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>">
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="conta_id" class="form-label">Conta</label>
                        <select class="form-select" id="conta_id" name="conta_id">
                            <option value="">Todas as contas</option>
                            <optgroup label="Conta Corrente">
                                <?php foreach ($contasPorTipo['conta_corrente'] as $conta): ?>
                                    <option value="<?php echo $conta['id']; ?>">
                                        <?php echo htmlspecialchars($conta['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Poupança">
                                <?php foreach ($contasPorTipo['poupanca'] as $conta): ?>
                                    <option value="<?php echo $conta['id']; ?>">
                                        <?php echo htmlspecialchars($conta['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Investimento">
                                <?php foreach ($contasPorTipo['investimento'] as $conta): ?>
                                    <option value="<?php echo $conta['id']; ?>">
                                        <?php echo htmlspecialchars($conta['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Outros">
                                <?php foreach ($contasPorTipo['outros'] as $conta): ?>
                                    <option value="<?php echo $conta['id']; ?>">
                                        <?php echo htmlspecialchars($conta['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                            <option value="">Todas as formas</option>
                            <?php foreach ($formasPagamento as $valor => $rotulo): ?>
                                <option value="<?php echo $valor; ?>">
                                    <?php echo htmlspecialchars($rotulo); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="valor_min" class="form-label">Valor Mínimo</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valor_min" name="valor_min" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="valor_max" class="form-label">Valor Máximo</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valor_max" name="valor_max" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="agrupar_por_categoria" name="agrupar_por_categoria" checked>
                            <label class="form-check-label" for="agrupar_por_categoria">
                                Agrupar por categoria
                            </label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="incluir_subcategorias" name="incluir_subcategorias" checked>
                            <label class="form-check-label" for="incluir_subcategorias">
                                Incluir subcategorias
                            </label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="mostrar_detalhado" name="mostrar_detalhado">
                            <label class="form-check-label" for="mostrar_detalhado">
                                Mostrar lançamentos detalhados
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="formato" id="formato_pdf" value="pdf" checked>
                            <label class="form-check-label" for="formato_pdf">
                                <i class="bi bi-file-pdf"></i> Gerar PDF
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="formato" id="formato_excel" value="excel">
                            <label class="form-check-label" for="formato_excel">
                                <i class="bi bi-file-excel"></i> Exportar para Excel
                            </label>
                        </div>
                        
                        <div class="float-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="limparFiltros()">
                                <i class="bi bi-arrow-counterclockwise"></i> Limpar Filtros
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-file-earmark-text"></i> Gerar Relatório
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-info-circle"></i> Instruções
            </h5>
        </div>
        <div class="card-body">
            <p>Utilize os filtros acima para personalizar o relatório financeiro de acordo com suas necessidades:</p>
            <ul>
                <li><strong>Período:</strong> Selecione o intervalo de datas para o relatório.</li>
                <li><strong>Tipo:</strong> Filtre por receitas, despesas ou ambos.</li>
                <li><strong>Status:</strong> Filtre por status dos lançamentos (pendente, pago/recebido, vencido, cancelado).</li>
                <li><strong>Categoria:</strong> Selecione uma categoria específica de receita ou despesa.</li>
                <li><strong>Conta:</strong> Filtre por conta específica.</li>
                <li><strong>Forma de Pagamento:</strong> Filtre por forma de pagamento específica.</li>
                <li><strong>Valor:</strong> Defina um intervalo de valores para filtrar os lançamentos.</li>
                <li><strong>Opções de Exibição:</strong>
                    <ul>
                        <li><strong>Agrupar por categoria:</strong> Agrupa os resultados por categoria para melhor visualização.</li>
                        <li><strong>Incluir subcategorias:</strong> Inclui as subcategorias no agrupamento.</li>
                        <li><strong>Mostrar lançamentos detalhados:</strong> Exibe cada lançamento individualmente no relatório.</li>
                    </ul>
                </li>
            </ul>
            <p class="mb-0">Após configurar os filtros, escolha o formato de saída (PDF ou Excel) e clique em "Gerar Relatório".</p>
        </div>
    </div>
</div>

<script>
// Limpar todos os filtros
document.addEventListener('DOMContentLoaded', function() {
    // Configurar datas
    const dataInicio = document.getElementById('data_inicio');
    const dataFim = document.getElementById('data_fim');
    
    // Validar datas
    dataInicio.addEventListener('change', function() {
        dataFim.min = this.value;
        if (dataFim.value < this.value) {
            dataFim.value = this.value;
        }
    });
    
    dataFim.addEventListener('change', function() {
        if (this.value < dataInicio.value) {
            this.value = dataInicio.value;
        }
    });
    
    // Configurar envio do formulário via AJAX
    document.getElementById('formRelatorio').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Mostrar loading
        const btnSubmit = this.querySelector('button[type="submit"]');
        const btnOriginalText = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Gerando...';
        
        // Obter dados do formulário
        const formData = new FormData(this);
        const data = {};
        
        // Adicionar apenas os campos preenchidos
        for (const [key, value] of formData.entries()) {
            if (value !== '') {
                data[key] = value;
            }
        }
        
        // Adicionar opções de exibição
        data['agrupar_por_categoria'] = document.getElementById('agrupar_por_categoria').checked ? '1' : '0';
        data['incluir_subcategorias'] = document.getElementById('incluir_subcategorias').checked ? '1' : '0';
        data['mostrar_detalhado'] = document.getElementById('mostrar_detalhado').checked ? '1' : '0';
        
        // Enviar requisição
        fetch('<?php echo SITE_URL; ?>/api/relatorios_controller.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.blob();
        })
        .then(blob => {
            // Criar link para download
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = data.formato === 'pdf' ? 'relatorio_financeiro.pdf' : 'relatorio_financeiro.xlsx';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao gerar relatório: ' + (error.message || 'Erro desconhecido'));
        })
        .finally(() => {
            // Restaurar botão
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = btnOriginalText;
        });
    });
});

// Função para limpar todos os filtros
function limparFiltros() {
    // Limpar datas
    const hoje = new Date();
    const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    
    document.getElementById('data_inicio').value = formatDate(primeiroDiaMes);
    document.getElementById('data_fim').value = formatDate(ultimoDiaMes);
    
    // Limpar seleções
    document.getElementById('tipo').value = '';
    document.getElementById('status').value = 'pago';
    document.getElementById('categoria_id').value = '';
    document.getElementById('conta_id').value = '';
    document.getElementById('forma_pagamento').value = '';
    document.getElementById('valor_min').value = '';
    document.getElementById('valor_max').value = '';
    
    // Resetar checkboxes
    document.getElementById('agrupar_por_categoria').checked = true;
    document.getElementById('incluir_subcategorias').checked = true;
    document.getElementById('mostrar_detalhado').checked = false;
    
    // Marcar PDF como padrão
    document.getElementById('formato_pdf').checked = true;
}

// Função auxiliar para formatar data como YYYY-MM-DD
function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
