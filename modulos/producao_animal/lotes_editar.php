<?php
$page_title = "Editar Lote de Animais";
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
    header('Location: ' . SITE_URL . '/producao-animal/lotes');
    exit;
}

$paClass = new ProducaoAnimal();
$lote = $paClass->getLoteById($id);
if (!$lote) {
    header('Location: ' . SITE_URL . '/producao-animal/lotes');
    exit;
}

// Anti-IDOR: o lote de animais pertence a um associado (animal_lotes.associado_id).
// Admin/Gestor editam qualquer lote; nível 3 só os do próprio associado.
$donoLote = (int) ($lote['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoLote <= 0 || $donoLote !== (int) $user->getAssociadoIdResolvido())) {
    echo "<div class='alert alert-danger m-4'>Acesso negado: este lote pertence a outro produtor.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['nome_lote'] ?? '') === '') {
        $message = 'Informe o nome do lote.';
        $message_type = 'danger';
    } elseif ($paClass->atualizarLote($id, $_POST)) {
        AuditLog::registrar('PA_LOTE_ATUALIZADO', 'animal_lotes', $id, 'Lote: ' . $_POST['nome_lote']);
        header('Location: ' . SITE_URL . '/producao-animal/lotes?atualizado=1');
        exit;
    } else {
        $message = 'Erro ao atualizar o lote. Tente novamente.';
        $message_type = 'danger';
    }
    $lote = array_merge($lote, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-boxes text-success"></i> Editar Lote de Animais</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/lotes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Lote <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_lote" required value="<?php echo htmlspecialchars($lote['nome_lote']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" name="especie">
                        <option value="">Selecione...</option>
                        <?php foreach (['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA'] as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo ($lote['especie'] ?? '') === $e ? 'selected' : ''; ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <input type="text" class="form-control" name="categoria" placeholder="ex.: recria, terminação, matrizes" value="<?php echo htmlspecialchars($lote['categoria'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade (cabeças)</label>
                    <input type="number" class="form-control" name="quantidade" min="0" value="<?php echo htmlspecialchars($lote['quantidade'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($lote['descricao'] ?? ''); ?></textarea>
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