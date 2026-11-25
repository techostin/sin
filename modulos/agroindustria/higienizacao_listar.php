<?php
$page_title = "Controle de Higienização";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ControleHigienizacao.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$higClass = new ControleHigienizacao();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $higClass->softDelete($id);
    AuditLog::registrar('HIGIENIZACAO_EXCLUIDA', 'controle_higienizacao', $id, 'Registro de higienização excluído');
    header('Location: ' . SITE_URL . '/agroindustria/higienizacao');
    exit;
}

$registros = $higClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-droplet text-info"></i> Controle de Higienização</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/higienizacao/nova" class="btn btn-info text-white">
            <i class="bi bi-plus-circle"></i> Novo Registro
        </a>
    </div>

    <div class="alert alert-light border">
        <i class="bi bi-info-circle text-info"></i> <strong>RF08:</strong> nenhuma ordem de produção pode ser lançada sem higienização concluída do equipamento/linha nas últimas 24 horas.
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Equipamento / Linha</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Sanitizante</th>
                            <th>Responsável</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$registros): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro de higienização.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($registros as $h): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($h['equipamento_linha']); ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($h['data_hora_inicio'])); ?></td>
                                <td>
                                    <?php echo $h['data_hora_fim'] ? date('d/m/Y H:i', strtotime($h['data_hora_fim'])) : '<span class="badge bg-warning text-dark">Em andamento</span>'; ?>
                                </td>
                                <td><?php echo htmlspecialchars($h['sanitizante_utilizado']); ?></td>
                                <td><?php echo htmlspecialchars($h['responsavel_nome']); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/higienizacao?excluir=<?php echo $h['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este registro?');"><i class="bi bi-trash"></i></a>
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
