<?php
$page_title = "Editar Produtos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';

$message = '';
$message_type = '';

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID do produto não informado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$produtos = new Produto();

// Buscar dados do produto
$dados_produtos = $produtos->getById($id);
if (!$dados_produtos) {
    echo '<div class="alert alert-danger">Produto não encontrado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// IDOR Check
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT p.associado_id 
                          FROM produtos pr 
                          INNER JOIN lavouras l ON pr.lavoura_id = l.id 
                          INNER JOIN propriedades p ON l.propriedade_id = p.id 
                          WHERE pr.id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res || $res['associado_id'] != $user->getAssociadoId()) {
        echo "<div class='alert alert-danger'>Acesso Negado: Este produto pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

// Buscar lavouras para o select
$lavoura_obj = new Lavoura();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $lavouras = $lavoura_obj->getAll();
} else {
    $lavouras = $lavoura_obj->getByAssociadoId($user->getAssociadoId() ?? -1);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados = $_POST;
    $dados['id'] = $id;

    if ($produtos->update($dados)) {
        $message = "Produto atualizado com sucesso!";
        $message_type = "success";
        // Recarregar dados após atualização
        $dados_produtos = $produtos->getById($id);
    } else {
        $message = "Erro ao atualizar produto.";
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Produto</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-success">
        <i class="bi bi-list"></i> Listar Produtos
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">

    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Produto</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dados_produtos['nome'] ?? ''); ?>" required>
    </div>

    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo/Variedade</label>
        <input type="text" class="form-control" id="tipo" name="tipo" value="<?php echo htmlspecialchars($dados_produtos['tipo'] ?? ''); ?>">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo htmlspecialchars($dados_produtos['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <select class="form-select" id="unidade_medida" name="unidade_medida">
            <option value="kg" <?php echo ($dados_produtos['unidade_medida'] == 'kg') ? 'selected' : ''; ?>>Quilograma (kg)</option>
            <option value="unidade" <?php echo ($dados_produtos['unidade_medida'] == 'unidade') ? 'selected' : ''; ?>>Unidade</option>
            <option value="caixa" <?php echo ($dados_produtos['unidade_medida'] == 'caixa') ? 'selected' : ''; ?>>Caixa</option>
            <option value="litro" <?php echo ($dados_produtos['unidade_medida'] == 'litro') ? 'selected' : ''; ?>>Litro</option>
            <option value="saca" <?php echo ($dados_produtos['unidade_medida'] == 'saca') ? 'selected' : ''; ?>>Saca</option>
        </select>
    </div>

    <div class="col-md-4">
        <label for="preco_unitario" class="form-label">Preço Unitário (R$)</label>
        <input type="text" class="form-control" id="preco_unitario" name="preco_unitario" value="<?php echo htmlspecialchars($dados_produtos['preco_unitario'] ?? ''); ?>">
    </div>

    <div class="col-md-4">
        <label for="quantidade_produzida" class="form-label">Quantidade Produzida</label>
        <input type="text" class="form-control" id="quantidade_produzida" name="quantidade_produzida" value="<?php echo htmlspecialchars($dados_produtos['quantidade_produzida'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="data_colheita" class="form-label">Data da Colheita</label>
        <input type="date" class="form-control" id="data_colheita" name="data_colheita" value="<?php echo htmlspecialchars($dados_produtos['data_colheita'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="lavoura_id" class="form-label">Lavoura de Origem</label>
        <select class="form-select" id="lavoura_id" name="lavoura_id" required>
            <option value="">Selecione a lavoura...</option>
            <?php foreach ($lavouras as $lav): ?>
                <option value="<?php echo $lav['id']; ?>" <?php echo ($dados_produtos['lavoura_id'] == $lav['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lav['nome'] . " (" . $lav['propriedade_nome'] . ")"); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($dados_produtos['observacoes'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Produtos
        </a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
