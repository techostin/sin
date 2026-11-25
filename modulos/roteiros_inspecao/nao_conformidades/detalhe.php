<?php
$page_title = "Detalhe da Não Conformidade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger mt-4'>ID inválido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$userTable = $_SESSION['user_table'] ?? 'associados';
$isAdmin   = $userNivel <= NIVEL_GESTOR;
$userId    = $_SESSION['user_id'];

$db = Database::getInstance()->getConnection();

$sql = "SELECT nc.*,
               vt.data_visita,
               a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
               p.propriedade AS propriedade_nome,
               r.nome AS roteiro_nome
        FROM nao_conformidades nc
        JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id
        JOIN associados a  ON vt.associado_id   = a.id
        JOIN propriedades p ON vt.propriedade_id = p.id
        JOIN roteiros_inspecao r ON vt.roteiro_id = r.id
        WHERE nc.id = ?";
$params = [(int)$id];

if (!$isAdmin && $userTable === 'associados') {
    $sql    .= " AND vt.associado_id = ?";
    $params[] = $userId;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$nc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nc) {
    echo "<div class='alert alert-danger mt-4'>Não conformidade não encontrada ou sem permissão de acesso.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
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
                <h1 class="mb-1">Não Conformidade #<?php echo $nc['id']; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/nao-conformidades">Não Conformidades</a></li>
                        <li class="breadcrumb-item active">#<?php echo $nc['id']; ?></li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo SITE_URL; ?>/visita-tecnica/<?php echo $nc['visita_tecnica_id']; ?>/relatorio" class="btn btn-assoc-ghost btn-sm">
                    <i class="bi bi-eye me-1"></i> Ver Visita
                </a>
                <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="btn btn-assoc-ghost btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Info principal -->
        <div class="col-lg-4">
            <div class="gm-card mb-3">
                <div class="card-head">Informações Gerais</div>
                <div class="p-4">
                    <p class="mb-1 text-muted small">STATUS</p>
                    <span class="badge bg-<?php echo $cor; ?> fs-6 mb-3"><?php echo htmlspecialchars($nc['status']); ?></span>

                    <p class="mb-1 text-muted small">GRAVIDADE</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($nc['gravidade'] ?? '—'); ?></p>

                    <p class="mb-1 text-muted small">PRODUTOR</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($nc['associado_nome'] . ' ' . $nc['associado_sobrenome']); ?></p>

                    <p class="mb-1 text-muted small">PROPRIEDADE</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($nc['propriedade_nome']); ?></p>

                    <p class="mb-1 text-muted small">VISITA</p>
                    <p class="fw-bold"><?php echo $nc['data_visita'] ? date('d/m/Y', strtotime($nc['data_visita'])) : '—'; ?></p>

                    <p class="mb-1 text-muted small">ROTEIRO</p>
                    <p class="fw-bold"><?php echo htmlspecialchars($nc['roteiro_nome']); ?></p>

                    <p class="mb-1 text-muted small">PRAZO CORREÇÃO</p>
                    <p class="fw-bold <?php echo ($nc['prazo_correcao'] && strtotime($nc['prazo_correcao']) < time() && $nc['status'] !== 'Resolvida') ? 'text-danger' : ''; ?>">
                        <?php echo $nc['prazo_correcao'] ? date('d/m/Y', strtotime($nc['prazo_correcao'])) : '—'; ?>
                    </p>

                    <?php if ($nc['data_resolucao']): ?>
                    <p class="mb-1 text-muted small">DATA RESOLUÇÃO</p>
                    <p class="fw-bold text-success"><?php echo date('d/m/Y', strtotime($nc['data_resolucao'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Formulário de atualização -->
        <div class="col-lg-8">
            <div class="gm-card">
                <div class="card-head">Detalhes e Ação Corretiva</div>
                <div class="p-4">
                    <div class="alert alert-light border mb-4">
                        <div class="text-muted small mb-1">REQUISITO / NÃO CONFORMIDADE:</div>
                        <div class="fw-bold fs-5">
                            <?php echo nl2br(htmlspecialchars($nc['descricao'] ?: ($nc['item_texto'] ?? 'Item #' . $nc['item_checklist_id']))); ?>
                        </div>
                    </div>

                    <?php $disabled = ($nc['status'] === 'Resolvida') ? 'disabled' : ''; ?>

                    <form id="formNC" method="post">
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="id" value="<?php echo $nc['id']; ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" <?php echo $disabled; ?>>
                                    <option value="Aberta"      <?php echo $nc['status'] === 'Aberta'       ? 'selected' : ''; ?>>Aberta</option>
                                    <option value="Em Correção" <?php echo $nc['status'] === 'Em Correção'  ? 'selected' : ''; ?>>Em Correção</option>
                                    <option value="Resolvida"   <?php echo $nc['status'] === 'Resolvida'    ? 'selected' : ''; ?>>Resolvida</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prazo de Correção</label>
                                <input type="date" class="form-control" name="prazo_correcao"
                                       value="<?php echo $nc['prazo_correcao'] ? substr($nc['prazo_correcao'], 0, 10) : ''; ?>"
                                       <?php echo $disabled; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Responsável</label>
                                <input type="text" class="form-control" name="responsavel_correcao"
                                       value="<?php echo htmlspecialchars($nc['responsavel_correcao'] ?? ''); ?>"
                                       placeholder="Nome do responsável" <?php echo $disabled; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gravidade</label>
                                <select class="form-select" name="gravidade" <?php echo $disabled; ?>>
                                    <option value="Baixa"   <?php echo $nc['gravidade'] === 'Baixa'   ? 'selected' : ''; ?>>Baixa</option>
                                    <option value="Alta"    <?php echo $nc['gravidade'] === 'Alta'    ? 'selected' : ''; ?>>Alta</option>
                                    <option value="Critica" <?php echo $nc['gravidade'] === 'Critica' ? 'selected' : ''; ?>>Crítica</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Ação Corretiva</label>
                                <textarea class="form-control" name="acao_corretiva" rows="3"
                                          <?php echo $disabled; ?> placeholder="Descreva as ações tomadas..."><?php echo htmlspecialchars($nc['acao_corretiva'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observações</label>
                                <textarea class="form-control" name="observacoes" rows="2"
                                          <?php echo $disabled; ?> placeholder="Observações adicionais..."><?php echo htmlspecialchars($nc['observacoes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <?php if ($nc['status'] !== 'Resolvida'): ?>
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="<?php echo SITE_URL; ?>/nao-conformidades" class="btn btn-assoc-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-assoc-success">
                                <i class="bi bi-save me-1"></i> Salvar Alterações
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mt-4 mb-0">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Esta não conformidade foi resolvida
                            <?php if ($nc['verificado_por']): ?> por <strong><?php echo htmlspecialchars($nc['verificado_por']); ?></strong><?php endif; ?>
                            em <strong><?php echo $nc['data_resolucao'] ? date('d/m/Y', strtotime($nc['data_resolucao'])) : '—'; ?></strong>.
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$('#formNC').on('submit', function(e) {
    e.preventDefault();
    const payload = {};
    $(this).serializeArray().forEach(i => payload[i.name] = i.value);
    delete payload._method;

    fetch('<?php echo SITE_URL; ?>/api/nao_conformidades.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo SITE_URL; ?>/nao-conformidades';
        } else {
            alert('Erro: ' + (data.error || 'desconhecido'));
        }
    });
});
</script>
