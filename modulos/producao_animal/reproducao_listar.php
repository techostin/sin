<?php
$page_title = "Controle de Reprodução Animal";
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
    if ($paClass->softDeleteReproducao($id)) {
        AuditLog::registrar('PA_REPRODUCAO_EXCLUIDA', 'controle_reproducao_animal', $id, 'Evento reprodutivo excluído');
    }
    header('Location: ' . SITE_URL . '/producao-animal/reproducao');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$registros = $paClass->getReproducoes($escopo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-stars text-success"></i> Controle de Reprodução</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao/novo" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Evento</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Aleitamento natural mínimo (Portaria 52/2021):</strong> bovinos/bubalinos
        90 dias; ovinos/caprinos 28 dias; suínos 45 dias — salvo risco à mãe ou à cria.
    </div>

    <?php if (isset($_GET['cadastrado'])): ?><div class="alert alert-success alert-dismissible fade show">Evento cadastrado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success alert-dismissible fade show">Evento atualizado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Data</th><th>Animal (mãe)</th><th>Evento</th><th>Reprodutor</th><th>Vivos/Natimortos</th><th>Aleitamento</th><th>Prev. Parto</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$registros): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum evento reprodutivo registrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($r['data_evento'])); ?></td>
                                <td><?php echo htmlspecialchars($r['nome_animal'] ?? '-'); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($r['tipo_evento']); ?></span></td>
                                <td><?php echo htmlspecialchars($r['macho_reprodutor_id'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($r['nascidos_vivos'] !== null): ?>
                                        <?php echo $r['nascidos_vivos'] . ' vivos'; ?><?php if ($r['natimortos'] > 0): ?> / <?php echo $r['natimortos']; ?> natimortos<?php endif; ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td><?php echo $r['periodo_aleitamento_dias'] !== null ? $r['periodo_aleitamento_dias'] . ' dias' : '-'; ?></td>
                                <td><?php echo $r['previsao_parto'] ? date('d/m/Y', strtotime($r['previsao_parto'])) : '-'; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao/<?php echo $r['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao?excluir=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este evento?');"><i class="bi bi-trash"></i></a>
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