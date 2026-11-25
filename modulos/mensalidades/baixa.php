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

$mensagem = '';
$mensagem_tipo = '';
$pixResultado = null;

$model = new Mensalidade();
$mensalidade = $model->getById($id);
if (!$mensalidade) {
    header('Location: ' . SITE_URL . '/mensalidades');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        // Verificar se é geração de PIX
        if (isset($_POST['gerar_pix'])) {
            $pixResultado = $model->gerarPix($id);
            if (empty($pixResultado['erro'])) {
                $mensagem = 'PIX gerado com sucesso! Copie o código ou escaneie o QR Code.';
                $mensagem_tipo = 'success';
            } else {
                $mensagem = $pixResultado['erro'];
                $mensagem_tipo = 'danger';
            }
        } else {
            $result = $model->baixar($id, $_POST);
            if (empty($result['erro'])) {
                $_SESSION['success_message'] = 'Pagamento registrado com sucesso.';
                header('Location: ' . SITE_URL . '/mensalidades');
                exit;
            }
            $mensagem = $result['erro'];
            $mensagem_tipo = 'danger';
        }
    }
}

$page_title = "Baixa de Mensalidade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-check2-circle text-success me-2"></i>Dar Baixa em Mensalidade</h1>
            <span class="text-muted">Registra o pagamento e atualiza a situação da cobrança.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:640px;">
        <div class="card-header bg-success text-white"><i class="bi bi-cash-coin me-1"></i>Pagamento</div>
        <div class="card-body">
            <div class="mb-4">
                <div><strong><?php echo htmlspecialchars($mensalidade['associado_nome']); ?></strong></div>
                <div class="text-muted small">Referência <?php echo str_pad((string)$mensalidade['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $mensalidade['ano_referencia']; ?>
                    • Vencimento <?php echo date('d/m/Y', strtotime($mensalidade['data_vencimento'])); ?>
                    • <?php echo $mensalidade['plano_nome'] ? 'Plano: ' . htmlspecialchars($mensalidade['plano_nome']) : 'Plano livre'; ?>
                </div>
                <div class="fs-5 fw-bold mt-1">R$ <?php echo number_format((float)$mensalidade['valor_cobrado'], 2, ',', '.'); ?></div>
            </div>

            <!-- Botão Gerar PIX (apenas se não estiver pago e provedor configurado) -->
            <?php if ($mensalidade['status_mensalidade'] !== 'pago' && defined('PIX_PROVIDER') && PIX_PROVIDER !== ''): ?>
            <div class="d-grid gap-2 mb-3">
                <form method="post" class="d-inline">
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <button type="submit" name="gerar_pix" class="btn btn-primary" onclick="return confirm('Gerar cobrança PIX para esta mensalidade?');">
                        <i class="bi bi-qr-code me-1"></i> Gerar Cobrança PIX
                    </button>
                </form>
                <small class="text-muted">Provedor: <?php echo strtoupper(PIX_PROVIDER); ?></small>
            </div>
            <?php endif; ?>

            <!-- Exibição do PIX gerado -->
            <?php if ($pixResultado && empty($pixResultado['erro'])): ?>
            <div class="alert alert-success mb-3">
                <h6><i class="bi bi-check-circle me-1"></i>PIX Gerado com Sucesso</h6>
                <p class="mb-1"><strong>TXID:</strong> <code><?php echo htmlspecialchars($pixResultado['txid']); ?></code></p>
                <?php if (!empty($pixResultado['pix_copia_cola'])): ?>
                <div class="mb-2">
                    <label class="form-label small mb-1">Copia e Cola (PIX)</label>
                    <div class="input-group">
                        <textarea class="form-control form-control-sm" rows="3" readonly onclick="this.select()"><?php echo htmlspecialchars($pixResultado['pix_copia_cola']); ?></textarea>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar', 2000);"><i class="bi bi-clipboard"></i> Copiar</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($pixResultado['pix_qrcode'])): ?>
                <div class="text-center mb-2">
                    <label class="form-label small mb-1">QR Code</label>
                    <?php if (str_starts_with($pixResultado['pix_qrcode'], 'data:image') || str_starts_with($pixResultado['pix_qrcode'], 'iVBOR') || str_starts_with($pixResultado['pix_qrcode'], '/9j/')): ?>
                        <img src="<?php echo htmlspecialchars($pixResultado['pix_qrcode']); ?>" alt="QR Code PIX" class="img-fluid border rounded" style="max-width: 200px;">
                    <?php else: ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($pixResultado['pix_copia_cola']); ?>" alt="QR Code PIX" class="img-fluid border rounded" style="max-width: 200px;">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>Envie o código "Copia e Cola" ou o QR Code para o associado pagar via app do banco.
                </div>
            </div>
            <?php endif; ?>

            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="metodo_pagamento" class="form-select" required>
                            <option value="PIX">PIX</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Cartao">Cartão</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Transferencia">Transferência</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Data do Pagamento</label>
                        <input type="date" name="data_pagamento" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valor Pago (R$)</label>
                        <input type="text" name="valor" class="form-control" value="<?php echo number_format((float)$mensalidade['valor_cobrado'], 2, ',', '.'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observações</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Confirmar Baixa</button>
                        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>