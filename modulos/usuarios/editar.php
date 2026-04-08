<?php
$page_title = "Editar Usuário";
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
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        echo "<div class='alert alert-danger'>Usuário não encontrado.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar usuário: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $nivel = $_POST['nivel'] ?? '';
    $status = $_POST['status'] ?? '';
    try {
        if ($senha) {
            $stmt = $db->prepare("UPDATE usuarios SET nome=?, email=?, senha=?, nivel=?, status=? WHERE id=?");
            $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nivel, $status, $id]);
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET nome=?, email=?, nivel=?, status=? WHERE id=?");
            $stmt->execute([$nome, $email, $nivel, $status, $id]);
        }
        $mensagem = '<div class="alert alert-success">Usuário atualizado com sucesso!</div>';
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['nivel'] = $nivel;
        $usuario['status'] = $status;
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Editar Usuário</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha (deixe em branco para não alterar)</label>
                <input type="password" class="form-control" id="senha" name="senha">
            </div>
            <div class="mb-3">
                <label for="nivel" class="form-label">Nível</label>
                <select class="form-select" id="nivel" name="nivel" required>
                    <option value="">Selecione</option>
                    <option value="1" <?php if((int)$usuario['nivel'] === 1) echo 'selected'; ?>>Admin</option>
                    <option value="2" <?php if((int)$usuario['nivel'] === 2) echo 'selected'; ?>>Gestor</option>
                    <option value="3" <?php if((int)$usuario['nivel'] === 3) echo 'selected'; ?>>Usuário</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="">Selecione</option>
                    <option value="ativo" <?php if(strtolower($usuario['status'])==='ativo') echo 'selected'; ?>>Ativo</option>
                    <option value="inativo" <?php if(strtolower($usuario['status'])==='inativo') echo 'selected'; ?>>Inativo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>/usuarios" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 