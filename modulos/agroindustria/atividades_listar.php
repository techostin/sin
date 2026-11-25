<?php
$page_title = "Atividades Diárias da Agroindústria";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroAtividadeDiaria.php';
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

$atividadeClass = new AgroAtividadeDiaria();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($atividadeClass->softDelete($id)) {
        AuditLog::registrar('AGRO_ATIVIDADE_EXCLUIDA', 'agro_atividades_diarias', $id, 'Atividade diária excluída');
    }
    header('Location: ' . SITE_URL . '/agroindustria/atividades');
    exit;
}

$atividades = $atividadeClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-calendar-check text-primary"></i> Atividades Diárias</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/atividade/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Atividade
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Atividade</th>
                            <th>Setor</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$atividades): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma atividade registrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($atividades as $a): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($a['data_atividade'])); ?>
                                    <?php if (!empty($a['hora_inicio'])): ?>
                                        <br><small class="text-muted"><?php echo substr($a['hora_inicio'], 0, 5); ?><?php echo !empty($a['hora_fim']) ? ' – ' . substr($a['hora_fim'], 0, 5) : ''; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($a['nome']); ?></strong>
                                    <?php if (!empty($a['descricao'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($a['descricao'], 0, 80)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['setor_nome'])): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($a['setor_nome']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['categoria_nome'])): ?>
                                        <span class="badge" style="background-color:<?php echo htmlspecialchars($a['categoria_cor']); ?>;"><?php echo htmlspecialchars($a['categoria_nome']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $a['status'] === 'Concluída' ? 'bg-success' : ($a['status'] === 'Em Andamento' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($a['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/atividade/<?php echo $a['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/atividades?excluir=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta atividade?');"><i class="bi bi-trash"></i></a>
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