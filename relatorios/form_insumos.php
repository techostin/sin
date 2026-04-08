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
$pageTitle = 'Relatório de Insumos';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

try {
    // Conectar ao banco
    $db = Database::getInstance()->getConnection();
    
    // Buscar categorias de insumos
    $stmt = $db->query("SELECT id, nome FROM categorias_insumos ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar fornecedores
    $stmt = $db->query("SELECT id, nome FROM fornecedores ORDER BY nome");
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar unidades de medida
    $unidades = [
        'kg' => 'Quilograma (kg)',
        'g' => 'Grama (g)',
        'l' => 'Litro (l)',
        'ml' => 'Mililitro (ml)',
        'un' => 'Unidade (un)',
        'cx' => 'Caixa (cx)',
        'sc' => 'Saco (sc)',
        'pct' => 'Pacote (pct)'
    ];
    
} catch (PDOException $e) {
    $error = "Erro ao carregar dados: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted">Preencha os filtros abaixo para gerar o relatório de insumos.</p>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="formRelatorio" method="post" action="<?php echo SITE_URL; ?>/api/relatorios_controller.php" target="_blank">
                <input type="hidden" name="tipo" value="insumos">
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>">
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="fornecedor_id" class="form-label">Fornecedor</label>
                        <select class="form-select" id="fornecedor_id" name="fornecedor_id">
                            <option value="">Todos os fornecedores</option>
                            <?php foreach ($fornecedores as $fornecedor): ?>
                                <option value="<?php echo $fornecedor['id']; ?>">
                                    <?php echo htmlspecialchars($fornecedor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
                        <select class="form-select" id="unidade_medida" name="unidade_medida">
                            <option value="">Todas as unidades</option>
                            <?php foreach ($unidades as $sigla => $descricao): ?>
                                <option value="<?php echo $sigla; ?>">
                                    <?php echo htmlspecialchars($descricao); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="data_validade_inicio" class="form-label">Validade - De</label>
                        <input type="date" class="form-control" id="data_validade_inicio" name="data_validade_inicio">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="data_validade_fim" class="form-label">Validade - Até</label>
                        <input type="date" class="form-control" id="data_validade_fim" name="data_validade_fim">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="ordenacao" class="form-label">Ordenar por</label>
                        <select class="form-select" id="ordenacao" name="ordenacao">
                            <option value="nome_asc">Nome (A-Z)</option>
                            <option value="nome_desc">Nome (Z-A)</option>
                            <option value="estoque_asc">Estoque (menor para maior)</option>
                            <option value="estoque_desc">Estoque (maior para menor)</option>
                            <option value="validade_asc">Validade (mais próximo)</option>
                            <option value="validade_desc">Validade (mais distante)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="estoque_baixo" name="estoque_baixo">
                            <label class="form-check-label" for="estoque_baixo">
                                Apenas itens com estoque baixo
                            </label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="proximo_vencer" name="proximo_vencer">
                            <label class="form-check-label" for="proximo_vencer">
                                Apenas itens próximos ao vencimento (30 dias)
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
            <p>Utilize os filtros acima para personalizar o relatório de insumos de acordo com suas necessidades:</p>
            <ul>
                <li><strong>Categoria:</strong> Filtre por categoria específica de insumos.</li>
                <li><strong>Fornecedor:</strong> Selecione um fornecedor específico.</li>
                <li><strong>Unidade de Medida:</strong> Filtre por unidade de medida específica.</li>
                <li><strong>Validade:</strong> Defina um intervalo de datas para filtrar insumos por data de validade.</li>
                <li><strong>Filtros Especiais:</strong> 
                    <ul>
                        <li><strong>Estoque Baixo:</strong> Mostrar apenas itens com quantidade abaixo do mínimo recomendado.</li>
                        <li><strong>Próximos ao Vencimento:</strong> Mostrar apenas itens que vencem nos próximos 30 dias.</li>
                    </ul>
                </li>
                <li><strong>Ordenação:</strong> Defina a ordem de exibição dos resultados.</li>
            </ul>
            <p class="mb-0">Após configurar os filtros, escolha o formato de saída (PDF ou Excel) e clique em "Gerar Relatório".</p>
        </div>
    </div>
</div>

<script>
// Limpar todos os filtros
document.addEventListener('DOMContentLoaded', function() {
    // Configurar datas padrão
    const hoje = new Date();
    const dataFim = new Date();
    dataFim.setMonth(dataFim.getMonth() + 3); // 3 meses à frente
    
    document.getElementById('data_validade_inicio').valueAsDate = hoje;
    document.getElementById('data_validade_fim').valueAsDate = dataFim;
    
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
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        // Adicionar filtros de data de validade
        const dataInicio = document.getElementById('data_validade_inicio').value;
        const dataFim = document.getElementById('data_validade_fim').value;
        
        if (dataInicio) {
            data['data_validade_inicio'] = dataInicio;
        }
        
        if (dataFim) {
            data['data_validade_fim'] = dataFim;
        }
        
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
            a.download = data.formato === 'pdf' ? 'relatorio_insumos.pdf' : 'relatorio_insumos.xlsx';
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
    
    // Validar datas
    document.getElementById('data_validade_inicio').addEventListener('change', function() {
        const dataFim = document.getElementById('data_validade_fim');
        if (this.value > dataFim.value) {
            dataFim.value = this.value;
        }
        dataFim.min = this.value;
    });
    
    document.getElementById('data_validade_fim').addEventListener('change', function() {
        const dataInicio = document.getElementById('data_validade_inicio');
        if (this.value < dataInicio.value) {
            this.value = dataInicio.value;
        }
    });
});

// Função para limpar todos os filtros
function limparFiltros() {
    // Limpar seleções
    document.getElementById('categoria_id').value = '';
    document.getElementById('fornecedor_id').value = '';
    document.getElementById('unidade_medida').value = '';
    document.getElementById('ordenacao').value = 'nome_asc';
    
    // Limpar datas
    const hoje = new Date();
    const dataFim = new Date();
    dataFim.setMonth(dataFim.getMonth() + 3); // 3 meses à frente
    
    document.getElementById('data_validade_inicio').valueAsDate = hoje;
    document.getElementById('data_validade_fim').valueAsDate = dataFim;
    
    // Desmarcar checkboxes
    document.getElementById('estoque_baixo').checked = false;
    document.getElementById('proximo_vencer').checked = false;
    
    // Marcar PDF como padrão
    document.getElementById('formato_pdf').checked = true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
