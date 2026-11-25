<?php
$page_title = "Editar Evento Reprodutivo";
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
    header('Location: ' . SITE_URL . '/producao-animal/reproducao');
    exit;
}

$paClass = new ProducaoAnimal();
$registro = $paClass->getReproducaoById($id);
if (!$registro) {
    header('Location: ' . SITE_URL . '/producao-animal/reproducao');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$animais = $paClass->getAll($escopo);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['data_evento'] ?? '') === '') {
        $message = 'Informe a data do evento.';
        $message_type = 'danger';
    } else {
        $aleitamento = !empty($_POST['periodo_aleitamento_dias']) ? (int)$_POST['periodo_aleitamento_dias'] : 0;
        if (($_POST['tipo_evento'] ?? '') === 'DESMAME' && $aleitamento > 0) {
            $especie = !empty($_POST['especie']) ? strtoupper($_POST['especie']) : '';
            $min = 90;
            if (in_array($especie, ['OVINOS', 'CAPRINOS'])) $min = 28;
            if ($especie === 'SUINOS') $min = 45;
            if (!$especie) $min = 0;
            if ($min > 0 && $aleitamento < $min) {
                $message = "Aleitamento abaixo do mínimo legal para a espécie ($min dias). Justifique em observações.";
                $message_type = 'danger';
            }
        }
        if (!$message) {
            if ($paClass->atualizarReproducao($id, $_POST)) {
                AuditLog::registrar('PA_REPRODUCAO_ATUALIZADA', 'controle_reproducao_animal', $id, $_POST['tipo_evento'] . ' em ' . $_POST['data_evento']);
                header('Location: ' . SITE_URL . '/producao-animal/reproducao?atualizado=1');
                exit;
            }
            $message = 'Erro ao atualizar o evento. Tente novamente.';
            $message_type = 'danger';
        }
    }
    $registro = array_merge($registro, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-stars text-success"></i> Editar Evento Reprodutivo</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de Evento <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_evento" required>
                        <?php foreach (['COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($registro['tipo_evento'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data do Evento <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_evento" required value="<?php echo htmlspecialchars($registro['data_evento']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Animal (mãe/fêmea)</label>
                    <select class="form-select" name="animal_id">
                        <option value="">— Selecione —</option>
                        <?php foreach ($animais as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (int)($registro['animal_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome_animal'] . ' (' . $a['especie'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Identificação do Reprodutor</label>
                    <input type="text" class="form-control" name="macho_reprodutor_id" value="<?php echo htmlspecialchars($registro['macho_reprodutor_id'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Previsão de Parto</label>
                    <input type="date" class="form-control" name="previsao_parto" value="<?php echo htmlspecialchars($registro['previsao_parto'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grupo Reprodutivo</label>
                    <input type="text" class="form-control" name="grupo_reprodutivo" value="<?php echo htmlspecialchars($registro['grupo_reprodutivo'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nascidos Vivos</label>
                    <input type="number" class="form-control" name="nascidos_vivos" min="0" value="<?php echo htmlspecialchars($registro['nascidos_vivos'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Natimortos</label>
                    <input type="number" class="form-control" name="natimortos" min="0" value="<?php echo htmlspecialchars($registro['natimortos'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Período de Aleitamento (dias)</label>
                    <input type="number" class="form-control" name="periodo_aleitamento_dias" min="0" value="<?php echo htmlspecialchars($registro['periodo_aleitamento_dias'] ?? ''); ?>">
                    <div class="form-text">Mín. 90 (bov./bubal.), 28 (ov./cap.), 45 (suínos).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" name="especie">
                        <option value="">—</option>
                        <?php foreach (['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS'] as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo ($registro['especie'] ?? '') === $e ? 'selected' : ''; ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
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