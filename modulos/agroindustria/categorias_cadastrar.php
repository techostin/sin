<?php
$page_title = "Nova Categoria de Atividade";
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

$categoriaClass = new AgroCategoriaAtividade();

$message = '';
$message_type = '';
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['nome'] ?? '') === '') {
        $message = 'Informe o nome da categoria.';
        $message_type = 'danger';
    } else {
        $dados['associado_id'] = $user->getAssociadoId();
        $dados['cor'] = !empty($dados['cor']) ? $dados['cor'] : '#0d6efd';
        if ($categoriaClass->create($dados)) {
            AuditLog::registrar('AGRO_CATEGORIA_CADASTRADA', 'agro_categorias_atividades', null, 'Categoria: ' . $dados['nome']);
            header('Location: ' . SITE_URL . '/agroindustria/categorias?cadastrada=1');
            exit;
        }
        $message = 'Erro ao salvar a categoria. Tente novamente.';
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-tags text-primary"></i> Nova Categoria de Atividade</h1>
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
                    <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($dados['nome'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cor</label>
                    <input type="color" class="form-control form-control-color" name="cor" value="<?php echo htmlspecialchars($dados['cor'] ?? '#0d6efd'); ?>" style="width:80px;height:38px;">
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($dados['descricao'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Categoria</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>