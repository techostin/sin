<?php
$page_title = "Ambientes de Criação";
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
    if ($paClass->softDeleteAmbiente($id)) {
        AuditLog::registrar('PA_AMBIENTE_EXCLUIDO', 'ambiente_criacao_animal', $id, 'Ambiente de criação excluído');
    }
    header('Location: ' . SITE_URL . '/producao-animal/ambientes');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$ambientes = $paClass->getAmbientes($escopo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-house-heart text-success"></i> Ambientes de Criação</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/ambiente/novo" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Ambiente</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Art. 38-44 (Portaria 52/2021):</strong> os animais devem ter acesso a
        áreas externas, ar livre, sombreamento/abrigo e sistemas de criação que respeitem densidades máximas e bem-estar.
    </div>

    <?php if (isset($_GET['cadastrado'])): ?><div class="alert alert-success alert-dismissible fade show">Ambiente cadastrado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success alert-dismissible fade show">Ambiente atualizado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Instalação</th><th>Espécie</th><th>Sistema</th><th>Área (m²)</th><th>Animais</th><th>Dens. Ref.</th><th>Sombra/Abrigo</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$ambientes): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum ambiente cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ambientes as $a): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($a['nome_instalacao']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($a['tipo_instalacao'] ?? ''); ?></small></td>
                                <td><?php echo htmlspecialchars($a['especie'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($a['sistema'] ?? '-'); ?></td>
                                <td><?php echo $a['area_total_m2'] !== null ? htmlspecialchars($a['area_total_m2']) . ' m²' : '-'; ?></td>
                                <td><?php echo $a['quantidade_animais'] !== null ? htmlspecialchars($a['quantidade_animais']) : '-'; ?></td>
                                <td><?php echo $a['densidade_referencia'] !== null ? htmlspecialchars($a['densidade_referencia']) : '-'; ?></td>
                                <td><?php echo $a['tem_sombreamento_abrigo'] ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-secondary">Não</span>'; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/ambiente/<?php echo $a['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/ambientes?excluir=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este ambiente?');"><i class="bi bi-trash"></i></a>
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