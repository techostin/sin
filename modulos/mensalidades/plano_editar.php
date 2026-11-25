<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/PlanoMensalidade.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/planos');
    exit;
}
$id = (int) $_GET['id'];

$planeModel = new PlanoMensalidade();
$plano = $planeModel->getById($id);
if (!$plano) {
    header('Location: ' . SITE_URL . '/planos');
    exit;
}

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        if ($planeModel->update($id, $_POST)) {
            $_SESSION['success_message'] = 'Plano atualizado com sucesso.';
            header('Location: ' . SITE_URL . '/planos');
            exit;
        }
        $mensagem = 'Erro ao atualizar o plano.';
        $mensagem_tipo = 'danger';
    }
}

$associacoes = (new Associacao())->getAll();

$page_title = "Editar Plano de Mensalidade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-pencil-square text-warning me-2"></i>Editar Plano</h1>
            <span class="text-muted">Alterações valem para as próximas gerações de lote.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/planos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:760px;">
        <div class="card-header bg-warning text-dark"><i class="bi bi-tag me-1"></i>Dados do Plano</div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($plano['nome']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="mensalidade" <?php echo $plano['tipo'] === 'mensalidade' ? 'selected' : ''; ?>>Mensalidade</option>
                            <option value="anuidade" <?php echo $plano['tipo'] === 'anuidade' ? 'selected' : ''; ?>>Anuidade</option>
                            <option value="joia" <?php echo $plano['tipo'] === 'joia' ? 'selected' : ''; ?>>Jóia de entrada</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" name="valor" class="form-control" value="<?php echo number_format((float)$plano['valor'], 2, ',', '.'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dia de Vencimento</label>
                        <input type="number" name="dia_vencimento" class="form-control" min="1" max="28" value="<?php echo (int) $plano['dia_vencimento']; ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" <?php echo (int)$plano['ativo'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Associação (vazio = vale para todas)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Todas as associações</option>
                            <?php foreach ($associacoes as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo (int) $plano['associacao_id'] === (int) $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Salvar Alterações</button>
                        <a href="<?php echo SITE_URL; ?>/planos" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>