<?php
$page_title = isset($_GET['id']) ? "Editar Certificação" : "Nova Certificação";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$certificacaoId = $isEdit ? $_GET['id'] : null;

// Se for edição, buscar dados existentes
$certificacao = null;
if ($isEdit) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM certificacao_participativa WHERE id = ?");
    $stmt->execute([$certificacaoId]);
    $certificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificacao) {
        echo "<div class='alert alert-danger'>Certificação não encontrada.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
}

// Buscar associados para o select
$db = Database::getInstance()->getConnection();
$stmtAssociados = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome");
$associados = $stmtAssociados->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mt-4"><?php echo $page_title; ?></h1>
<p>Registro de certificação orgânica - Portaria MAPA 52/2021</p>

<div class="card">
    <div class="card-header">
        <span><?php echo $isEdit ? 'Editar Certificação' : 'Cadastrar Nova Certificação'; ?></span>
    </div>
    <div class="card-body">
        <form id="formCertificacao">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $certificacaoId; ?>">
            <?php endif; ?>
            
            <!-- Informações Básicas -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="associado_id" class="form-label">Associado <span class="text-danger">*</span></label>
                    <select class="form-select" id="associado_id" name="associado_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $assoc): ?>
                            <option value="<?php echo $assoc['id']; ?>" 
                                    <?php echo ($certificacao && $certificacao['associado_id'] == $assoc['id']) ? 'selected' : ''; ?>>
                                <?php echo $assoc['nome'] . ' ' . $assoc['sobrenome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Agricultor titular da certificação</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="tipo_certificacao" class="form-label">Tipo de Certificação <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo_certificacao" name="tipo_certificacao" required>
                        <option value="SPG" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'SPG') ? 'selected' : ''; ?>>SPG (Sistema Participativo de Garantia)</option>
                        <option value="OCS" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'OCS') ? 'selected' : ''; ?>>OCS (Organização de Controle Social)</option>
                        <option value="Rede Ecovida" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'Rede Ecovida') ? 'selected' : ''; ?>>Rede Ecovida</option>
                        <option value="Outro" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                    </select>
                </div>
            </div>

            <!-- Dados da Certificação -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="numero_certificado" class="form-label">Nº do Certificado</label>
                    <input type="text" class="form-control" id="numero_certificado" name="numero_certificado" 
                           value="<?php echo $certificacao['numero_certificado'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="data_emissao" class="form-label">Data de Emissão</label>
                    <input type="date" class="form-control" id="data_emissao" name="data_emissao" 
                           value="<?php echo $certificacao['data_emissao'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="data_validade" class="form-label">Data de Validade</label>
                    <input type="date" class="form-control" id="data_validade" name="data_validade" 
                           value="<?php echo $certificacao['data_validade'] ?? ''; ?>">
                    <small class="text-muted">Certificações SPG geralmente têm validade de 1 ano</small>
                </div>
            </div>

            <!-- Status e Organismo -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Em Conversão" <?php echo ($certificacao && $certificacao['status'] == 'Em Conversão') ? 'selected' : ''; ?>>Em Conversão</option>
                        <option value="Ativa" <?php echo ($certificacao && $certificacao['status'] == 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Vencida" <?php echo ($certificacao && $certificacao['status'] == 'Vencida') ? 'selected' : ''; ?>>Vencida</option>
                        <option value="Suspensa" <?php echo ($certificacao && $certificacao['status'] == 'Suspensa') ? 'selected' : ''; ?>>Suspensa</option>
                        <option value="Cancelada" <?php echo ($certificacao && $certificacao['status'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                    <small class="text-muted">Período de conversão: mínimo 12 meses (Portaria 52/2021)</small>
                <// div>
                
                <div class="col-md-6 mb-3">
                    <label for="organismo_certificador" class="form-label">Organismo Certificador/SPG</label>
                    <input type="text" class="form-control" id="organismo_certificador" name="organismo_certificador" 
                           value="<?php echo $certificacao['organismo_certificador'] ?? ''; ?>" 
                           placeholder="Ex: SPG Orgânicos do Vale">
                </div>
            </div>

            <!-- Grupo (para SPG) -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="nome_grupo" class="form-label">Nome do Grupo (SPG)</label>
                    <input type="text" class="form-control" id="nome_grupo" name="nome_grupo" 
                           value="<?php echo $certificacao['nome_grupo'] ?? ''; ?>" 
                           placeholder="Nome do grupo de agricultores do SPG">
                    <small class="text-muted">Aplicável para certificações SPG</small>
                </div>
            </div>

            <!-- URLs de Documentos -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="documento_url" class="form-label">URL do Certificado</label>
                    <input type="url" class="form-control" id="documento_url" name="documento_url" 
                           value="<?php echo $certificacao['documento_url'] ?? ''; ?>" 
                           placeholder="https://exemplo.com/certificado.pdf">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="declaracao_compromisso_url" class="form-label">URL da Declaração de Compromisso</label>
                    <input type="url" class="form-control" id="declaracao_compromisso_url" name="declaracao_compromisso_url" 
                           value="<?php echo $certificacao['declaracao_compromisso_url'] ?? ''; ?>" 
                           placeholder="https://exemplo.com/declaracao.pdf">
                </div>
            </div>

            <!-- Observações -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?php echo $certificacao['observacoes'] ?? ''; ?></textarea>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> <?php echo $isEdit ? 'Atualizar' : 'Cadastrar'; ?>
                    </button>
                    <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#formCertificacao').on('submit', function(e) {
        e.preventDefault();
        
        const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
        const formData = {};
        
        // Coletar dados do formulário
        $(this).serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        // Configurar requisição
        const url = '<?php echo SITE_URL; ?>/api/certificacao/controller.php';
        const method = isEdit ? 'PUT' : 'POST';
        
       // Enviar dados
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(isEdit ? 'Certificação atualizada com sucesso!' : 'Certificação cadastrada com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/certificacoes';
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar requisição');
        });
    });
});
</script>
