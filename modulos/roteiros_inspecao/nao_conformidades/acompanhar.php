<?php
$page_title = "Acompanhar Resolução - Não Conformidade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger mt-4'>ID de Não Conformidade inválido ou não informado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$userTable = $_SESSION['user_table'] ?? 'associados';
$isAdmin   = $userNivel <= NIVEL_GESTOR;
$userId    = $_SESSION['user_id'];

// Buscar os dados da Não Conformidade
$sql = "SELECT nc.*, vt.data_visita, vt.observacoes as visita_obs,
               a.nome as associado_nome, a.sobrenome as associado_sobrenome,
               p.propriedade as propriedade_nome, r.nome as roteiro_nome, r.checklist as roteiro_checklist
        FROM nao_conformidades nc
        JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id
        JOIN associados a ON vt.associado_id = a.id
        JOIN propriedades p ON vt.propriedade_id = p.id
        JOIN roteiros_inspecao r ON vt.roteiro_id = r.id
        WHERE nc.id = ?";
$params = [(int)$id];

if (!$isAdmin && $userTable === 'associados') {
    $sql .= " AND vt.associado_id = ?";
    $params[] = $userId;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$nc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nc) {
    echo "<div class='alert alert-danger mt-4'>Ocorrência não encontrada ou você não possui permissão para visualizá-la.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Resolver o nome do item do checklist
require_once __DIR__ . '/../../../classes/ChecklistPadrao.php';
$checklist = json_decode($nc['roteiro_checklist'], true) ?? [];
$itemTexto = "Item #" . $nc['item_checklist_id'];
foreach ($checklist as $item) {
    if ((string)($item['id'] ?? '') === (string)$nc['item_checklist_id']) {
        $itemTexto = $item['item'] ?? $item['pergunta'] ?? $itemTexto;
        break;
    }
}
// Se não encontrado no roteiro, buscar no checklist padrão (produção/processamento orgânico)
if ($itemTexto === "Item #" . $nc['item_checklist_id'] && isset(ChecklistPadrao::mapa()[(int)$nc['item_checklist_id']])) {
    $itemTexto = ChecklistPadrao::mapa()[(int)$nc['item_checklist_id']]['item'];
}
if (empty($nc['descricao'])) {
    $nc['descricao'] = $itemTexto;
}

$statusColors = ['Aberta' => 'danger', 'Em Correção' => 'warning', 'Resolvida' => 'success'];
$cor = $statusColors[$nc['status']] ?? 'secondary';
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-clock-history me-2" style="color:var(--gm-primary);"></i>Acompanhar Resolução</h1>
                <p class="mb-0">Gestão do plano de ação corretiva para conformidade orgânica.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="btn btn-assoc-ghost btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Lista
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Coluna da Linha do Tempo e Detalhes -->
        <div class="col-lg-8">
            <!-- Detalhe da Irregularidade -->
            <div class="gm-card mb-4">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-<?php echo $cor; ?> fs-6 py-2 px-3"><?php echo htmlspecialchars($nc['status']); ?></span>
                        <span class="small text-muted fw-bold">Gravidade: <span class="text-danger"><?php echo htmlspecialchars($nc['gravidade']); ?></span></span>
                    </div>
                    <div class="bg-light p-3 rounded mb-3 border-start border-4 border-danger">
                        <h6 class="text-muted small fw-bold mb-1">REQUISITO NÃO ATENDIDO</h6>
                        <p class="mb-0 fw-bold text-dark fs-5"><?php echo nl2br(htmlspecialchars($nc['descricao'])); ?></p>
                    </div>
                    
                    <div class="row g-3 text-muted small">
                        <div class="col-sm-6">
                            <i class="bi bi-building me-1"></i> <strong>Propriedade:</strong> <?php echo htmlspecialchars($nc['propriedade_nome']); ?>
                        </div>
                        <div class="col-sm-6">
                            <i class="bi bi-person me-1"></i> <strong>Produtor:</strong> <?php echo htmlspecialchars($nc['associado_nome'] . ' ' . $nc['associado_sobrenome']); ?>
                        </div>
                        <div class="col-sm-6">
                            <i class="bi bi-calendar-event me-1"></i> <strong>Identificado em:</strong> <?php echo date('d/m/Y', strtotime($nc['data_visita'])); ?>
                        </div>
                        <div class="col-sm-6">
                            <i class="bi bi-alarm me-1"></i> <strong>Prazo limite:</strong> <span class="fw-bold <?php echo (strtotime($nc['prazo_correcao']) < time() && $nc['status'] !== 'Resolvida') ? 'text-danger' : 'text-dark'; ?>"><?php echo date('d/m/Y', strtotime($nc['prazo_correcao'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulário do Plano de Ação -->
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-card-checklist me-2" style="color:var(--gm-primary);"></i>Plano de Ação Corretiva
                </div>
                <div class="p-4">
                    <form id="formPlanoAcao">
                        <input type="hidden" name="id" value="<?php echo $nc['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Descrição das Ações Realizadas / Propostas *</label>
                            <textarea class="form-control" name="acao_corretiva" rows="4" required placeholder="Descreva as medidas que você já adotou ou planeja adotar para resolver esta não conformidade..."><?php echo htmlspecialchars($nc['acao_corretiva'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">Por favor, descreva as ações corretivas.</div>
                        </div>

                        <div class="row mb-3 g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Responsável pela Ação *</label>
                                <input type="text" class="form-control" name="responsavel_correcao" value="<?php echo htmlspecialchars($nc['responsavel_correcao'] ?? $nc['associado_nome'] . ' ' . $nc['associado_sobrenome']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Atualizar Status</label>
                                <select class="form-select text-dark fw-bold" name="status">
                                    <option value="Aberta" <?php echo $nc['status'] === 'Aberta' ? 'selected' : ''; ?>>Aberta (Pendente)</option>
                                    <option value="Em Correção" <?php echo $nc['status'] === 'Em Correção' ? 'selected' : ''; ?>>Em Correção</option>
                                    <?php if ($isAdmin): ?>
                                    <option value="Resolvida" <?php echo $nc['status'] === 'Resolvida' ? 'selected' : ''; ?>>Resolvida (Confirmada pelo Técnico)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Observações / Comentários</label>
                            <textarea class="form-control" name="observacoes" rows="2" placeholder="Notas adicionais..."><?php echo htmlspecialchars($nc['observacoes'] ?? ''); ?></textarea>
                        </div>

                        <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">* Campos obrigatórios</span>
                            <button type="submit" class="btn btn-assoc-success px-4 fw-bold" id="btn-salvar-acao">
                                <i class="bi bi-save"></i> Salvar Plano de Ação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna de Evidências / Fotos -->
        <div class="col-lg-4">
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-camera-fill me-2" style="color:var(--gm-primary);"></i>Fotos de Evidência
                </div>
                <div class="p-4">
                    <?php if ($nc['evidencia_correcao_url']): ?>
                        <div class="mb-3 text-center border p-2 rounded bg-light">
                            <img src="<?php echo SITE_URL . '/' . htmlspecialchars($nc['evidencia_correcao_url']); ?>" class="img-fluid rounded" style="max-height: 250px;" alt="Evidência de Correção">
                            <p class="mt-2 mb-0 small text-success fw-bold"><i class="bi bi-patch-check-fill"></i> Foto de Evidência Anexada</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small mb-3">Nenhuma imagem de evidência de correção foi anexada ainda.</div>
                    <?php endif; ?>

                    <form id="formUploadEvidencia" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo $nc['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Anexar nova foto de evidência</label>
                            <input class="form-control form-control-sm" type="file" name="evidencia_foto" accept="image/*" required>
                            <small class="text-muted">Tire uma foto que comprove a resolução do problema.</small>
                        </div>
                        <button type="submit" class="btn btn-assoc-success btn-sm w-100 fw-bold" id="btn-upload">
                            <i class="bi bi-upload"></i> Enviar Foto
                        </button>
                    </form>
                </div>
            </div>

            <!-- Técnico Resolução (Apenas para exibir status caso esteja resolvido) -->
            <?php if ($nc['status'] === 'Resolvida'): ?>
                <div class="gm-card bg-success bg-opacity-10 mb-4">
                    <div class="p-4">
                        <h6 class="fw-bold text-success"><i class="bi bi-check-circle-fill me-2"></i>Conformidade Validada</h6>
                        <p class="small mb-1 text-muted">VALIDADOR</p>
                        <p class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($nc['verificado_por'] ?? 'Técnico Responsável'); ?></p>
                        
                        <p class="small mb-1 text-muted">DATA DA VALIDAÇÃO</p>
                        <p class="fw-bold text-dark mb-0"><?php echo date('d/m/Y', strtotime($nc['data_resolucao'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Enviar plano de ação (via API PUT)
    $('#formPlanoAcao').on('submit', function(e) {
        e.preventDefault();
        
        const payload = {};
        $(this).serializeArray().forEach(i => payload[i.name] = i.value);

        $('#btn-salvar-acao').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Salvando...');

        fetch('<?php echo SITE_URL; ?>/api/nao_conformidades.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Plano de ação atualizado com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao atualizar: ' + (data.error || 'Erro desconhecido.'));
                $('#btn-salvar-acao').prop('disabled', false).html('<i class="bi bi-save"></i> Salvar Plano de Ação');
            }
        })
        .catch(err => {
            console.error('Action Save Error:', err);
            alert('❌ Falha na comunicação com o servidor.');
            $('#btn-salvar-acao').prop('disabled', false).html('<i class="bi bi-save"></i> Salvar Plano de Ação');
        });
    });

    // Enviar imagem de evidência (via POST)
    $('#formUploadEvidencia').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        $('#btn-upload').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Enviando...');

        // Usamos um controller para tratar o upload e associar à não conformidade
        fetch('<?php echo SITE_URL; ?>/api/nao_conformidades.php?action=upload_evidencia', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Foto de evidência anexada com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro no upload: ' + (data.error || 'Erro desconhecido.'));
                $('#btn-upload').prop('disabled', false).html('<i class="bi bi-upload"></i> Enviar Foto');
            }
        })
        .catch(err => {
            console.error('Upload Error:', err);
            alert('❌ Erro ao enviar arquivo para o servidor.');
            $('#btn-upload').prop('disabled', false).html('<i class="bi bi-upload"></i> Enviar Foto');
        });
    });
});
</script>
