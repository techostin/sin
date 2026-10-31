<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
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
    header('Location: ' . SITE_URL . '/mensalidades');
    exit;
}
$id = (int) $_GET['id'];

$model = new Mensalidade();
$mensalidade = $model->getById($id);
if (!$mensalidade) {
    header('Location: ' . SITE_URL . '/mensalidades');
    exit;
}

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $result = $model->reverterBaixa($id);
        if (empty($result['erro'])) {
            $_SESSION['success_message'] = 'Pagamento estornado: a mensalidade voltou para pendente/atrasada.';
            header('Location: ' . SITE_URL . '/mensalidades');
            exit;
        }
        $mensagem = $result['erro'];
        $mensagem_tipo = 'danger';
    }
}

$page_title = "Estornar Pagamento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-counterclockwise text-warning me-2"></i>Estornar Pagamento</h1>
            <span class="text-muted">Desfaz a baixa e retorna a cobrança para pendente/atrasada.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:640px;">
        <div class="card-header bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Confirmação de estorno</div>
        <div class="card-body">
            <div class="mb-4">
                <div><strong><?php echo htmlspecialchars($mensalidade['associado_nome']); ?></strong></div>
                <div class="text-muted small">Referência <?php echo str_pad((string)$mensalidade['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $mensalidade['ano_referencia']; ?>
                    • Vencimento <?php echo date('d/m/Y', strtotime($mensalidade['data_vencimento'])); ?>
                    • Pago em <?php echo $mensalidade['data_pagamento'] ? date('d/m/Y', strtotime($mensalidade['data_pagamento'])) : '—'; ?>
                </div>
                <div class="fs-5 fw-bold mt-1">R$ <?php echo number_format((float)$mensalidade['valor_cobrado'], 2, ',', '.'); ?></div>
            </div>
            <div class="alert alert-warning">
                O estorno remove o pagamento registrado, o lançamento de caixa vinculado e volta a mensalidade para
                <strong>pendente</strong> ou <strong>atrasada</strong>.
            </div>
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <button class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirmar Estorno</button>
                <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>