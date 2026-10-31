<?php
$page_title = "Novo Lote de Animais";
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

$message = '';
$message_type = '';
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['nome_lote'] ?? '') === '') {
        $message = 'Informe o nome do lote.';
        $message_type = 'danger';
    } else {
        $dados['associado_id'] = $user->getAssociadoId();
        $dados['criado_por'] = (int)($_SESSION['user_id'] ?? 0);
        if ($id = $paClass->criarLote($dados)) {
            AuditLog::registrar('PA_LOTE_CADASTRADO', 'animal_lotes', $id, 'Lote: ' . $dados['nome_lote']);
            header('Location: ' . SITE_URL . '/producao-animal/lotes?cadastrado=1');
            exit;
        }
        $message = 'Erro ao salvar o lote. Tente novamente.';
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-boxes text-success"></i> Novo Lote de Animais</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/lotes" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Lote <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_lote" required value="<?php echo htmlspecialchars($dados['nome_lote'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espécie</label>
                    <select class="form-select" name="especie">
                        <option value="">Selecione...</option>
                        <?php foreach (['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA'] as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo ($dados['especie'] ?? '') === $e ? 'selected' : ''; ?>><?php echo $e; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <input type="text" class="form-control" name="categoria" placeholder="ex.: recria, terminação, matrizes" value="<?php echo htmlspecialchars($dados['categoria'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade (cabeças)</label>
                    <input type="number" class="form-control" name="quantidade" min="0" value="<?php echo htmlspecialchars($dados['quantidade'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($dados['descricao'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Lote</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>