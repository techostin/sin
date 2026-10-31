<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Associado.php';
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
        $result = (new Mensalidade())->criarManual($_POST);
        if (empty($result['erro'])) {
            $_SESSION['success_message'] = 'Mensalidade lançada com sucesso.';
            header('Location: ' . SITE_URL . '/mensalidades');
            exit;
        }
        $mensagem = $result['erro'];
        $mensagem_tipo = 'danger';
    }
}

$associados = (new Associado())->getAll();
$associacoes = (new Associacao())->getAll();
$post = $_POST;

$page_title = "Lançamento Manual de Mensalidade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-plus-circle text-info me-2"></i>Lançamento Manual</h1>
            <span class="text-muted">Mensalidade avulsa, jóia de entrada ou cobranças pontuais.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:760px;">
        <div class="card-header bg-info text-white"><i class="bi bi-pencil-square me-1"></i>Dados do Lançamento</div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Associado</label>
                        <select name="associado_id" class="form-select" required>
                            <option value="">Selecione</option>
                            <?php foreach ($associados as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo ($post['associado_id'] ?? '') == $a['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Associação (opcional)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Principal do associado</option>
                            <?php foreach ($associacoes as $ac): ?>
                                <option value="<?php echo $ac['id']; ?>" <?php echo ($post['associacao_id'] ?? '') == $ac['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($ac['sigla']) ? $ac['sigla'] : $ac['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ano Referência</label>
                        <input type="number" name="ano_referencia" class="form-control" min="2020" max="2100" value="<?php echo htmlspecialchars($post['ano_referencia'] ?? date('Y')); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mês Referência</label>
                        <select name="mes_referencia" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($post['mes_referencia'] ?? date('m')) == $m ? 'selected' : ''; ?>><?php echo str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" name="valor" class="form-control" placeholder="35,20" value="<?php echo htmlspecialchars($post['valor'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vencimento</label>
                        <input type="date" name="data_vencimento" class="form-control" value="<?php echo htmlspecialchars($post['data_vencimento'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descrição (aparece no pagamento)</label>
                        <input type="text" name="descricao" class="form-control" value="<?php echo htmlspecialchars($post['descricao'] ?? ''); ?>" placeholder="Ex.: Jóia de entrada, Mensalidade avulsa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observações</label>
                        <input type="text" name="observacoes" class="form-control">
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3">
                            <small class="text-uppercase fw-bold text-muted">Já está pago?</small>
                            <div class="row g-2 mt-2">
                                <div class="col-md-5">
                                    <label class="form-label small">Data do Pagamento</label>
                                    <input type="date" name="data_pagamento" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small">Forma</label>
                                    <select name="metodo_pagamento" class="form-select form-select-sm">
                                        <option value="PIX">PIX</option>
                                        <option value="Boleto">Boleto</option>
                                        <option value="Cartao">Cartão</option>
                                        <option value="Dinheiro">Dinheiro</option>
                                        <option value="Transferencia">Transferência</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <small class="text-muted">Deixe vazio para lançar como pendente.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-info"><i class="bi bi-check-lg me-1"></i>Lançar</button>
                        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>