<?php
$page_title = "Renovação de Certificado";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de certificação inválido.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome 
                      FROM certificacao_participativa c 
                      JOIN associados a ON c.associado_id = a.id 
                      WHERE c.id = ?");
$stmt->execute([$id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cert) {
    echo "<div class='alert alert-danger'>Certificação não encontrada.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Anti-IDOR: certificação é dado do associado (certificacao_participativa.associado_id).
// Admin/Gestor renovam para qualquer produtor; nível 3 só a própria certificação.
$donoCert = (int) ($cert['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoCert <= 0 || $donoCert !== (int) $user->getAssociadoIdResolvido())) {
    echo "<div class='alert alert-danger mt-4'>Acesso negado: esta certificação pertence a outro produtor.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1">Renovar Certificação</h1>
                <p class="mb-0">Atualize as datas de validade para o novo ciclo de certificação orgânica.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-assoc-ghost btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Listagem
            </a>
        </div>
    </div>

    <div class="row g-4">
    <div class="col-md-6">
        <div class="gm-card mb-4">
            <div class="card-head">
                <i class="bi bi-arrow-repeat me-2" style="color:var(--gm-primary);"></i> Dados da Renovação
            </div>
            <div class="p-4">
                <form id="formRenovacao">
                    <input type="hidden" name="id" value="<?php echo $cert['id']; ?>">
                    <input type="hidden" name="associado_id" value="<?php echo $cert['associado_id']; ?>">
                    <input type="hidden" name="tipo_certificacao" value="<?php echo $cert['tipo_certificacao']; ?>">

                    <div class="mb-3">
                        <label class="form-label">Associado</label>
                        <input type="text" class="form-control" value="<?php echo $cert['associado_nome'] . ' ' . $cert['associado_sobrenome']; ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" class="form-control" value="<?php echo $cert['tipo_certificacao']; ?>" disabled>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_emissao" class="form-label">Nova Data de Emissão</label>
                            <input type="date" class="form-control" id="data_emissao" name="data_emissao" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_validade" class="form-label">Nova Data de Validade</label>
                            <input type="date" class="form-control" id="data_validade" name="data_validade" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="numero_certificado" class="form-label">Novo Número do Certificado</label>
                        <input type="text" class="form-control" id="numero_certificado" name="numero_certificado" value="<?php echo $cert['numero_certificado']; ?>">
                    </div>

                    <div class="mb-3">
                        <label for="arquivo_assinatura" class="form-label">
                            <i class="bi bi-file-earmark-pdf"></i> Certificado Assinado (PDF)
                        </label>
                        <input type="file" class="form-control" id="arquivo_assinatura" name="arquivo_assinatura" 
                               accept=".pdf,application/pdf" required>
                        <small class="text-muted">
                            Faça upload do certificado já assinado digitalmente em formato PDF.
                            O certificado só ficará disponível para download após o upload do arquivo assinado.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações da Renovação</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-assoc-ghost">Cancelar</a>
                        <button type="submit" class="btn btn-assoc-success">
                            <i class="bi bi-upload"></i> Upload e Confirmar Renovação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="gm-card">
            <div class="card-head bg-light">
                <i class="bi bi-info-circle me-2" style="color:var(--gm-primary);"></i> Certificado Anterior
            </div>
            <div class="p-4">
                <p><strong>Nº Atual:</strong> <?php echo $cert['numero_certificado'] ?: 'Não informado'; ?></p>
                <p><strong>Emissão:</strong> <?php echo $cert['data_emissao'] ? date('d/m/Y', strtotime($cert['data_emissao'])) : '-'; ?></p>
                <p><strong>Validade:</strong> <?php echo $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '-'; ?></p>
                <p><strong>Status:</strong> <span class="badge bg-<?php echo $cert['status'] == 'Ativa' ? 'success' : 'warning'; ?>"><?php echo $cert['status']; ?></span></p>
                <hr>
                <p class="text-muted small">Ao confirmar a renovação, o sistema atualizará o registro principal e manterá o histórico de conformidade associado.</p>
            </div>
        </div>
    </div>
</div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#formRenovacao').on('submit', function(e) {
        e.preventDefault();
        
        // Validar se o arquivo foi selecionado
        const arquivo = document.getElementById('arquivo_assinatura').files[0];
        if (!arquivo) {
            alert('Selecione o arquivo PDF do certificado assinado.');
            return;
        }
        
        // Validar formato PDF
        if (arquivo.type !== 'application/pdf' && !arquivo.name.toLowerCase().endsWith('.pdf')) {
            alert('O arquivo deve ser um PDF.');
            return;
        }
        
        // Validar tamanho (máx 10MB)
        if (arquivo.size > 10 * 1024 * 1024) {
            alert('O arquivo deve ter no máximo 10MB.');
            return;
        }
        
        // Desabilitar botão e mostrar loading
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Enviando...');
        
        // Construir FormData com arquivo e dados do formulário
        const formData = new FormData(this);
        formData.set('action', 'upload_assinatura');
        formData.set('status', 'Ativa');
        
        fetch('<?php echo SITE_URL; ?>/api/certificacao/controller.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Certificação renovada e certificado assinado com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/certificacoes';
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
                submitBtn.prop('disabled', false).html(originalText);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar renovação. Verifique o console.');
            submitBtn.prop('disabled', false).html(originalText);
        });
    });
});
</script>
