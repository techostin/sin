<?php
$page_title = "Novo Registro de Alimentação";
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
    if (trim($dados['data_alimentacao'] ?? '') === '') {
        $message = 'Informe a data da alimentação.';
        $message_type = 'danger';
    } else {
        // Validação art. 34-37: alimentação não orgânica é exceção (15%/20% MS)
        $origem = $dados['origem'] ?? 'ORGANICO';
        if ($origem === 'NAO_ORGANICO_AUTORIZADO' && empty($dados['observacoes'])) {
            $message = 'Para alimento de origem não orgânica, descreva a escassez/necessidade e a autorização no PMO.';
            $message_type = 'danger';
        } else {
            $dados['associado_id'] = $user->getAssociadoId();
            $dados['criado_por'] = (int)($_SESSION['user_id'] ?? 0);
            if ($id = $paClass->criarAlimentacao($dados)) {
                AuditLog::registrar('PA_ALIMENTACAO_CADASTRADA', 'controle_alimentacao_animal', $id, 'Alimentação ' . $dados['tipo_alimento'] . ' em ' . $dados['data_alimentacao']);
                header('Location: ' . SITE_URL . '/producao-animal/alimentacao?cadastrado=1');
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
        <h1><i class="bi bi-basket text-success"></i> Novo Registro de Alimentação</h1>
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
                    <input type="date" class="form-control" name="data_alimentacao" required value="<?php echo htmlspecialchars($dados['data_alimentacao'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Alimento <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipo_alimento" required>
                        <?php foreach (['FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($dados['tipo_alimento'] ?? '') === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Origem do Alimento <span class="text-danger">*</span></label>
                    <select class="form-select" name="origem" required>
                        <option value="ORGANICO" <?php echo (($dados['origem'] ?? 'ORGANICO') === 'ORGANICO') ? 'selected' : ''; ?>>Orgânico (própria ou outra unidade orgânica)</option>
                        <option value="EM_CONVERSAO" <?php echo ($dados['origem'] ?? '') === 'EM_CONVERSAO' ? 'selected' : ''; ?>>Em conversão</option>
                        <option value="NAO_ORGANICO_AUTORIZADO" <?php echo ($dados['origem'] ?? '') === 'NAO_ORGANICO_AUTORIZADO' ? 'selected' : ''; ?>>Não orgânico (exceção autorizada no PMO)</option>
                    </select>
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
                    <label class="form-label">Produto Utilizado</label>
                    <input type="text" class="form-control" name="produto_utilizado" value="<?php echo htmlspecialchars($dados['produto_utilizado'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade (kg)</label>
                    <input type="number" step="0.01" class="form-control" name="quantidade_kg" min="0" value="<?php echo htmlspecialchars($dados['quantidade_kg'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">% Matéria Seca</label>
                    <input type="number" step="0.01" class="form-control" name="percentual_materia_seca" min="0" max="100" value="<?php echo htmlspecialchars($dados['percentual_materia_seca'] ?? ''); ?>">
                    <div class="form-text">Limite não orgânico: 15% (ruminantes) / 20% (não ruminantes).</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações (escassez/necessidade/autorização PMO)</label>
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