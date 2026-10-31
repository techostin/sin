<?php
$page_title = "Setores da Agroindústria";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroSetor.php';
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

$setorClass = new AgroSetor();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($setorClass->softDelete($id)) {
        AuditLog::registrar('AGRO_SETOR_EXCLUIDO', 'agro_setores', $id, 'Setor de agroindústria excluído');
    }
    header('Location: ' . SITE_URL . '/agroindustria/setores');
    exit;
}

$setores = $setorClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-diagram-3 text-success"></i> Setores da Agroindústria</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/setor/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Novo Setor
        </a>
    </div>

    <p class="text-muted">Os setores substituem as lavouras do Caderno de Campo no contexto da agroindústria (ex.: recebimento, processamento, embalagem, expedição).</p>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Setor</th>
                            <th>Descrição</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$setores): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Nenhum setor cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($setores as $s): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($s['nome']); ?></strong></td>
                                <td><?php echo htmlspecialchars($s['descricao'] ?? ''); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/setor/<?php echo $s['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/setores?excluir=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este setor?');"><i class="bi bi-trash"></i></a>
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