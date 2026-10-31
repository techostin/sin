<?php
$page_title = "Controles de Sanidade Animal";
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

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($paClass->softDeleteSanidade($id)) {
        AuditLog::registrar('PA_SANIDADE_EXCLUIDA', 'controle_sanidade_animal', $id, 'Registro de sanidade excluído');
    }
    header('Location: ' . SITE_URL . '/producao-animal/sanidade');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$registros = $paClass->getSanidades($escopo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-heart-pulse text-success"></i> Controle de Sanidade</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade/novo" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Registro</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Art. 59-63 (Portaria 52/2021):</strong> tratamento excepcional (produto não
        previsto no Anexo II ou não orgânico) exige aprovação da OCS, período de carência <strong>dobrado</strong> e mínimo de
        96 horas, e limite de <strong>2 tratamentos por animal/ano</strong> (não vacinados).
    </div>

    <?php if (isset($_GET['cadastrado'])): ?><div class="alert alert-success alert-dismissible fade show">Registro cadastrado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success alert-dismissible fade show">Registro atualizado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Data</th><th>Animal/Lote</th><th>Tipo</th><th>Produto</th><th>Norma</th><th>Carência</th><th>Liberação</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$registros): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro de sanidade.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($r['data_tratamento'])); ?></td>
                                <td><?php echo htmlspecialchars($r['nome_animal'] ?? 'Lote: ' . ($r['nome_lote'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($r['tipo_tratamento']); ?></td>
                                <td><?php echo htmlspecialchars($r['produto_utilizado'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($r['produto_normatizado']): ?>
                                        <span class="badge bg-success">Anexo II</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Excepcional</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $r['periodo_carencia_horas'] !== null ? $r['periodo_carencia_horas'] . 'h' : '-'; ?></td>
                                <td><?php echo $r['data_liberacao_organica'] ? date('d/m/Y', strtotime($r['data_liberacao_organica'])) : '-'; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade/<?php echo $r['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade?excluir=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este registro?');"><i class="bi bi-trash"></i></a>
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