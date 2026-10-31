<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Caixa.php';
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

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $result = (new Caixa())->create($_POST);
        if (!empty($result['erro'])) {
            $mensagem = $result['erro'];
            $mensagem_tipo = 'danger';
        } else {
            $_SESSION['success_message'] = 'Lançamento de caixa registrado.';
            header('Location: ' . SITE_URL . '/caixa');
            exit;
        }
    }
}

$associacoes = (new Associacao())->getAll();
$post = $_POST;

$page_title = "Novo Lançamento de Caixa";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-plus-circle text-primary me-2"></i>Novo Lançamento</h1>
            <span class="text-muted">Registra uma entrada ou saída de recursos.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/caixa" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:720px;">
        <div class="card-header bg-primary text-white"><i class="bi bi-cash-coin me-1"></i>Dados do Lançamento</div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="Entrada" <?php echo ($post['tipo'] ?? 'Entrada') === 'Entrada' ? 'selected' : ''; ?>>Entrada</option>
                            <option value="Saida" <?php echo ($post['tipo'] ?? '') === 'Saida' ? 'selected' : ''; ?>>Saída</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Data</label>
                        <input type="date" name="data" class="form-control" value="<?php echo htmlspecialchars($post['data'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control" value="<?php echo htmlspecialchars($post['descricao'] ?? ''); ?>" placeholder="Ex.: Recebimento de mensalidades, compra de materiais" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" name="valor" class="form-control" value="<?php echo htmlspecialchars($post['valor'] ?? ''); ?>" placeholder="0,00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Categoria (opcional)</label>
                        <input type="text" name="categoria" class="form-control" value="<?php echo htmlspecialchars($post['categoria'] ?? ''); ?>" placeholder="Ex.: Mensalidade, Manutenção, Eventos">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Associação (opcional)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Nenhuma</option>
                            <?php foreach ($associacoes as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo ($post['associacao_id'] ?? '') == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Registrar</button>
                        <a href="<?php echo SITE_URL; ?>/caixa" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>