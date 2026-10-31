<?php
$page_title = "Editar Registro de Alimentação";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . SITE_URL . '/producao-animal/alimentacao');
    exit;
}

$paClass = new ProducaoAnimal();
$registro = $paClass->getAlimentacaoById($id);
if (!$registro) {
    header('Location: ' . SITE_URL . '/producao-animal/alimentacao');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$animais = $paClass->getAll($escopo);
$lotes = $paClass->getLotes($escopo);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['data_alimentacao'] ?? '') === '') {
        $message = 'Informe a data da alimentação.';
        $message_type = 'danger';
    } elseif (($_POST['origem'] ?? '') === 'NAO_ORGANICO_AUTORIZADO' && empty($_POST['observacoes'])) {
        $message = 'Para alimento de origem não orgânica, descreva a escassez/necessidade e a autorização no PMO.';
        $message_type = 'danger';
    } elseif ($paClass->atualizarAlimentacao($id, $_POST)) {
        AuditLog::registrar('PA_ALIMENTACAO_ATUALIZADA', 'controle_alimentacao_animal', $id, 'Alimentação em ' . $_POST['data_alimentacao']);
        header('Location: ' . SITE_URL . '/producao-animal/alimentacao?atualizado=1');
        exit;
    } else {
        $message = 'Erro ao atualizar o registro. Tente novamente.';
        $message_type = 'danger';
    }
    $registro = array_merge($registro, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-basket text-success"></i> Editar Registro de Alimentação</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_alimentacao" required value="<?php echo htmlspecialchars($registro['data_alimentacao']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Alimento <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_alimento" required>
                        <?php foreach (['FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($registro['tipo_alimento'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Origem do Alimento <span class="text-danger">*</span></label>
                    <select class="form-select" name="origem" required>
                        <option value="ORGANICO" <?php echo (($registro['origem'] ?? 'ORGANICO') === 'ORGANICO') ? 'selected' : ''; ?>>Orgânico (própria ou outra unidade orgânica)</option>
                        <option value="EM_CONVERSAO" <?php echo ($registro['origem'] ?? '') === 'EM_CONVERSAO' ? 'selected' : ''; ?>>Em conversão</option>
                        <option value="NAO_ORGANICO_AUTORIZADO" <?php echo ($registro['origem'] ?? '') === 'NAO_ORGANICO_AUTORIZADO' ? 'selected' : ''; ?>>Não orgânico (exceção autorizada no PMO)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Animal</label>
                    <select class="form-select" name="animal_id">
                        <option value="">— Selecione (ou use lote) —</option>
                        <?php foreach ($animais as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (int)($registro['animal_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome_animal'] . ' (' . $a['especie'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lote</label>
                    <select class="form-select" name="lote_id">
                        <option value="">— Selecione (ou use animal) —</option>
                        <?php foreach ($lotes as $l): ?>
                            <option value="<?php echo $l['id']; ?>" <?php echo (int)($registro['lote_id'] ?? 0) === (int)$l['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['nome_lote']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Produto Utilizado</label>
                    <input type="text" class="form-control" name="produto_utilizado" value="<?php echo htmlspecialchars($registro['produto_utilizado'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade (kg)</label>
                    <input type="number" step="0.01" class="form-control" name="quantidade_kg" min="0" value="<?php echo htmlspecialchars($registro['quantidade_kg'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">% Matéria Seca</label>
                    <input type="number" step="0.01" class="form-control" name="percentual_materia_seca" min="0" max="100" value="<?php echo htmlspecialchars($registro['percentual_materia_seca'] ?? ''); ?>">
                    <div class="form-text">Limite não orgânico: 15% (ruminantes) / 20% (não ruminantes).</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações (escassez/necessidade/autorização PMO)</label>
                    <textarea class="form-control" name="observacoes" rows="3"><?php echo htmlspecialchars($registro['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Alterações</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>