<?php
$page_title = "Cadastrar Produto";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/Lavoura.php'; // Para buscar lavouras

$message = '';
$message_type = '';

// Apenas usuários logados
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lavoura_obj = new Lavoura();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $lavouras = $lavoura_obj->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $lavouras = $lavoura_obj->getByAssociadoId($associado_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $produto = new Produto();

    // Opcional: validações simples antes de salvar
    // $campos_obrigatorios = ['nome'];
    // foreach ($campos_obrigatorios as $campo) {
    //     if (empty($_POST[$campo])) {
    //         $message = "O campo '{$campo}' é obrigatório.";
    //         $message_type = "danger";
    //         require_once __DIR__ . '/../../../includes/footer.php';
    //         exit;
    //     }
    // }

    $dados_produto = [];
    foreach ($_POST as $key => $value) {
        $dados_produto[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    if ($produto->create($dados_produto)) {
        $message = "Produto cadastrado com sucesso!";
        $message_type = "success";
        $_POST = [];
    } else {
        $message = "Erro ao cadastrar produto.";
        $message_type = "danger";
    }
}
?>

<h1 class="mt-4">Cadastrar Novo Produto</h1>
<p>Preencha o formulário abaixo para adicionar um novo produto.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/caderno-campo/produto/novo" method="POST" class="row g-3">
    
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Produto</label>
        <input type="text" class="form-control" id="nome" name="nome" required>
    </div>
    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo/Variedade</label>
        <input type="text" class="form-control" id="tipo" name="tipo">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
    </div>

    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <select class="form-select" id="unidade_medida" name="unidade_medida">
            <option value="kg">Quilograma (kg)</option>
            <option value="unidade">Unidade</option>
            <option value="caixa">Caixa</option>
            <option value="litro">Litro</option>
            <option value="saca">Saca</option>
        </select>
    </div>
    <div class="col-md-4">
        <label for="preco_unitario" class="form-label">Preço Unitário (R$)</label>
        <input type="text" class="form-control" id="preco_unitario" name="preco_unitario">
    </div>
    <div class="col-md-4">
        <label for="quantidade_produzida" class="form-label">Quantidade Produzida</label>
        <input type="text" class="form-control" id="quantidade_produzida" name="quantidade_produzida">
    </div>
    <div class="col-md-4">
        <label for="data_colheita" class="form-label">Data de Colheita</label>
        <input type="date" class="form-control" id="data_colheita" name="data_colheita">
    </div>

    <div class="col-md-12">
        <label for="lavoura_id" class="form-label">Lavoura de Origem</label>
        <select class="form-select" id="lavoura_id" name="lavoura_id">
            <option value="">Selecione uma Lavoura</option>
            <?php foreach ($lavouras as $lav): ?>
                <?php
                    $selected = (isset($_POST['lavoura_id']) && $_POST['lavoura_id'] == $lav['id']) ? 'selected' : '';
                ?>
                <option value="<?php echo $lav['id']; ?>" <?php echo $selected; ?>>
                    <?php echo $lav['nome'] . " (" . $lav['propriedade_nome'] . ")"; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Produto</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Produtos
        </a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
