<?php
$page_title = "Cadastrar Animal";
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

$animalClass = new ManejoAnimal();
$paClass = new ProducaoAnimal();
$escopoLotes = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$lotes = $paClass->getLotes($escopoLotes);
$associado = new Associado();
$associados = $user->hasPermission(NIVEL_GESTOR) ? $associado->getAll() : ($associado->getById($user->getAssociadoId()) ? [$associado->getById($user->getAssociadoId())] : []);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'associado_id' => !empty($_POST['associado_id']) ? $_POST['associado_id'] : ($user->getAssociadoId() ?? null),
        'nome_animal' => $_POST['nome_animal'] ?? '',
        'especie' => $_POST['especie'] ?? '',
        'raca' => $_POST['raca'] ?? '',
        'identificacao' => $_POST['identificacao'] ?? '',
        'sexo' => $_POST['sexo'] ?? '',
        'data_nascimento' => $_POST['data_nascimento'] ?? '',
        'status' => $_POST['status'] ?? 'ATIVO',
        'origem_conversao' => $_POST['origem_conversao'] ?? 'ORGANICO',
        'lote_id' => $_POST['lote_id'] ?? '',
        'peso_kg' => $_POST['peso_kg'] ?? '',
        'tipo_entrada' => $_POST['tipo_entrada'] ?? '',
        'fornecedor_origem' => $_POST['fornecedor_origem'] ?? '',
        'data_entrada' => $_POST['data_entrada'] ?? '',
        'observacoes_origem' => $_POST['observacoes_origem'] ?? '',
        'criado_por' => (int)$_SESSION['user_id'],
    ];

    if (empty($dados['nome_animal']) || empty($dados['especie'])) {
        $message = 'Preencha nome e espécie do animal.';
        $message_type = 'danger';
    } else {
        $id = $animalClass->create($dados);
        AuditLog::registrar('ANIMAL_CADASTRADO', 'animais', $id, 'Animal: ' . $dados['nome_animal'] . ' (' . $dados['especie'] . ')');
        header('Location: ' . SITE_URL . '/manejo-animal/' . $id);
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-egg-fill text-success"></i> Cadastrar Animal</h1>
        <a href="<?php echo SITE_URL; ?>/manejo-animal" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nome do Animal <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_animal" required value="<?php echo htmlspecialchars($_POST['nome_animal'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Espécie <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="especie" required value="<?php echo htmlspecialchars($_POST['especie'] ?? ''); ?>" placeholder="Ex.: Bovino, Suíno, Ovino, Aves">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Raça</label>
                    <input type="text" class="form-control" name="raca" value="<?php echo htmlspecialchars($_POST['raca'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Identificação (brinco/tag)</label>
                    <input type="text" class="form-control" name="identificacao" value="<?php echo htmlspecialchars($_POST['identificacao'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sexo</label>
                    <select class="form-select" name="sexo">
                        <option value="">—</option>
                        <option value="M">Macho</option>
                        <option value="F" <?php echo (($_POST['sexo'] ?? '') === 'F') ? 'selected' : ''; ?>>Fêmea</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nascimento</label>
                    <input type="date" class="form-control" name="data_nascimento" value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Associado</label>
                    <select class="form-select" name="associado_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (($_POST['associado_id'] ?? '') == $a['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Origem (conversão orgânica)</label>
                    <select class="form-select" name="origem_conversao">
                        <option value="ORGANICO" <?php echo (($_POST['origem_conversao'] ?? '') === 'ORGANICO') ? 'selected' : ''; ?>>Orgânico</option>
                        <option value="EM_CONVERSAO" <?php echo (($_POST['origem_conversao'] ?? '') === 'EM_CONVERSAO') ? 'selected' : ''; ?>>Em conversão</option>
                        <option value="CONVENCIONAL" <?php echo (($_POST['origem_conversao'] ?? '') === 'CONVENCIONAL') ? 'selected' : ''; ?>>Convencional</option>
                    </select>
                    <div class="form-text"><small>Conforme RF05: ração deve ser orgânica ou respeitar limites de conversão.</small></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="ATIVO">Ativo</option>
                        <option value="VENDIDO">Vendido</option>
                        <option value="ABATIDO">Abatido</option>
                        <option value="MORTO">Morto</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lote</label>
                    <select class="form-select" name="lote_id">
                        <option value="">— Selecione —</option>
                        <?php foreach ($lotes as $l): ?>
                            <option value="<?php echo $l['id']; ?>" <?php echo (($_POST['lote_id'] ?? '') == $l['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['nome_lote']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Peso (kg)</label>
                    <input type="number" step="0.01" class="form-control" name="peso_kg" min="0" value="<?php echo htmlspecialchars($_POST['peso_kg'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Entrada</label>
                    <select class="form-select" name="tipo_entrada">
                        <option value="">— Selecione —</option>
                        <option value="NASCIDO_PROPRIEDADE" <?php echo (($_POST['tipo_entrada'] ?? '') === 'NASCIDO_PROPRIEDADE') ? 'selected' : ''; ?>>Nascido na propriedade</option>
                        <option value="COMPRADO_ORGANICO" <?php echo (($_POST['tipo_entrada'] ?? '') === 'COMPRADO_ORGANICO') ? 'selected' : ''; ?>>Comprado (orgânico)</option>
                        <option value="COMPRA_CONVENCIONAL" <?php echo (($_POST['tipo_entrada'] ?? '') === 'COMPRA_CONVENCIONAL') ? 'selected' : ''; ?>>Comprado convencional (em conversão)</option>
                        <option value="TRANSFERIDO_OCS" <?php echo (($_POST['tipo_entrada'] ?? '') === 'TRANSFERIDO_OCS') ? 'selected' : ''; ?>>Transferido (mesma OCS)</option>
                    </select>
                    <div class="form-text"><small>Portaria 52/2021: animais convencionais entram em conversão conforme espécie.</small></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fornecedor de Origem</label>
                    <input type="text" class="form-control" name="fornecedor_origem" value="<?php echo htmlspecialchars($_POST['fornecedor_origem'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data de Entrada</label>
                    <input type="date" class="form-control" name="data_entrada" value="<?php echo htmlspecialchars($_POST['data_entrada'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Observações de Origem</label>
                    <textarea class="form-control" name="observacoes_origem" rows="2"><?php echo htmlspecialchars($_POST['observacoes_origem'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Cadastrar Animal</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
