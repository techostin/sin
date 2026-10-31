<?php
$page_title = "Manejo Animal";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ManejoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$animalClass = new ManejoAnimal();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $animalClass->softDelete($id);
    AuditLog::registrar('ANIMAL_EXCLUIDO', 'animais', $id, 'Animal excluído');
    header('Location: ' . SITE_URL . '/manejo-animal');
    exit;
}

$animais = $animalClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null));

$origemBadge = [
    'ORGANICO' => 'bg-success',
    'EM_CONVERSAO' => 'bg-warning text-dark',
    'CONVENCIONAL' => 'bg-secondary',
];
$statusBadge = [
    'ATIVO' => 'bg-success',
    'VENDIDO' => 'bg-info',
    'ABATIDO' => 'bg-secondary',
    'MORTO' => 'bg-danger',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-egg-fill text-success"></i> Manejo Animal (RF05)</h1>
        <a href="<?php echo SITE_URL; ?>/manejo-animal/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Cadastrar Animal
        </a>
    </div>

    <div class="alert alert-light border">
        <i class="bi bi-info-circle text-info"></i> Registro de histórico clínico, calendário de vacinação obrigatória e controle de alimentação (exigência de ração orgânica ou limites de conversão — Portaria MAPA nº 52/2021).
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Identificação</th>
                            <th>Espécie / Raça</th>
                            <th>Lote</th>
                            <th>Origem</th>
                            <th>Status</th>
                            <th>Registros</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$animais): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum animal cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($animais as $a): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($a['nome_animal']); ?></strong>
                                    <?php if (!empty($a['identificacao'])): ?>
                                        <br><small class="text-muted">Brinco/ID: <?php echo htmlspecialchars($a['identificacao']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($a['especie']); ?><?php echo $a['raca'] ? ' — ' . htmlspecialchars($a['raca']) : ''; ?><?php echo $a['sexo'] ? ' (' . $a['sexo'] . ')' : ''; ?></td>
                                <td><?php echo htmlspecialchars($a['lote_nome'] ?? '—'); ?></td>
                                <td><span class="badge <?php echo $origemBadge[$a['origem_conversao']] ?? 'bg-secondary'; ?>"><?php echo $a['origem_conversao']; ?></span></td>
                                <td><span class="badge <?php echo $statusBadge[$a['status']] ?? 'bg-secondary'; ?>"><?php echo $a['status']; ?></span></td>
                                <td><?php echo (int)$a['total_registros']; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/manejo-animal/<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver e registrar"><i class="bi bi-eye"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/manejo-animal/<?php echo $a['id']; ?>/editar" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/manejo-animal?excluir=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este animal?');"><i class="bi bi-trash"></i></a>
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
