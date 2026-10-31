<?php
$page_title = "Editar Setor";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroSetor.php';
require_once __DIR__ . '/../../classes/Agroindustria.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

if (!$user->temAgroindustria()) {
    echo "<div class='alert alert-danger m-4'>Acesso restrito a produtores de agroindústria.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . SITE_URL . '/agroindustria/setores');
    exit;
}

$setorClass    = new AgroSetor();
$agroClass     = new Agroindustria();
$isGestor      = $user->hasPermission(NIVEL_GESTOR);
$associadoId   = $user->getAssociadoId();

$setor = $setorClass->getById($id);
if (!$setor) {
    header('Location: ' . SITE_URL . '/agroindustria/setores');
    exit;
}

if (!$isGestor && (int)$setor['associado_id'] !== (int)$associadoId) {
    echo "<div class='alert alert-danger m-4'>Você não tem permissão para editar este setor.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$agroindustrias = $agroClass->getAll($isGestor ? null : $associadoId);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['nome'] ?? '') === '') {
        $message = 'Informe o nome do setor.';
        $message_type = 'danger';
    } elseif ($setorClass->update($id, $_POST)) {
        AuditLog::registrar('AGRO_SETOR_ATUALIZADO', 'agro_setores', $id, 'Setor: ' . $_POST['nome']);
        header('Location: ' . SITE_URL . '/agroindustria/setores?atualizado=1');
        exit;
    } else {
        $message = 'Erro ao atualizar o setor. Tente novamente.';
        $message_type = 'danger';
    }
    $setor = array_merge($setor, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-diagram-3 text-success"></i> Editar Setor</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/setores" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Setor <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($setor['nome']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agroindústria</label>
                    <select class="form-select" name="agroindustria_id">
                        <option value="">Nenhuma</option>
                        <?php foreach ($agroindustrias as $ag): ?>
                            <option value="<?php echo $ag['id']; ?>" <?php echo ($setor['agroindustria_id'] == $ag['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ag['nome_fantasia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($setor['descricao'] ?? ''); ?></textarea>
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