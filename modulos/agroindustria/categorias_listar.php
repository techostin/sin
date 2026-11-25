<?php
$page_title = "Categorias de Atividades";
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

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($categoriaClass->softDelete($id)) {
        AuditLog::registrar('AGRO_CATEGORIA_EXCLUIDA', 'agro_categorias_atividades', $id, 'Categoria de atividade excluída');
    }
    header('Location: ' . SITE_URL . '/agroindustria/categorias');
    exit;
}

$categorias = $categoriaClass->getAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-tags text-primary"></i> Categorias de Atividades</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/categoria/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Categoria
        </a>
    </div>

    <p class="text-muted">Categorias utilizadas para classificar as atividades diárias da agroindústria.</p>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Cor</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$categorias): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($categorias as $c): ?>
                            <tr>
                                <td><span class="badge" style="background-color:<?php echo htmlspecialchars($c['cor']); ?>;">&nbsp;</span></td>
                                <td><strong><?php echo htmlspecialchars($c['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['descricao'] ?? ''); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/categoria/<?php echo $c['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/categorias?excluir=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta categoria?');"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>