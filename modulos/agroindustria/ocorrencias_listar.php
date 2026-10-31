<?php
$page_title = "Ocorrências da Agroindústria";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroOcorrencia.php';
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

$ocorrenciaClass = new AgroOcorrencia();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($ocorrenciaClass->softDelete($id)) {
        AuditLog::registrar('AGRO_OCORRENCIA_EXCLUIDA', 'agro_ocorrencias', $id, 'Ocorrência excluída');
    }
    header('Location: ' . SITE_URL . '/agroindustria/ocorrencias');
    exit;
}

$ocorrencias = $ocorrenciaClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

$severidadeBadge = [
    'Baixa'  => 'bg-success',
    'Média'  => 'bg-warning text-dark',
    'Alta'   => 'bg-danger',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-exclamation-triangle text-warning"></i> Ocorrências</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/ocorrencia/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nova Ocorrência
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Ocorrência</th>
                            <th>Tipo</th>
                            <th>Setor</th>
                            <th>Severidade</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ocorrencias): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma ocorrência registrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ocorrencias as $o): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($o['data_ocorrencia'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($o['titulo']); ?></strong>
                                    <?php if (!empty($o['descricao'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($o['descricao'], 0, 80)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($o['tipo']); ?></td>
                                <td>
                                    <?php if (!empty($o['setor_nome'])): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($o['setor_nome']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $severidadeBadge[$o['severidade']] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($o['severidade']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $o['status'] === 'Aberta' ? 'bg-danger' : ($o['status'] === 'Em Tratamento' ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                        <?php echo htmlspecialchars($o['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/ocorrencia/<?php echo $o['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/agroindustria/ocorrencias?excluir=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta ocorrência?');"><i class="bi bi-trash"></i></a>
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