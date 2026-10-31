<?php
$page_title = "Editar Ambiente de Criação";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . SITE_URL . '/producao-animal/ambientes');
    exit;
}

$paClass = new ProducaoAnimal();
$ambiente = $paClass->getAmbienteById($id);
if (!$ambiente) {
    header('Location: ' . SITE_URL . '/producao-animal/ambientes');
    exit;
}

// Anti-IDOR: o ambiente pertence a um associado (ambiente_criacao_animal.associado_id).
// Admin/Gestor editam qualquer ambiente; nível 3 só os do próprio associado.
$donoAmbiente = (int) ($ambiente['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoAmbiente <= 0 || $donoAmbiente !== (int) $user->getAssociadoIdResolvido())) {
    echo "<div class='alert alert-danger m-4'>Acesso negado: este ambiente pertence a outro produtor.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$densidades = $paClass->getDensidades(!empty($ambiente['especie']) ? $ambiente['especie'] : null);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['nome_instalacao'] ?? '') === '') {
        $message = 'Informe o nome da instalação.';
        $message_type = 'danger';
    } elseif ($paClass->atualizarAmbiente($id, $_POST)) {
        AuditLog::registrar('PA_AMBIENTE_ATUALIZADO', 'ambiente_criacao_animal', $id, 'Ambiente: ' . $_POST['nome_instalacao']);
        header('Location: ' . SITE_URL . '/producao-animal/ambientes?atualizado=1');
        exit;
    } else {
        $message = 'Erro ao atualizar o ambiente. Tente novamente.';
        $message_type = 'danger';
    }
    $ambiente = array_merge($ambiente, $_POST);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-house-heart text-success"></i> Editar Ambiente de Criação</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/ambientes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome da Instalação <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_instalacao" required value="<?php echo htmlspecialchars($ambiente['nome_instalacao']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" name="especie">
                        <option value="">Selecione...</option>
                        <?php foreach (['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA'] as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo ($ambiente['especie'] ?? '') === $e ? 'selected' : ''; ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sistema</label>
                    <select class="form-select" name="sistema">
                        <?php foreach (['EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($ambiente['sistema'] ?? '') === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Instalação</label>
                    <input type="text" class="form-control" name="tipo_instalacao" placeholder="ex.: pasto, piquete, galpão, estábulo, aviário, apiário" value="<?php echo htmlspecialchars($ambiente['tipo_instalacao'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Área Total (m²)</label>
                    <input type="number" step="0.01" class="form-control" name="area_total_m2" min="0" value="<?php echo htmlspecialchars($ambiente['area_total_m2'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantidade de Animais</label>
                    <input type="number" class="form-control" name="quantidade_animais" min="0" value="<?php echo htmlspecialchars($ambiente['quantidade_animais'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Densidade de Referência (norma)</label>
                    <input type="number" step="0.01" class="form-control" name="densidade_referencia" min="0" value="<?php echo htmlspecialchars($ambiente['densidade_referencia'] ?? ''); ?>">
                    <div class="form-text">m²/animal recomendado pela norma para a espécie.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Possui Sombreamento/Abrigo</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="tem_sombreamento_abrigo" value="1" id="sombraAbrigo" <?php echo !empty($ambiente['tem_sombreamento_abrigo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sombraAbrigo">Sim</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="alert alert-light border">
                        <strong><i class="bi bi-rulers"></i> Referências de densidade máxima (Portaria 52/2021, art. 42):</strong>
                        <ul class="mb-0">
                            <?php foreach ($densidades as $d): ?>
                            <li><?php echo htmlspecialchars($d['especie'] . ' — ' . $d['fase'] . ': ' . $d['densidade'] . ($d['referencia'] ? ' (' . $d['referencia'] . ')' : '')); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="3"><?php echo htmlspecialchars($ambiente['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Alterações</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>