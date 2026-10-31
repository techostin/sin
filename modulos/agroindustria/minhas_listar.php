<?php
$page_title = "Minhas Agroindústrias";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Agroindustria.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$agroClass = new Agroindustria();
$isGestor = $user->hasPermission(NIVEL_GESTOR);
$filtroAssociado = $isGestor ? null : ($user->getAssociadoId() ?? null);

// Exclusão (soft delete)
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $item = $agroClass->getById($id);
    if ($item && ($isGestor || (int)$item['associado_id'] === (int)$filtroAssociado)) {
        $agroClass->softDelete($id);
        AuditLog::registrar('AGROINDUSTRIA_EXCLUIDA', 'agroindustrias', $id, 'Agroindústria excluída: ' . ($item['nome_fantasia'] ?? ''));
    }
    header('Location: ' . SITE_URL . '/agroindustria/minhas');
    exit;
}

$agroindustrias = $agroClass->getAll($filtroAssociado);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-buildings text-success"></i> Agroindústrias</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/minha/nova" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Agroindústria
        </a>
    </div>

    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i>
        <?php echo $isGestor
            ? 'Cadastro de todas as agroindústrias do sistema.'
            : 'Você pode ser somente agroindústria ou agricultor <strong>com</strong> agroindústria. Cadastre aqui as suas unidades de processamento.'; ?>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$agroindustrias): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-buildings display-4 d-block mb-2"></i>
                    Nenhuma agroindústria cadastrada.
                    <a href="<?php echo SITE_URL; ?>/agroindustria/minha/nova" class="d-block mt-2">Cadastrar agora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nome Fantasia</th>
                                <th>Razão Social / CNPJ</th>
                                <th>Localização</th>
                                <th>Responsável Técnico</th>
                                <th>Status</th>
                                <?php if ($isGestor): ?><th>Associado</th><?php endif; ?>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agroindustrias as $a): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($a['nome_fantasia'] ?: ($a['razao_social'] ?: '—')); ?></strong></td>
                                    <td>
                                        <?php if (!empty($a['razao_social'])): ?>
                                            <div><?php echo htmlspecialchars($a['razao_social']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($a['cnpj'])): ?>
                                            <small class="text-muted">CNPJ: <?php echo htmlspecialchars($a['cnpj']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $cidade = trim(($a['cidade'] ?? '') . ($a['estado'] ? ' - ' . $a['estado'] : ''));
                                        echo $cidade ? htmlspecialchars($cidade) : '—';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['responsavel_tecnico'] ?: '—'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $a['status'] === 'Ativa' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo htmlspecialchars($a['status']); ?>
                                        </span>
                                    </td>
                                    <?php if ($isGestor): ?>
                                        <td><?php echo htmlspecialchars($a['associado_nome'] ?? '—'); ?></td>
                                    <?php endif; ?>
                                    <td class="text-end">
                                        <a href="<?php echo SITE_URL; ?>/agroindustria/minha/<?php echo $a['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <a href="<?php echo SITE_URL; ?>/agroindustria/minhas?excluir=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta agroindústria?');"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
