<?php
$page_title = "Receitas Agroindústria";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ReceitaAgroindustria.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$receitaClass = new ReceitaAgroindustria();

// Exclusão (soft delete)
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($receitaClass->softDelete($id)) {
        AuditLog::registrar('RECEITA_EXCLUIDA', 'receitas_agroindustria', $id, 'Receita excluída');
    }
    header('Location: ' . SITE_URL . '/agroindustria/receitas');
    exit;
}

$receitas = $receitaClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

$seloLabels = [
    'PRODUTO_ORGANICO_BRASIL' => 'Produto Orgânico Brasil (≥95%)',
    'INGREDIENTES_ORGANICOS' => 'Ingredientes Orgânicos (70–94%)',
    'SEM_SELO' => 'Sem selo (<70%)',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-journal-richtext text-success"></i> Receitas Agroindústria</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/receitas/nova" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Receita
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Produto Final</th>
                            <th>% Orgânico</th>
                            <th>Selo Autorizado</th>
                            <th>Ingredientes</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$receitas): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma receita cadastrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($receitas as $r): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($r['nome_produto_final']); ?></strong>
                                    <?php if (!empty($r['descricao'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($r['descricao'], 0, 60)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $r['percentual_organico_calculado'] >= 95 ? 'bg-success' : ($r['percentual_organico_calculado'] >= 70 ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                        <?php echo number_format((float)$r['percentual_organico_calculado'], 1, ',', '.'); ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $r['selo_autorizado'] === 'PRODUTO_ORGANICO_BRASIL' ? 'bg-success' : ($r['selo_autorizado'] === 'INGREDIENTES_ORGANICOS' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($seloLabels[$r['selo_autorizado']] ?? $r['selo_autorizado']); ?>
                                    </span>
                                </td>
                                <td><?php echo (int)$r['total_ingredientes']; ?></td>
                                <td>
                                    <span class="badge <?php echo $r['status'] === 'Ativa' ? 'bg-success' : ($r['status'] === 'Rascunho' ? 'bg-secondary' : 'bg-danger'); ?>">
                                        <?php echo htmlspecialchars($r['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/receitas/<?php echo $r['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/receitas?excluir=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta receita?');"><i class="bi bi-trash"></i></a>
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
