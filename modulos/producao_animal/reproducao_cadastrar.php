<?php
$page_title = "Novo Evento Reprodutivo";
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

$message = '';
$message_type = '';
$dados = $_POST;
$tipoEvento = $dados['tipo_evento'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['data_evento'] ?? '') === '') {
        $message = 'Informe a data do evento.';
        $message_type = 'danger';
    } else {
        // Validação aleitamento mínimo (art. norma)
        $aleitamento = !empty($dados['periodo_aleitamento_dias']) ? (int)$dados['periodo_aleitamento_dias'] : 0;
        if ($tipoEvento === 'DESMAME' && $aleitamento > 0) {
            $especie = !empty($dados['especie']) ? strtoupper($dados['especie']) : '';
            $min = 90;
            if (in_array($especie, ['OVINOS', 'CAPRINOS'])) $min = 28;
            if ($especie === 'SUINOS') $min = 45;
            if (!$especie) $min = 0;
            if ($min > 0 && $aleitamento < $min) {
                $message = "Aleitamento abaixo do mínimo legal para a espécie ($min dias). Justifique em observações. (Bovinos/bubalinos 90, ovinos/caprinos 28, suínos 45 dias.)";
                $message_type = 'danger';
            }
        }
        if (!$message) {
            $dados['associado_id'] = $user->getAssociadoId();
            $dados['criado_por'] = (int)($_SESSION['user_id'] ?? 0);
            if ($id = $paClass->criarReproducao($dados)) {
                AuditLog::registrar('PA_REPRODUCAO_CADASTRADA', 'controle_reproducao_animal', $id, $dados['tipo_evento'] . ' em ' . $dados['data_evento']);
                header('Location: ' . SITE_URL . '/producao-animal/reproducao?cadastrado=1');
                exit;
            }
            $message = 'Erro ao salvar o evento. Tente novamente.';
            $message_type = 'danger';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-stars text-success"></i> Novo Evento Reprodutivo</h1>
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
                    <select class="form-select" name="tipo_evento" id="tipoEvento" required>
                        <?php foreach (['COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $tipoEvento === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data do Evento <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_evento" required value="<?php echo htmlspecialchars($dados['data_evento'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Animal (mãe/fêmea)</label>
                    <select class="form-select" name="animal_id">
                        <option value="">— Selecione —</option>
                        <?php foreach ($animais as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (int)($dados['animal_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome_animal'] . ' (' . $a['especie'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Identificação do Reprodutor</label>
                    <input type="text" class="form-control" name="macho_reprodutor_id" value="<?php echo htmlspecialchars($dados['macho_reprodutor_id'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Previsão de Parto</label>
                    <input type="date" class="form-control" name="previsao_parto" value="<?php echo htmlspecialchars($dados['previsao_parto'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Grupo Reprodutivo</label>
                    <input type="text" class="form-control" name="grupo_reprodutivo" value="<?php echo htmlspecialchars($dados['grupo_reprodutivo'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nascidos Vivos</label>
                    <input type="number" class="form-control" name="nascidos_vivos" min="0" value="<?php echo htmlspecialchars($dados['nascidos_vivos'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Natimortos</label>
                    <input type="number" class="form-control" name="natimortos" min="0" value="<?php echo htmlspecialchars($dados['natimortos'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Período de Aleitamento (dias)</label>
                    <input type="number" class="form-control" name="periodo_aleitamento_dias" min="0" value="<?php echo htmlspecialchars($dados['periodo_aleitamento_dias'] ?? ''); ?>">
                    <div class="form-text">Mín. 90 (bov./bubal.), 28 (ov./cap.), 45 (suínos).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" name="especie">
                        <option value="">—</option>
                        <?php foreach (['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS'] as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo ($dados['especie'] ?? '') === $e ? 'selected' : ''; ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="3"><?php echo htmlspecialchars($dados['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Evento</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>