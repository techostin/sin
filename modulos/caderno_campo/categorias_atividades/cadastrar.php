<?php
$page_title = "Cadastrar Categoria de Atividade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $cor = $_POST['cor'] ?? '#000000';
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO categorias_atividades (nome, descricao, cor) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $descricao, $cor]);
        $mensagem = '<div class="alert alert-success">Categoria cadastrada com sucesso!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao cadastrar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Cadastrar Categoria de Atividade</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Categoria</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="cor" class="form-label">Cor</label>
                <div class="d-flex align-items-center">
                    <input type="color" class="form-control form-control-color me-2" id="cor" name="cor" value="#000000" style="width: 60px;">
                    <input type="text" class="form-control" id="cor_text" value="#000000" readonly style="width: 120px;">
                </div>
                <small class="form-text text-muted">Escolha uma cor para identificar esta categoria visualmente.</small>
            </div>
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/categorias-atividades" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<script>
document.getElementById('cor').addEventListener('input', function() {
    document.getElementById('cor_text').value = this.value;
});
</script>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
