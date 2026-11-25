<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/PlanoMensalidade.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$planos = (new PlanoMensalidade())->getAll();

$page_title = "Planos de Mensalidade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

$tipoLabel = [
    'mensalidade' => 'Mensalidade',
    'anuidade'    => 'Anuidade',
    'joia'        => 'Jóia de entrada',
];
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-tags text-primary me-2"></i>Planos de Mensalidade</h1>
            <span class="text-muted">Tabelas de valores: mensalidade, anuidade e jóia de entrada por associação.</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/plano/novo" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Novo Plano</a>
            <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table id="planosTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Associação</th>
                        <th>Valor</th>
                        <th>Dia Venc.</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($planos)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Nenhum plano cadastrado.</td></tr>
                <?php else: foreach ($planos as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['nome']); ?></td>
                        <td><?php echo htmlspecialchars($tipoLabel[$p['tipo']] ?? $p['tipo']); ?></td>
                        <td><?php echo $p['associacao_nome'] ? htmlspecialchars($p['associacao_nome']) : '<span class="badge bg-secondary">Todas</span>'; ?></td>
                        <td>R$ <?php echo number_format((float)$p['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo (int) $p['dia_vencimento']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo (int)$p['ativo'] === 1 ? 'success' : 'secondary'; ?>">
                                <?php echo (int)$p['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?php echo SITE_URL; ?>/plano/<?php echo $p['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form action="<?php echo SITE_URL; ?>/plano/<?php echo $p['id']; ?>/excluir" method="post" style="display:inline;" onsubmit="return confirm('Excluir este plano? Os valores já lançados são mantidos.')">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <button class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#planosTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "responsive": true,
        "columnDefs": [{ "targets": -1, "orderable": false }]
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>