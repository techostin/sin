<?php
$page_title = "Ordens de Produção";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/OrdemProducao.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$opClass = new OrdemProducao();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $opClass->softDelete($id);
    AuditLog::registrar('ORDEM_PRODUCAO_EXCLUIDA', 'ordens_producao', $id, 'Ordem de produção excluída');
    header('Location: ' . SITE_URL . '/agroindustria/ordens');
    exit;
}

$ordens = $opClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

$statusBadge = [
    'APROVADO' => ['bg-success', 'Aprovado'],
    'ALERTA_FRAUDE' => ['bg-danger', 'Alerta de Fraude'],
    'EM_ANALISE' => ['bg-warning text-dark', 'Em análise'],
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-clipboard-check text-success"></i> Ordens de Produção</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/ordens/nova" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Ordem
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Lote Processado</th>
                            <th>Produto Final</th>
                            <th>Processamento</th>
                            <th>Qtde Final</th>
                            <th>% Orgânico</th>
                            <th>Status Auditoria</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ordens): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma ordem de produção.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ordens as $o): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($o['lote_processado_final']); ?></strong></td>
                                <td><?php echo htmlspecialchars($o['nome_produto_final']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($o['data_processamento'])); ?></td>
                                <td><?php echo number_format((float)$o['quantidade_final_obtida'], 2, ',', '.') . ' ' . htmlspecialchars($o['unidade_medida']); ?></td>
                                <td><?php echo $o['percentual_organico'] !== null ? number_format((float)$o['percentual_organico'], 1, ',', '.') . '%' : '—'; ?></td>
                                <td>
                                    <?php [$badge, $label] = $statusBadge[$o['status_auditoria']] ?? ['bg-secondary', $o['status_auditoria']]; ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/ordens/<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/ordens?excluir=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta ordem?');"><i class="bi bi-trash"></i></a>
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
