<?php
$page_title = "Novo Registro de Sanidade";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$paClass = new ProducaoAnimal();
$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$animais = $paClass->getAll($escopo);
$lotes = $paClass->getLotes($escopo);

$message = '';
$message_type = '';
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['data_tratamento'] ?? '') === '') {
        $message = 'Informe a data do tratamento.';
        $message_type = 'danger';
    } else {
        $excepcional = !isset($dados['produto_normatizado']) || (int)$dados['produto_normatizado'] !== 1;
        $carencia = !empty($dados['periodo_carencia_horas']) ? (int)$dados['periodo_carencia_horas'] : 0;

        if ($excepcional && $carencia <= 0) {
            $message = 'Tratamento excepcional exige período de carência (mínimo 96 horas).';
            $message_type = 'danger';
        } elseif ($excepcional && $carencia < 96) {
            $message = 'O período de carência mínimo para tratamento excepcional é de 96 horas.';
            $message_type = 'danger';
        } else {
            $dados['associado_id'] = $user->getAssociadoId();
            $dados['criado_por'] = (int)($_SESSION['user_id'] ?? 0);
            if ($id = $paClass->criarSanidade($dados)) {
                AuditLog::registrar('PA_SANIDADE_CADASTRADA', 'controle_sanidade_animal', $id, 'Sanidade ' . $dados['tipo_tratamento'] . ' em ' . $dados['data_tratamento']);
                header('Location: ' . SITE_URL . '/producao-animal/sanidade?cadastrado=1');
                exit;
            }
            $message = 'Erro ao salvar o registro. Tente novamente.';
            $message_type = 'danger';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-heart-pulse text-success"></i> Novo Registro de Sanidade</h1>
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
                    <input type="date" class="form-control" name="data_tratamento" required value="<?php echo htmlspecialchars($dados['data_tratamento'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Tratamento <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_tratamento" required>
                        <?php foreach (['VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($dados['tipo_tratamento'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Maximete Normatizado (Anexo II)</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="produto_normatizado" value="1" id="prodNorm" <?php echo isset($dados['produto_normatizado']) && (int)$dados['produto_normatizado'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="prodNorm">Produto consta do Anexo II da Portaria 52/2021</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Produto Utilizado</label>
                    <input type="text" class="form-control" name="produto_utilizado" value="<?php echo htmlspecialchars($dados['produto_utilizado'] ?? ''); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Motivo</label>
                    <input type="text" class="form-control" name="motivo" value="<?php echo htmlspecialchars($dados['motivo'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Animal</label>
                    <select class="form-select" name="animal_id">
                        <option value="">— Selecione (ou use lote) —</option>
                        <?php foreach ($animais as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (int)($dados['animal_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome_animal'] . ' (' . $a['especie'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lote</label>
                    <select class="form-select" name="lote_id">
                        <option value="">— Selecione (ou use animal) —</option>
                        <?php foreach ($lotes as $l): ?>
                            <option value="<?php echo $l['id']; ?>" <?php echo (int)($dados['lote_id'] ?? 0) === (int)$l['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['nome_lote']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Dose/Quantidade</label>
                    <input type="text" class="form-control" name="dose_quantidade" value="<?php echo htmlspecialchars($dados['dose_quantidade'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Via de Aplicação</label>
                    <input type="text" class="form-control" name="via_aplicacao" value="<?php echo htmlspecialchars($dados['via_aplicacao'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Responsável</label>
                    <input type="text" class="form-control" name="responsavel" value="<?php echo htmlspecialchars($dados['responsavel'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Período de Carência (horas)</label>
                    <input type="number" class="form-control" name="periodo_carencia_horas" min="0" value="<?php echo htmlspecialchars($dados['periodo_carencia_horas'] ?? ''); ?>">
                    <div class="form-text">Tratamento excepcional: mínimo 96h.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de Liberação Orgânica</label>
                    <input type="date" class="form-control" name="data_liberacao_organica" value="<?php echo htmlspecialchars($dados['data_liberacao_organica'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Aprovado pela OCS (excepcional)</label>
                    <select class="form-select" name="aprovado_ocs">
                        <option value="">—</option>
                        <option value="1" <?php echo ($dados['aprovado_ocs'] ?? '') === '1' ? 'selected' : ''; ?>>Sim</option>
                        <option value="0" <?php echo ($dados['aprovado_ocs'] ?? '0') === '0' ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nº do Tratamento (no ano)</label>
                    <input type="number" class="form-control" name="numero_tratamento" min="1" value="<?php echo htmlspecialchars($dados['numero_tratamento'] ?? 1); ?>">
                    <div class="form-text">Limite de 2 tratamentos/ano por animal.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="3"><?php echo htmlspecialchars($dados['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Registro</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>