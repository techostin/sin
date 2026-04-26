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
?>

<h1 class="mt-4">Renovar Certificação</h1>
<p>Atualize as datas de validade para o novo ciclo de certificação orgânica.</p>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-arrow-repeat"></i> Dados da Renovação
            </div>
            <div class="card-body">
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
                        <label for="observacoes" class="form-label">Observações da Renovação</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success">Confirmar Renovação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <i class="bi bi-info-circle"></i> Certificado Anterior
            </div>
            <div class="card-body">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#formRenovacao').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {};
        $(this).serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        // Ativar o status para "Ativa" na renovação
        formData['status'] = 'Ativa';
        
        fetch('<?php echo SITE_URL; ?>/api/certificacao/controller.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Certificação renovada com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/certificacoes';
            } else {
                alert('Erro na renovação: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar renovação');
        });
    });
});
</script>
