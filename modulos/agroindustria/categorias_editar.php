<?php
$page_title = "Editar Categoria de Atividade";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroCategoriaAtividade.php';
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
    header('Location: ' . SITE_URL . '/agroindustria/categorias');
    exit;
}

$categoriaClass = new AgroCategoriaAtividade();
$categoria = $categoriaClass->getById($id);
if (!$categoria) {
    header('Location: ' . SITE_URL . '/agroindustria/categorias');
    exit;
}

// Anti-IDOR: a categoria pode pertencer a um associado (agro_categorias_atividades.associado_id).
// Admin/Gestor editam qualquer uma; nível 3 só as do próprio associado.
// Categorias globais (associado_id nulo) ficam restritas à equipe.
$donoCategoria = (int) ($categoria['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoCategoria <= 0 || $donoCategoria !== (int) $user->getAssociadoIdResolvido())) {
    echo "<div class='alert alert-danger m-4'>Acesso negado: esta categoria não pertence a você.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['nome'] ?? '') === '') {
        $message = 'Informe o nome da categoria.';
        $message_type = 'danger';
    } elseif ($categoriaClass->update($id, $_POST)) {
        AuditLog::registrar('AGRO_CATEGORIA_ATUALIZADA', 'agro_categorias_atividades', $id, 'Categoria: ' . $_POST['nome']);
        header('Location: ' . SITE_URL . '/agroindustria/categorias?atualizada=1');
        exit;
    } else {
        $message = 'Erro ao atualizar a categoria. Tente novamente.';
        $message_type = 'danger';
    }
    $categoria = array_merge($categoria, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-tags text-primary"></i> Editar Categoria de Atividade</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/categorias" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($categoria['nome']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cor</label>
                    <input type="color" class="form-control form-control-color" name="cor" value="<?php echo htmlspecialchars($categoria['cor'] ?? '#0d6efd'); ?>" style="width:80px;height:38px;">
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($categoria['descricao'] ?? ''); ?></textarea>
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