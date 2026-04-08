<?php
$page_title = "Cadastrar Usuário";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Verifica se o usuário tem permissão de admin ou gestor
if (!$user->isLoggedIn() || !in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página. Apenas administradores e gestores podem cadastrar usuários.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $nivel = $_POST['nivel'] ?? '';
    $status = $_POST['status'] ?? '';
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, nivel, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel, $status]);
        $mensagem = '<div class="alert alert-success">Usuário cadastrado com sucesso!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao cadastrar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Cadastrar Usuário</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="mb-3">
                <label for="nivel" class="form-label">Nível</label>
                <select class="form-select" id="nivel" name="nivel" required>
                    <option value="">Selecione</option>
                    <option value="1">Admin</option>
                    <option value="2">Gestor</option>
                    <option value="3">Usuário</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="">Selecione</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="<?php echo SITE_URL; ?>/usuarios" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 