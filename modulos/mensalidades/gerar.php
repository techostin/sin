<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/PlanoMensalidade.php';
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
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $resultado = (new Mensalidade())->gerarLote($_POST);
        if (!empty($resultado['erro'])) {
            $mensagem = $resultado['erro'];
            $mensagem_tipo = 'danger';
        } else {
            $mensagem = 'Lote gerado: ' . $resultado['inseridos'] . ' lançamento(s), ' . $resultado['pulados'] . ' já existente(s).';
            $mensagem_tipo = 'success';
        }
    }
}

$associacoes = (new Associacao())->getAll();
$planos = (new PlanoMensalidade())->getAtivos();

$post = $_POST;
$page_title = "Gerar Lote de Mensalidades";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

function selected($a, $b) { return ($a ?? '') == $b ? 'selected' : ''; }
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-diagram-3 text-primary me-2"></i>Gerar Lote de Mensalidades</h1>
            <span class="text-muted">Cria as mensalidades do mês para todos os associados ativos vinculados à associação.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show"><?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:720px;">
        <div class="card-header bg-primary text-white"><i class="bi bi-gear me-1"></i>Parâmetros do Lote</div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Associação</label>
                        <select name="associacao_id" class="form-select" required <?php echo $resultado && empty($resultado['erro']) ? 'disabled' : ''; ?>>
                            <option value="">Selecione</option>
                            <?php foreach ($associacoes as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo selected($post['associacao_id'] ?? '', $a['id']); ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plano (opcional)</label>
                        <select name="plano_id" class="form-select">
                            <option value="">— Sem plano —</option>
                            <?php foreach ($planos as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo selected($post['plano_id'] ?? '', $p['id']); ?>>
                                    <?php echo htmlspecialchars($p['nome']) . ' (R$ ' . number_format((float)$p['valor'], 2, ',', '.') . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ano</label>
                        <input type="number" name="ano" class="form-control" min="2020" max="2100" value="<?php echo htmlspecialchars($post['ano'] ?? date('Y')); ?>" <?php echo $resultado && empty($resultado['erro']) ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mês</label>
                        <select name="mes" class="form-select" <?php echo $resultado && empty($resultado['erro']) ? 'disabled' : ''; ?> required>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo selected($post['mes'] ?? date('m'), $m); ?>><?php echo str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" name="valor" class="form-control" placeholder="35,20" value="<?php echo htmlspecialchars($post['valor'] ?? ''); ?>" <?php echo $resultado && empty($resultado['erro']) ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dia de Vencimento</label>
                        <input type="number" name="dia_vencimento" class="form-control" min="1" max="28" value="<?php echo htmlspecialchars($post['dia_vencimento'] ?? 10); ?>" <?php echo $resultado && empty($resultado['erro']) ? 'disabled' : ''; ?> required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" <?php echo $resultado && empty($resultado['erro']) ? 'hidden' : ''; ?>><i class="bi bi-rocket-takeoff me-1"></i>Gerar Lote</button>
                        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>