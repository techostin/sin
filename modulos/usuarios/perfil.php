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
$user_table = $_SESSION['user_table'] ?? 'usuarios';
$mensagem = '';

// Verificar se user_id existe
if (!$user_id) {
    echo "<div class='alert alert-danger'>ID do usuário não encontrado na sessão.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Buscar dados do usuário logado na tabela correta da sessão
try {
    $db = Database::getInstance()->getConnection();

    if (!in_array($user_table, ['usuarios', 'associados'], true)) {
        $user_table = 'usuarios';
    }

    $stmt = $db->prepare("SELECT * FROM {$user_table} WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Fallback para sessões antigas sem user_table consistente
        $fallback_table = $user_table === 'usuarios' ? 'associados' : 'usuarios';
        $stmt = $db->prepare("SELECT * FROM {$fallback_table} WHERE id = ?");
        $stmt->execute([$user_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            echo "<div class='alert alert-danger'>Usuário não encontrado. ID: $user_id</div>";
            require_once __DIR__ . '/../../includes/footer.php';
            exit;
        }

        $user_table = $fallback_table;
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
        if (!empty($senha_atual) || !empty($nova_senha) || !empty($confirmar_senha)) {
            if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
                $mensagem = '<div class="alert alert-danger">Para alterar a senha, preencha todos os campos de senha.</div>';
            } elseif (!password_verify($senha_atual, $usuario['senha'])) {
                $mensagem = '<div class="alert alert-danger">Senha atual incorreta!</div>';
            } elseif ($nova_senha !== $confirmar_senha) {
                $mensagem = '<div class="alert alert-danger">As novas senhas não coincidem!</div>';
            } else {
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE {$user_table} SET nome = ?, email = ?, senha = ? WHERE id = ?");
                $stmt->execute([$nome, $email, $nova_senha_hash, $user_id]);
                $mensagem = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
                $usuario['nome'] = $nome;
                $usuario['email'] = $email;
                $usuario['senha'] = $nova_senha_hash;
                $_SESSION['user_nome'] = $nome;
            }
        } else {
            // Atualizar apenas nome e email
            $stmt = $db->prepare("UPDATE {$user_table} SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $user_id]);
            $mensagem = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
            $usuario['nome'] = $nome;
            $usuario['email'] = $email;
            $_SESSION['user_nome'] = $nome;
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
                        switch($usuario['tipo'] ?? ($_SESSION['user_tipo'] ?? '')) {
                            case 'Admin': echo 'danger'; break;
                            case 'Gestor': echo 'warning'; break;
                            case 'Usuario': echo 'success'; break;
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo htmlspecialchars($usuario['tipo'] ?? ($_SESSION['user_tipo'] ?? 'N/A')); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?php 
                        switch($usuario['status'] ?? '') {
                            case 'ativo': echo 'success'; break;
                            case 'inativo': echo 'danger'; break;
                            case 'pendente': echo 'warning'; break;
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo ucfirst(htmlspecialchars($usuario['status'] ?? 'não informado')); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Nível:</strong><br>
                    <span class="badge bg-info"><?php echo htmlspecialchars($usuario['nivel'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>ID da Conta:</strong><br>
                    <code><?php echo $user_id; ?></code>
                </div>
                
                <div class="mb-3">
                    <strong>Data de Cadastro:</strong><br>
                    <small class="text-muted">
                        <?php
                        $data_cadastro = $usuario['data_cadastro'] ?? ($usuario['created_at'] ?? null);
                        echo $data_cadastro ? date('d/m/Y H:i', strtotime($data_cadastro)) : 'Não informado';
                        ?>
                    </small>
                </div>
                
                <div class="mb-3">
                    <strong>Última Atualização:</strong><br>
                    <small class="text-muted">
                        <?php
                        $ultima_atualizacao = $usuario['ultima_atualizacao'] ?? ($usuario['updated_at'] ?? null);
                        echo $ultima_atualizacao ? date('d/m/Y H:i', strtotime($ultima_atualizacao)) : 'Não informado';
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 