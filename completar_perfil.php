<?php
require_once 'config/config.php';
require_once 'classes/User.php';

$user = new User();

// Verificar se o usuário está logado
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

// Se o perfil já estiver completo, redirecionar
if ($user->hasPerfilCompleto()) {
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
        header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
    } else {
        header("Location: " . SITE_URL . "/");
    }
    exit;
}

// Determinar quais campos estão faltando baseado no nível
$nivel = $_SESSION['user_nivel'] ?? null;
$currentUser = $user->getCurrentUser();
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados = [];
    
    // Gestor: orgao, setor, especialidade
    if ($nivel == NIVEL_GESTOR) {
        if (isset($_POST['orgao'])) $dados['orgao'] = $_POST['orgao'];
        if (isset($_POST['setor'])) $dados['setor'] = $_POST['setor'];
        if (isset($_POST['especialidade'])) $dados['especialidade'] = $_POST['especialidade'];
    }
    
    // Usuario: nicho
    if ($nivel == NIVEL_USUARIO) {
        if (isset($_POST['nicho'])) $dados['nicho'] = $_POST['nicho'];
    }
    
    if (!empty($dados) && $user->updatePerfil($dados)) {
        $success_message = "Perfil atualizado com sucesso!";
        
        // Redirecionar após atualizar
        if ($nivel == NIVEL_USUARIO) {
            header("Location: " . SITE_URL . "/caderno-campo/dashboard-agricultor");
        } else {
            header("Location: " . SITE_URL . "/");
        }
        exit;
    } else {
        $error_message = "Erro ao atualizar perfil. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Perfil - <?php echo SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .card-header {
            background-color: #28a745;
            color: white;
            text-align: center;
            padding: 1.5rem;
        }
        .card-header h3 {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3><?php echo SITE_TITLE; ?></h3>
            </div>
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-4">Complete seu Perfil</h5>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <p class="text-muted mb-4">Preencha os campos abaixo para continuar usando o sistema.</p>
                
                <form action="<?php echo SITE_URL; ?>/completar-perfil" method="POST">
                    <?php if ($nivel == NIVEL_GESTOR): ?>
                        <div class="mb-3">
                            <label for="orgao" class="form-label">Órgão</label>
                            <input type="text" class="form-control" id="orgao" name="orgao" 
                                   value="<?php echo htmlspecialchars($currentUser['orgao'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="setor" class="form-label">Setor</label>
                            <input type="text" class="form-control" id="setor" name="setor" 
                                   value="<?php echo htmlspecialchars($currentUser['setor'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="especialidade" class="form-label">Especialidade</label>
                            <input type="text" class="form-control" id="especialidade" name="especialidade" 
                                   value="<?php echo htmlspecialchars($currentUser['especialidade'] ?? ''); ?>" required>
                        </div>
                    <?php elseif ($nivel == NIVEL_USUARIO): ?>
                        <div class="mb-3">
                            <label for="tipo_usuario" class="form-label">Tipo de Usuário</label>
                            <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                                <option value="">Selecione...</option>
                                <option value="agricultor" <?php echo ($currentUser['tipo_usuario'] ?? '') == 'agricultor' ? 'selected' : ''; ?>>Agricultor</option>
                                <option value="produtor_agroindustria" <?php echo ($currentUser['tipo_usuario'] ?? '') == 'produtor_agroindustria' ? 'selected' : ''; ?>>Produtor de Agroindústria</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="nicho" class="form-label">Nicho de Atuação</label>
                            <select class="form-select" id="nicho" name="nicho" required>
                                <option value="">Selecione...</option>
                                <option value="agricultura_familiar" <?php echo ($currentUser['nicho'] ?? '') == 'agricultura_familiar' ? 'selected' : ''; ?>>Agricultura Familiar</option>
                                <option value="producao_organica" <?php echo ($currentUser['nicho'] ?? '') == 'producao_organica' ? 'selected' : ''; ?>>Produção Orgânica</option>
                                <option value="agroecologia" <?php echo ($currentUser['nicho'] ?? '') == 'agroecologia' ? 'selected' : ''; ?>>Agroecologia</option>
                                <option value="extrativismo" <?php echo ($currentUser['nicho'] ?? '') == 'extrativismo' ? 'selected' : ''; ?>>Extrativismo Sustentável</option>
                                <option value="outro" <?php echo ($currentUser['nicho'] ?? '') == 'outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div> REPLACE
                    <?php endif; ?>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">Salvar e Continuar</button>
                    </div>
                </form>
            </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
