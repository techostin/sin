<?php
$page_title = "Editar Animal";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ManejoAnimal.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . SITE_URL . "/manejo-animal");
    exit;
}

$animalClass = new ManejoAnimal();
$animal = $animalClass->getById($id);
if (!$animal) {
    header("Location: " . SITE_URL . "/manejo-animal");
    exit;
}

$paClass = new ProducaoAnimal();
$escopoLotes = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$lotes = $paClass->getLotes($escopoLotes);

$associado = new Associado();
$associados = $user->hasPermission(NIVEL_GESTOR) ? $associado->getAll() : ($associado->getById($user->getAssociadoId()) ? [$associado->getById($user->getAssociadoId())] : []);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = array_merge($animal, $_POST);
    if (empty($dados['nome_animal']) || empty($dados['especie'])) {
        $message = 'Preencha nome e espécie do animal.';
        $message_type = 'danger';
    } elseif ($paClass->atualizarAnimal($id, $dados)) {
        AuditLog::registrar('ANIMAL_ATUALIZADO', 'animais', $id, 'Animal: ' . $dados['nome_animal']);
        header('Location: ' . SITE_URL . '/manejo-animal/' . $id . '?atualizado=1');
        exit;
    } else {
        $message = 'Erro ao atualizar o animal. Tente novamente.';
        $message_type = 'danger';
    }
    $animal = $dados;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-egg-fill text-success"></i> Editar Animal</h1>
        <a href="<?php echo SITE_URL; ?>/manejo-animal/<?php echo $id; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nome do Animal <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_animal" required value="<?php echo htmlspecialchars($animal['nome_animal']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Espécie <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="especie" required value="<?php echo htmlspecialchars($animal['especie']); ?>" placeholder="Ex.: Bovino, Suíno, Ovino, Aves">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Raça</label>
                    <input type="text" class="form-control" name="raca" value="<?php echo htmlspecialchars($animal['raca'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Identificação (brinco/tag)</label>
                    <input type="text" class="form-control" name="identificacao" value="<?php echo htmlspecialchars($animal['identificacao'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sexo</label>
                    <select class="form-select" name="sexo">
                        <option value="">—</option>
                        <option value="M" <?php echo ($animal['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>Macho</option>
                        <option value="F" <?php echo ($animal['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>Fêmea</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nascimento</label>
                    <input type="date" class="form-control" name="data_nascimento" value="<?php echo htmlspecialchars($animal['data_nascimento'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Associado</label>
                    <select class="form-select" name="associado_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo ($animal['associado_id'] == $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Origem (conversão orgânica)</label>
                    <select class="form-select" name="origem_conversao">
                        <option value="ORGANICO" <?php echo ($animal['origem_conversao'] ?? '') === 'ORGANICO' ? 'selected' : ''; ?>>Orgânico</option>
                        <option value="EM_CONVERSAO" <?php echo ($animal['origem_conversao'] ?? '') === 'EM_CONVERSAO' ? 'selected' : ''; ?>>Em conversão</option>
                        <option value="CONVENCIONAL" <?php echo ($animal['origem_conversao'] ?? '') === 'CONVENCIONAL' ? 'selected' : ''; ?>>Convencional</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="ATIVO" <?php echo ($animal['status'] ?? '') === 'ATIVO' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="VENDIDO" <?php echo ($animal['status'] ?? '') === 'VENDIDO' ? 'selected' : ''; ?>>Vendido</option>
                        <option value="ABATIDO" <?php echo ($animal['status'] ?? '') === 'ABATIDO' ? 'selected' : ''; ?>>Abatido</option>
                        <option value="MORTO" <?php echo ($animal['status'] ?? '') === 'MORTO' ? 'selected' : ''; ?>>Morto</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lote</label>
                    <select class="form-select" name="lote_id">
                        <option value="">— Selecione —</option>
                        <?php foreach ($lotes as $l): ?>
                            <option value="<?php echo $l['id']; ?>" <?php echo ($animal['lote_id'] == $l['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['nome_lote']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" step="0.01" class="form-control" name="peso_kg" min="0" value="<?php echo htmlspecialchars($animal['peso_kg'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Entrada</label>
                    <select class="form-select" name="tipo_entrada">
                        <option value="">— Selecione —</option>
                        <option value="NASCIDO_PROPRIEDADE" <?php echo ($animal['tipo_entrada'] ?? '') === 'NASCIDO_PROPRIEDADE' ? 'selected' : ''; ?>>Nascido na propriedade</option>
                        <option value="COMPRADO_ORGANICO" <?php echo ($animal['tipo_entrada'] ?? '') === 'COMPRADO_ORGANICO' ? 'selected' : ''; ?>>Comprado (orgânico)</option>
                        <option value="COMPRA_CONVENCIONAL" <?php echo ($animal['tipo_entrada'] ?? '') === 'COMPRA_CONVENCIONAL' ? 'selected' : ''; ?>>Comprado convencional (em conversão)</option>
                        <option value="TRANSFERIDO_OCS" <?php echo ($animal['tipo_entrada'] ?? '') === 'TRANSFERIDO_OCS' ? 'selected' : ''; ?>>Transferido (mesma OCS)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fornecedor de Origem</label>
                    <input type="text" class="form-control" name="fornecedor_origem" value="<?php echo htmlspecialchars($animal['fornecedor_origem'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de Entrada</label>
                    <input type="date" class="form-control" name="data_entrada" value="<?php echo htmlspecialchars($animal['data_entrada'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Observações de Origem</label>
                    <textarea class="form-control" name="observacoes_origem" rows="2"><?php echo htmlspecialchars($animal['observacoes_origem'] ?? ''); ?></textarea>
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