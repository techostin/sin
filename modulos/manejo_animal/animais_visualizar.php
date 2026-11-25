<?php
$page_title = "Animal — Manejo";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ManejoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$animalClass = new ManejoAnimal();
$animal = $animalClass->getById($id);
if (!$animal) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/menu.php';
    echo '<div class="alert alert-warning m-3">Animal não encontrado.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Verificação IDOR
if (!$user->hasPermission(NIVEL_GESTOR) && (int)$animal['associado_id'] !== (int)$user->getAssociadoId()) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/menu.php';
    echo '<div class="alert alert-danger m-3">Acesso Negado: este animal pertence a outro produtor.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$message = '';
$message_type = '';

// Remover registro
if (isset($_GET['remover_registro']) && is_numeric($_GET['remover_registro'])) {
    $animalClass->removerRegistro((int)$_GET['remover_registro']);
    AuditLog::registrar('MANEJO_REGISTRO_EXCLUIDO', 'manejo_animal_registros', (int)$_GET['remover_registro'], 'Registro removido');
    header('Location: ' . SITE_URL . '/manejo-animal/' . $id);
    exit;
}

// Adicionar registro (clínico/vacinação/alimentação)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'animal_id' => $id,
        'tipo_registro' => $_POST['tipo_registro'] ?? '',
        'data_registro' => $_POST['data_registro'] ?? '',
        'descricao' => $_POST['descricao'] ?? '',
        'produto_utilizado' => $_POST['produto_utilizado'] ?? '',
        'dose_quantidade' => $_POST['dose_quantidade'] ?? '',
        'responsavel' => $_POST['responsavel'] ?? '',
        'observacoes' => $_POST['observacoes'] ?? '',
        'criado_por' => (int)$_SESSION['user_id'],
    ];

    if (empty($dados['tipo_registro']) || empty($dados['data_registro'])) {
        $message = 'Informe o tipo de registro e a data.';
        $message_type = 'danger';
    } else {
        $regId = $animalClass->adicionarRegistro($dados);
        AuditLog::registrar('MANEJO_REGISTRO_ADICIONADO', 'manejo_animal_registros', $regId, 'Tipo: ' . $dados['tipo_registro']);
        header('Location: ' . SITE_URL . '/manejo-animal/' . $id . '?registrado=1');
        exit;
    }
}

$registros = $animalClass->getRegistros($id);

$tipoLabels = [
    'CLINICO' => ['Histórico Clínico', 'bg-info text-white', 'bi-activity'],
    'VACINACAO' => ['Vacinação', 'bg-primary text-white', 'bi-syringe'],
    'ALIMENTACAO' => ['Alimentação', 'bg-success text-white', 'bi-egg-fried'],
];
$origemBadge = ['ORGANICO' => 'bg-success', 'EM_CONVERSAO' => 'bg-warning text-dark', 'CONVENCIONAL' => 'bg-secondary'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1>
            <i class="bi bi-egg-fill text-success"></i> <?php echo htmlspecialchars($animal['nome_animal']); ?>
            <span class="badge <?php echo $origemBadge[$animal['origem_conversao']] ?? 'bg-secondary'; ?> fs-6 align-middle"><?php echo $animal['origem_conversao']; ?></span>
        </h1>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/manejo-animal/<?php echo $id; ?>/editar" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
            <a href="<?php echo SITE_URL; ?>/manejo-animal" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-sm-3"><label class="text-muted small d-block">Espécie</label><strong><?php echo htmlspecialchars($animal['especie']); ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Raça</label><strong><?php echo htmlspecialchars($animal['raca'] ?? '—'); ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Identificação</label><strong><?php echo htmlspecialchars($animal['identificacao'] ?? '—'); ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Sexo</label><strong><?php echo $animal['sexo'] ?: '—'; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Nascimento</label><strong><?php echo $animal['data_nascimento'] ? date('d/m/Y', strtotime($animal['data_nascimento'])) : '—'; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Status</label><strong><?php echo $animal['status']; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Lote</label><strong><?php echo $animal['lote_nome'] ? htmlspecialchars($animal['lote_nome']) : '—'; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Peso</label><strong><?php echo $animal['peso_kg'] !== null ? htmlspecialchars($animal['peso_kg']) . ' kg' : '—'; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Tipo de Entrada</label><strong><?php echo $animal['tipo_entrada'] ?? '—'; ?></strong></div>
                <div class="col-sm-3"><label class="text-muted small d-block">Fornecedor</label><strong><?php echo htmlspecialchars($animal['fornecedor_origem'] ?? '—'); ?></strong></div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['registrado'])): ?>
        <div class="alert alert-success">Registro adicionado ao histórico do animal.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><i class="bi bi-plus-circle"></i> Novo Registro</strong></div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_registro" required>
                                <option value="CLINICO">Histórico Clínico</option>
                                <option value="VACINACAO">Vacinação</option>
                                <option value="ALIMENTACAO">Alimentação</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="data_registro" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <input type="text" class="form-control" name="descricao" placeholder="Ex.: Aplicação vacina aftosa / Ração orgânica">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Produto Utilizado</label>
                            <input type="text" class="form-control" name="produto_utilizado" placeholder="Ex.: Vacina, vermífugo, ração orgânica">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dose / Quantidade</label>
                            <input type="text" class="form-control" name="dose_quantidade">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Responsável</label>
                            <input type="text" class="form-control" name="responsavel">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Adicionar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><i class="bi bi-journal-medical"></i> Histórico do Animal</strong></div>
                <div class="card-body">
                    <?php if (!$registros): ?>
                        <p class="text-muted text-center py-3 mb-0">Nenhum registro. Adicione o primeiro ao lado.</p>
                    <?php endif; ?>
                    <?php foreach ($registros as $reg): ?>
                        <?php [$tipoLabel, $tipoBadge, $tipoIcon] = $tipoLabels[$reg['tipo_registro']] ?? [$reg['tipo_registro'], 'bg-secondary', 'bi-journal']; ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge <?php echo $tipoBadge; ?>"><i class="bi <?php echo $tipoIcon; ?> me-1"></i><?php echo $tipoLabel; ?></span>
                                <div>
                                    <small class="text-muted me-2"><?php echo date('d/m/Y', strtotime($reg['data_registro'])); ?></small>
                                    <a href="<?php echo SITE_URL; ?>/manejo-animal/<?php echo $id; ?>?remover_registro=<?php echo $reg['id']; ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('Remover este registro?');"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                            <?php if ($reg['descricao']): ?><p class="mb-1"><strong><?php echo htmlspecialchars($reg['descricao']); ?></strong></p><?php endif; ?>
                            <div class="small">
                                <?php if ($reg['produto_utilizado']): ?><span class="me-3"><i class="bi bi-box-seam text-muted"></i> <?php echo htmlspecialchars($reg['produto_utilizado']); ?></span><?php endif; ?>
                                <?php if ($reg['dose_quantidade']): ?><span class="me-3"><i class="bi bi-eyedropper text-muted"></i> <?php echo htmlspecialchars($reg['dose_quantidade']); ?></span><?php endif; ?>
                                <?php if ($reg['responsavel']): ?><span><i class="bi bi-person text-muted"></i> <?php echo htmlspecialchars($reg['responsavel']); ?></span><?php endif; ?>
                            </div>
                            <?php if ($reg['observacoes']): ?><small class="text-muted d-block mt-1"><?php echo nl2br(htmlspecialchars($reg['observacoes'])); ?></small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
