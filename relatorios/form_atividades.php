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
$pageTitle = 'Relatório de Atividades';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

try {
    // Conectar ao banco
    $db = Database::getInstance()->getConnection();
    
    // Buscar categorias
    $stmt = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar propriedades
    $sql = "SELECT id, nome FROM propriedades ORDER BY nome";
    if (!$user->isAdmin()) {
        $sql = "SELECT p.id, p.nome 
                FROM propriedades p
                INNER JOIN usuarios u ON p.responsavel_id = u.id
                WHERE u.id = :user_id
                ORDER BY p.nome";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user->getId()]);
    } else {
        $stmt = $db->query($sql);
    }
    $propriedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar usuários (apenas para administradores)
    $usuarios = [];
    if ($user->isAdmin()) {
        $stmt = $db->query("SELECT id, nome FROM usuarios ORDER BY nome");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error = "Erro ao carregar dados: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted">Preencha os filtros abaixo para gerar o relatório de atividades.</p>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="formRelatorio" method="post" action="<?php echo SITE_URL; ?>/api/relatorios_controller.php" target="_blank">
                <input type="hidden" name="tipo" value="atividades">
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                               value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Concluído">Concluído</option>
                            <option value="Cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="propriedade_id" class="form-label">Propriedade</label>
                        <select class="form-select" id="propriedade_id" name="propriedade_id">
                            <option value="">Todas as propriedades</option>
                            <?php foreach ($propriedades as $propriedade): ?>
                                <option value="<?php echo $propriedade['id']; ?>">
                                    <?php echo htmlspecialchars($propriedade['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($user->isAdmin()): ?>
                    <div class="col-md-4">
                        <label for="usuario_id" class="form-label">Responsável</label>
                        <select class="form-select" id="usuario_id" name="usuario_id">
                            <option value="">Todos os responsáveis</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>">
                                    <?php echo htmlspecialchars($usuario['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="usuario_id" value="<?php echo $user->getId(); ?>">
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label for="ordenacao" class="form-label">Ordenar por</label>
                        <select class="form-select" id="ordenacao" name="ordenacao">
                            <option value="data_atividade_desc">Data (mais recente primeiro)</option>
                            <option value="data_atividade_asc">Data (mais antigo primeiro)</option>
                            <option value="propriedade_asc">Propriedade (A-Z)</option>
                            <option value="propriedade_desc">Propriedade (Z-A)</option>
                            <option value="status_asc">Status (A-Z)</option>
                            <option value="status_desc">Status (Z-A)</option>
                        </select>
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
            <p>Utilize os filtros acima para personalizar o relatório de acordo com suas necessidades:</p>
            <ul>
                <li><strong>Período:</strong> Selecione o intervalo de datas para o relatório.</li>
                <li><strong>Status:</strong> Filtre por atividades pendentes, em andamento, concluídas ou canceladas.</li>
                <li><strong>Categoria:</strong> Selecione uma categoria específica de atividades.</li>
                <li><strong>Propriedade:</strong> Filtre por propriedade específica.</li>
                <?php if ($user->isAdmin()): ?>
                <li><strong>Responsável:</strong> Selecione um usuário responsável específico.</li>
                <?php endif; ?>
                <li><strong>Ordenação:</strong> Defina a ordem de exibição dos resultados.</li>
            </ul>
            <p class="mb-0">Após configurar os filtros, escolha o formato de saída (PDF ou Excel) e clique em "Gerar Relatório".</p>
        </div>
    </div>
</div>

<script>
// Limpar todos os filtros
document.addEventListener('DOMContentLoaded', function() {
    // Desabilitar datas futuras
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('data_fim').max = today;
    
    // Validar data inicial <= data final
    document.getElementById('data_inicio').addEventListener('change', function() {
        document.getElementById('data_fim').min = this.value;
    });
    
    // Se data final for menor que a inicial, ajustar
    document.getElementById('data_fim').addEventListener('change', function() {
        var dataInicio = document.getElementById('data_inicio').value;
        if (this.value < dataInicio) {
            this.value = dataInicio;
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
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
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
            a.download = data.formato === 'pdf' ? 'relatorio_atividades.pdf' : 'relatorio_atividades.xlsx';
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
    // Redefinir valores padrão
    document.getElementById('data_inicio').value = '<?php echo date('Y-m-01'); ?>';
    document.getElementById('data_fim').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('status').value = '';
    document.getElementById('categoria_id').value = '';
    document.getElementById('propriedade_id').value = '';
    document.getElementById('ordenacao').value = 'data_atividade_desc';
    
    <?php if ($user->isAdmin()): ?>
    document.getElementById('usuario_id').value = '';
    <?php endif; ?>
    
    // Marcar PDF como padrão
    document.getElementById('formato_pdf').checked = true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
