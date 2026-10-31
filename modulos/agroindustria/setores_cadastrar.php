<?php
$page_title = "Novo Setor";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroSetor.php';
require_once __DIR__ . '/../../classes/Agroindustria.php';
require_once __DIR__ . '/../../classes/Associado.php';
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

$setorClass    = new AgroSetor();
$agroClass     = new Agroindustria();
$associado     = new Associado();

$isGestor      = $user->hasPermission(NIVEL_GESTOR);
$associadoId   = $user->getAssociadoId();
$agroindustrias = $agroClass->getAll($isGestor ? null : $associadoId);
$associados = $isGestor ? $associado->getAll() : (($associadoId && $associado->getById($associadoId)) ? [$associado->getById($associadoId)] : []);

$message = '';
$message_type = '';
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['nome'] ?? '') === '') {
        $message = 'Informe o nome do setor.';
        $message_type = 'danger';
    } else {
        $dados['associado_id'] = !empty($dados['associado_id']) ? $dados['associado_id'] : $associadoId;
        if ($setorClass->create($dados)) {
            AuditLog::registrar('AGRO_SETOR_CADASTRADO', 'agro_setores', null, 'Setor: ' . $dados['nome']);
            header('Location: ' . SITE_URL . '/agroindustria/setores?cadastrado=1');
            exit;
        }
        $message = 'Erro ao salvar o setor. Tente novamente.';
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-diagram-3 text-success"></i> Novo Setor</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/setores" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Setor <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($dados['nome'] ?? ''); ?>" placeholder="Ex.: Processamento, Embalagem, Expedição">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Associado</label>
                    <select class="form-select" name="associado_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (($dados['associado_id'] ?? $associadoId) == $a['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agroindústria</label>
                    <select class="form-select" name="agroindustria_id">
                        <option value="">Nenhuma</option>
                        <?php foreach ($agroindustrias as $ag): ?>
                            <option value="<?php echo $ag['id']; ?>" <?php echo (($dados['agroindustria_id'] ?? '') == $ag['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ag['nome_fantasia']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Opcional — vincula o setor a uma agroindústria específica.</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($dados['descricao'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Setor</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>