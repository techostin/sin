<?php
$page_title = "Editar Registro de Sanidade";
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
    header('Location: ' . SITE_URL . '/producao-animal/sanidade');
    exit;
}

$paClass = new ProducaoAnimal();
$registro = $paClass->getSanidadeById($id);
if (!$registro) {
    header('Location: ' . SITE_URL . '/producao-animal/sanidade');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$animais = $paClass->getAll($escopo);
$lotes = $paClass->getLotes($escopo);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['data_tratamento'] ?? '') === '') {
        $message = 'Informe a data do tratamento.';
        $message_type = 'danger';
    } else {
        $excepcional = !isset($_POST['produto_normatizado']) || (int)$_POST['produto_normatizado'] !== 1;
        $carencia = !empty($_POST['periodo_carencia_horas']) ? (int)$_POST['periodo_carencia_horas'] : 0;

        if ($excepcional && $carencia < 96) {
            $message = 'O período de carência mínimo para tratamento excepcional é de 96 horas.';
            $message_type = 'danger';
        } elseif ($paClass->atualizarSanidade($id, $_POST)) {
            AuditLog::registrar('PA_SANIDADE_ATUALIZADA', 'controle_sanidade_animal', $id, 'Sanidade em ' . $_POST['data_tratamento']);
            header('Location: ' . SITE_URL . '/producao-animal/sanidade?atualizado=1');
            exit;
        } else {
            $message = 'Erro ao atualizar o registro. Tente novamente.';
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
        <h1><i class="bi bi-heart-pulse text-success"></i> Editar Registro de Sanidade</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_tratamento" required value="<?php echo htmlspecialchars($registro['data_tratamento']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Tratamento <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_tratamento" required>
                        <?php foreach (['VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($registro['tipo_tratamento'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Maximete Normatizado (Anexo II)</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="produto_normatizado" value="1" id="prodNorm" <?php echo (int)($registro['produto_normatizado'] ?? 1) === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prodNorm">Produto consta do Anexo II da Portaria 52/2021</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Produto Utilizado</label>
                    <input type="text" class="form-control" name="produto_utilizado" value="<?php echo htmlspecialchars($registro['produto_utilizado'] ?? ''); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Motivo</label>
                    <input type="text" class="form-control" name="motivo" value="<?php echo htmlspecialchars($registro['motivo'] ?? ''); ?>">
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
                    <label class="form-label">Dose/Quantidade</label>
                    <input type="text" class="form-control" name="dose_quantidade" value="<?php echo htmlspecialchars($registro['dose_quantidade'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Via de Aplicação</label>
                    <input type="text" class="form-control" name="via_aplicacao" value="<?php echo htmlspecialchars($registro['via_aplicacao'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Responsável</label>
                    <input type="text" class="form-control" name="responsavel" value="<?php echo htmlspecialchars($registro['responsavel'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Período de Carência (horas)</label>
                    <input type="number" class="form-control" name="periodo_carencia_horas" min="0" value="<?php echo htmlspecialchars($registro['periodo_carencia_horas'] ?? ''); ?>">
                    <div class="form-text">Tratamento excepcional: mínimo 96h.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de Liberação Orgânica</label>
                    <input type="date" class="form-control" name="data_liberacao_organica" value="<?php echo htmlspecialchars($registro['data_liberacao_organica'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Aprovado pela OCS (excepcional)</label>
                    <select class="form-select" name="aprovado_ocs">
                        <option value="">—</option>
                        <option value="1" <?php echo ($registro['aprovado_ocs'] ?? '') === '1' ? 'selected' : ''; ?>>Sim</option>
                        <option value="0" <?php echo ($registro['aprovado_ocs'] ?? '') === '0' ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nº do Tratamento (no ano)</label>
                    <input type="number" class="form-control" name="numero_tratamento" min="1" value="<?php echo htmlspecialchars($registro['numero_tratamento'] ?? 1); ?>">
                    <div class="form-text">Limite de 2 tratamentos/ano por animal.</div>
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