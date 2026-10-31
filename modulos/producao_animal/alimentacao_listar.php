<?php
$page_title = "Controles de Alimentação Animal";
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
    if ($paClass->softDeleteAlimentacao($id)) {
        AuditLog::registrar('PA_ALIMENTACAO_EXCLUIDA', 'controle_alimentacao_animal', $id, 'Registro de alimentação excluído');
    }
    header('Location: ' . SITE_URL . '/producao-animal/alimentacao');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$registros = $paClass->getAlimentacoes($escopo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-basket text-success"></i> Controle de Alimentação</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao/novo" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Registro</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <strong>Art. 34-37 (Portaria 52/2021):</strong> a alimentação deve ser
        predominantemente da própria unidade orgânica ou de outra unidade orgânica. Em escassez, com PMO aprovado pela
        OCS, admite-se até 15% de matéria seca (ruminantes) ou 20% de matéria seca (não ruminantes) de origem não orgânica.
    </div>

    <?php if (isset($_GET['cadastrado'])): ?><div class="alert alert-success alert-dismissible fade show">Registro cadastrado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success alert-dismissible fade show">Registro atualizado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Data</th><th>Animal/Lote</th><th>Tipo</th><th>Produto</th><th>Qtd (kg)</th><th>% MS</th><th>Origem</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$registros): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum registro de alimentação.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($r['data_alimentacao'])); ?></td>
                                <td><?php echo htmlspecialchars($r['nome_animal'] ?? 'Lote: ' . ($r['nome_lote'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($r['tipo_alimento']); ?></td>
                                <td><?php echo htmlspecialchars($r['produto_utilizado'] ?? '-'); ?></td>
                                <td><?php echo $r['quantidade_kg'] !== null ? htmlspecialchars($r['quantidade_kg']) : '-'; ?></td>
                                <td><?php echo $r['percentual_materia_seca'] !== null ? htmlspecialchars($r['percentual_materia_seca']) . '%' : '-'; ?></td>
                                <td>
                                    <?php
                                    $cor = ['ORGANICO' => 'success', 'EM_CONVERSAO' => 'warning', 'NAO_ORGANICO_AUTORIZADO' => 'info'];
                                    $lbl = ['ORGANICO' => 'Orgânica', 'EM_CONVERSAO' => 'Conversão', 'NAO_ORGANICO_AUTORIZADO' => 'Não orgânica autorizada'];
                                    ?>
                                    <span class="badge bg-<?php echo $cor[$r['origem']] ?? 'secondary'; ?>"><?php echo $lbl[$r['origem']] ?? $r['origem']; ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao/<?php echo $r['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao?excluir=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este registro?');"><i class="bi bi-trash"></i></a>
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