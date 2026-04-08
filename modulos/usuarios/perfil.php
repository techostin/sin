<?php
$page_title = "Meu Perfil";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$mensagem = '';

// Verificar se user_id existe
if (!$user_id) {
    echo "<div class='alert alert-danger'>ID do usuário não encontrado na sessão.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Buscar dados do associado logado
try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar na tabela associados
    $stmt = $db->prepare("SELECT * FROM associados WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo "<div class='alert alert-danger'>Associado não encontrado. ID: $user_id</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar dados: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    try {
        // Verificar se a senha atual está correta
        if (!empty($senha_atual)) {
            // Verificar senha em texto plano (temporário)
            if ($senha_atual !== $usuario['senha']) {
                $mensagem = '<div class="alert alert-danger">Senha atual incorreta!</div>';
            } elseif ($nova_senha !== $confirmar_senha) {
                $mensagem = '<div class="alert alert-danger">As novas senhas não coincidem!</div>';
            } else {
                // Atualizar dados do associado
                $stmt = $db->prepare("UPDATE associados SET nome = ?, email = ?, senha = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $nova_senha, $user_id]);
                $mensagem = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
                $usuario['nome'] = $nome;
                $usuario['email'] = $email;
            }
        } else {
            // Atualizar apenas nome e email
            $stmt = $db->prepare("UPDATE associados SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $user_id]);
            $mensagem = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
            $usuario['nome'] = $nome;
            $usuario['email'] = $email;
        }
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>

<h1 class="mt-4">Meu Perfil</h1>
<p>Gerencie suas informações pessoais e altere sua senha.</p>

<?php if ($mensagem) echo $mensagem; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Informações Pessoais</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Alterar Senha</h6>
                    <p class="text-muted small">Preencha apenas se desejar alterar sua senha.</p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="senha_atual" class="form-label">Senha Atual</label>
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        <a href="<?php echo SITE_URL; ?>/" class="btn btn-secondary">Voltar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Informações da Conta</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Tipo de Usuário:</strong><br>
                    <span class="badge bg-<?php 
                        switch($usuario['tipo']) {
                            case 'Admin': echo 'danger'; break;
                            case 'Gestor': echo 'warning'; break;
                            case 'Usuario': echo 'success'; break;
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo htmlspecialchars($usuario['tipo']); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?php 
                        switch($usuario['status']) {
                            case 'ativo': echo 'success'; break;
                            case 'inativo': echo 'danger'; break;
                            case 'pendente': echo 'warning'; break;
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo ucfirst(htmlspecialchars($usuario['status'])); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Nível:</strong><br>
                    <span class="badge bg-info"><?php echo htmlspecialchars($usuario['nivel']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>ID da Conta:</strong><br>
                    <code><?php echo $user_id; ?></code>
                </div>
                
                <div class="mb-3">
                    <strong>Data de Cadastro:</strong><br>
                    <small class="text-muted">
                        <?php echo $usuario['data_cadastro'] ? date('d/m/Y H:i', strtotime($usuario['data_cadastro'])) : 'Não informado'; ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Última Atualização:</strong><br>
                    <small class="text-muted">
                        <?php echo $usuario['ultima_atualizacao'] ? date('d/m/Y H:i', strtotime($usuario['ultima_atualizacao'])) : 'Não informado'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 