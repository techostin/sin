<?php
$page_title = "Editar Conta";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM contas WHERE id = ?");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conta) {
        echo "<div class='alert alert-danger'>Conta não encontrada.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar conta: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $data = $_POST['data'] ?? '';
    try {
        $stmt = $db->prepare("UPDATE contas SET nome=?, tipo=?, valor=?, data=? WHERE id=?");
        $stmt->execute([$nome, $tipo, $valor, $data, $id]);
        $mensagem = '<div class="alert alert-success">Conta atualizada com sucesso!</div>';
        // Atualizar dados exibidos
        $conta['nome'] = $nome;
        $conta['tipo'] = $tipo;
        $conta['valor'] = $valor;
        $conta['data'] = $data;
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Editar Conta</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($conta['nome']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo" required>
                    <option value="">Selecione</option>
                    <option value="Receita" <?php if($conta['tipo']==='Receita') echo 'selected'; ?>>Receita</option>
                    <option value="Despesa" <?php if($conta['tipo']==='Despesa') echo 'selected'; ?>>Despesa</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="valor" class="form-label">Valor</label>
                <input type="text" class="form-control mask-valor" id="valor" name="valor" value="<?php echo htmlspecialchars($conta['valor']); ?>" placeholder="0,00" required>
            </div>
            <div class="mb-3">
                <label for="data" class="form-label">Data</label>
                <input type="date" class="form-control" id="data" name="data" value="<?php echo htmlspecialchars($conta['data']); ?>" required>
            </div>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>/contas" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 