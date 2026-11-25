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

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $id = (new PlanoMensalidade())->create($_POST);
        if ($id) {
            $_SESSION['success_message'] = 'Plano criado com sucesso.';
            header('Location: ' . SITE_URL . '/planos');
            exit;
        }
        $mensagem = 'Erro ao criar o plano. Verifique os dados.';
        $mensagem_tipo = 'danger';
    }
}

$associacoes = (new Associacao())->getAll();
$post = $_POST;

$page_title = "Novo Plano de Mensalidade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-plus-circle text-primary me-2"></i>Novo Plano</h1>
            <span class="text-muted">Tabela de valor da mensalidade por associação.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/planos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width:760px;">
        <div class="card-header bg-primary text-white"><i class="bi bi-tag me-1"></i>Dados do Plano</div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($post['nome'] ?? 'Mensalidade'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="mensalidade" <?php echo ($post['tipo'] ?? '') === 'mensalidade' ? 'selected' : ''; ?>>Mensalidade</option>
                            <option value="anuidade" <?php echo ($post['tipo'] ?? '') === 'anuidade' ? 'selected' : ''; ?>>Anuidade</option>
                            <option value="joia" <?php echo ($post['tipo'] ?? '') === 'joia' ? 'selected' : ''; ?>>Jóia de entrada</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" name="valor" class="form-control" placeholder="35,20" value="<?php echo htmlspecialchars($post['valor'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Dia de Vencimento</label>
                        <input type="number" name="dia_vencimento" class="form-control" min="1" max="28" value="<?php echo htmlspecialchars($post['dia_vencimento'] ?? 10); ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" <?php echo !isset($post['ativo']) || $post['ativo'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Associação (vazio = vale para todas)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Todas as associações</option>
                            <?php foreach ($associacoes as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo ($post['associacao_id'] ?? '') == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar Plano</button>
                        <a href="<?php echo SITE_URL; ?>/planos" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>