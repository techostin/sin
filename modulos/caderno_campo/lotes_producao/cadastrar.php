<?php
$page_title = "Cadastrar Lote de Produção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/LoteProducao.php';
require_once __DIR__ . '/../../../classes/Produto.php'; // Para buscar produtos

$message = '';
$message_type = '';

// Verificar se o usuário está logado
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$produto_obj = new Produto();

if ($user->hasPermission(NIVEL_GESTOR)) {
    $produtos = $produto_obj->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $produtos = $produto_obj->getByAssociadoId($associado_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lote = new LoteProducao();
    
    $dados_lote = [];
    foreach ($_POST as $key => $value) {
        $dados_lote[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    if ($lote->create($dados_lote)) {
        $message = "Lote de produção cadastrado com sucesso!";
        $message_type = "success";
    } else {
        $message = "Erro ao cadastrar lote de produção.";
        $message_type = "danger";
    }
}
?>

<h1 class="mt-4">Cadastrar Novo Lote de Produção</h1>
<p>Preencha o formulário abaixo para adicionar um novo lote de produção.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">
    
    <div class="col-md-6">
        <label for="nome_lote" class="form-label">Nome do Lote</label>
        <input type="text" class="form-control" id="nome_lote" name="nome_lote" required>
    </div>
    <div class="col-md-6">
        <label for="produto_id" class="form-label">Produto</label>
        <select class="form-select" id="produto_id" name="produto_id" required>
            <option value="">Selecione um Produto</option>
            <?php foreach ($produtos as $prod): ?>
                <option value="<?php echo $prod['id']; ?>"><?php echo $prod['nome']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-4">
        <label for="data_producao" class="form-label">Data de Produção</label>
        <input type="date" class="form-control" id="data_producao" name="data_producao" required>
    </div>
    <div class="col-md-4">
        <label for="quantidade" class="form-label">Quantidade</label>
        <input type="number" step="0.01" class="form-control" id="quantidade" name="quantidade" required>
    </div>
    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <input type="text" class="form-control" id="unidade_medida" name="unidade_medida">
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Lote</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/lotes" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
