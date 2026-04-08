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
$pageTitle = 'Relatório de Propriedades';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

try {
    // Conectar ao banco
    $db = Database::getInstance()->getConnection();
    
    // Buscar municípios
    $stmt = $db->query("SELECT id, nome FROM municipios ORDER BY nome");
    $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
            <p class="text-muted">Preencha os filtros abaixo para gerar o relatório de propriedades.</p>
        </div>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="formRelatorio" method="post" action="<?php echo SITE_URL; ?>/api/relatorios_controller.php" target="_blank">
                <input type="hidden" name="tipo" value="propriedades">
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="municipio_id" class="form-label">Município</label>
                        <select class="form-select" id="municipio_id" name="municipio_id">
                            <option value="">Todos os municípios</option>
                            <?php foreach ($municipios as $municipio): ?>
                                <option value="<?php echo $municipio['id']; ?>">
                                    <?php echo htmlspecialchars($municipio['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($user->isAdmin()): ?>
                    <div class="col-md-4">
                        <label for="responsavel_id" class="form-label">Responsável</label>
                        <select class="form-select" id="responsavel_id" name="responsavel_id">
                            <option value="">Todos os responsáveis</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>">
                                    <?php echo htmlspecialchars($usuario['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="responsavel_id" value="<?php echo $user->getId(); ?>">
                    <?php endif; ?>
                    
                    <div class="col-md-4">
                        <label for="ordenacao" class="form-label">Ordenar por</label>
                        <select class="form-select" id="ordenacao" name="ordenacao">
                            <option value="nome_asc">Nome (A-Z)</option>
                            <option value="nome_desc">Nome (Z-A)</option>
                            <option value="area_asc">Área (menor para maior)</option>
                            <option value="area_desc">Área (maior para menor)</option>
                            <option value="municipio_asc">Município (A-Z)</option>
                            <option value="municipio_desc">Município (Z-A)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="status_ativo" name="status[]" value="Ativo" checked>
                            <label class="form-check-label" for="status_ativo">Ativo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="status_inativo" name="status[]" value="Inativo" checked>
                            <label class="form-check-label" for="status_inativo">Inativo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="status_suspenso" name="status[]" value="Suspenso" checked>
                            <label class="form-check-label" for="status_suspenso">Suspenso</label>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Propriedade</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tipo_rural" name="tipo[]" value="Rural" checked>
                            <label class="form-check-label" for="tipo_rural">Rural</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tipo_urbana" name="tipo[]" value="Urbana" checked>
                            <label class="form-check-label" for="tipo_urbana">Urbana</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tipo_mista" name="tipo[]" value="Mista" checked>
                            <label class="form-check-label" for="tipo_mista">Mista</label>
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
            <p>Utilize os filtros acima para personalizar o relatório de propriedades de acordo com suas necessidades:</p>
            <ul>
                <li><strong>Município:</strong> Selecione um município específico para filtrar as propriedades.</li>
                <?php if ($user->isAdmin()): ?>
                <li><strong>Responsável:</strong> Selecione um responsável específico.</li>
                <?php endif; ?>
                <li><strong>Status:</strong> Selecione os status de propriedades que deseja incluir no relatório.</li>
                <li><strong>Tipo de Propriedade:</strong> Selecione os tipos de propriedade que deseja incluir.</li>
                <li><strong>Ordenação:</strong> Defina a ordem de exibição dos resultados.</li>
            </ul>
            <p class="mb-0">Após configurar os filtros, escolha o formato de saída (PDF ou Excel) e clique em "Gerar Relatório".</p>
        </div>
    </div>
</div>

<script>
// Limpar todos os filtros
document.addEventListener('DOMContentLoaded', function() {
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
        
        // Processar checkboxes de status
        const status = [];
        document.querySelectorAll('input[name="status[]"]:checked').forEach(checkbox => {
            status.push(checkbox.value);
        });
        data['status'] = status;
        
        // Processar checkboxes de tipo
        const tipos = [];
        document.querySelectorAll('input[name="tipo[]"]:checked').forEach(checkbox => {
            tipos.push(checkbox.value);
        });
        data['tipo'] = tipos;
        
        // Adicionar outros campos
        formData.forEach((value, key) => {
            if (key !== 'status[]' && key !== 'tipo[]') {
                data[key] = value;
            }
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
            a.download = data.formato === 'pdf' ? 'relatorio_propriedades.pdf' : 'relatorio_propriedades.xlsx';
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
    // Limpar seleções
    document.getElementById('municipio_id').value = '';
    document.getElementById('ordenacao').value = 'nome_asc';
    
    // Marcar todos os status
    document.querySelectorAll('input[name="status[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
    
    // Marcar todos os tipos
    document.querySelectorAll('input[name="tipo[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
    
    // Marcar PDF como padrão
    document.getElementById('formato_pdf').checked = true;
    
    <?php if ($user->isAdmin()): ?>
    document.getElementById('responsavel_id').value = '';
    <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
