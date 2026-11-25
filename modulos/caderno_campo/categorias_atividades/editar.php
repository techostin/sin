<?php
$page_title = "Editar Categoria de Atividade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/CategoriaAtividade.php';

$message = '';
$message_type = '';

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID da categoria não informado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$categoria = new CategoriaAtividade();

// Buscar dados da categoria
$dados_categoria = $categoria->getById($id);
if (!$dados_categoria) {
    echo '<div class="alert alert-danger">Categoria não encontrada.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Anti-IDOR: a categoria pertence a um associado (categorias_atividades.associado_id).
// Admin/Gestor editam qualquer uma; nível 3 apenas as do próprio associado e
// categorias globais (associado_id nulo) ficam restritas à equipe.
$donoCategoria = (int) ($dados_categoria['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoCategoria <= 0 || $donoCategoria !== (int) $user->getAssociadoIdResolvido())) {
    echo '<div class="alert alert-danger mt-4">Acesso negado: esta categoria não pertence a você.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados = $_POST;
    $dados['id'] = $id;
    
    if ($categoria->update($dados)) {
        $message = "Categoria atualizada com sucesso!";
        $message_type = "success";
        // Recarregar dados após atualização
        $dados_categoria = $categoria->getById($id);
    } else {
        $message = "Erro ao atualizar categoria.";
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Categoria de Atividade</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/categorias-atividades" class="btn btn-success">Listar Categorias</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">
    
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome da Categoria</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dados_categoria['nome']); ?>" required>
    </div>
    
    <div class="col-md-6">
        <label for="cor" class="form-label">Cor</label>
        <input type="color" class="form-control form-control-color" id="cor" name="cor" value="<?php echo htmlspecialchars($dados_categoria['cor']); ?>" title="Escolha uma cor">
    </div>

    <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($dados_categoria['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Atualizar Categoria</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/categorias-atividades" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?> 