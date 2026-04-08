<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Sanitizer.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
$message = '';
$message_type = '';

// Verificação de autenticação
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login.php");
    exit;
}

// Verificação de autorização (Nível)
$userNivel = isset($_SESSION['user_nivel']) ? (int) $_SESSION['user_nivel'] : null;
$hasAccess = in_array($userNivel, [NIVEL_ADMIN, NIVEL_GESTOR], true);

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$hasAccess) {
        die("Acesso negado.");
    }

    if (!CSRFProtection::validatePostRequest()) {
        $message = 'Erro de segurança: token inválido. Atualize a página e tente novamente.';
        $message_type = 'danger';
    } else {
        $conn = Database::getInstance()->getConnection();

        if (!$conn) {
            $message = 'Erro de conexão com o banco de dados.';
            $message_type = 'danger';
        } else {
            // Validação básica
            $nome = trim($_POST['nome'] ?? '');
            $sobrenome = trim($_POST['sobrenome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $senha_plain = $_POST['senha'] ?? '';
            $confirmar_senha = $_POST['confirmar_senha'] ?? '';

            $errors = [];
            if ($nome === '') {
                $errors[] = 'Nome é obrigatório.';
            }
            if ($sobrenome === '') {
                $errors[] = 'Sobrenome é obrigatório.';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Informe um e-mail válido.';
            }
            if ($senha_plain === '') {
                $errors[] = 'Senha é obrigatória.';
            }
            if ($senha_plain !== $confirmar_senha) {
                $errors[] = 'As senhas não conferem.';
            }

            if (!empty($errors)) {
                $message = implode('<br>', array_map('htmlspecialchars', $errors));
                $message_type = 'danger';
            } else {
                // Instancia Model
                $associadoModel = new Associado();
                $dados = $_POST;

                // Inicia transação
                $conn->beginTransaction();

                try {
                    // 1. Criar Associado
                    $resultado = $associadoModel->create($dados);

                    if (!is_numeric($resultado)) {
                        throw new Exception($resultado);
                    }

                    $associado_id = (int)$resultado;

                    // 2. Criar Usuário vinculado
                    $usuario_nome = trim($nome . ' ' . $sobrenome);
                    $usuario_email = $email;
                    $senha_hash = password_hash($senha_plain, PASSWORD_DEFAULT);
                    $tipo_usuario = isset($_POST['tipo']) ? strtolower((string)$_POST['tipo']) : 'usuario';
                    $nivel = isset($_POST['nivel']) ? (int) $_POST['nivel'] : (defined('NIVEL_USUARIO') ? NIVEL_USUARIO : 3);
                    $telefone_int = isset($_POST['telefone']) ? (int) preg_replace('/\D+/', '', (string)$_POST['telefone']) : null;

                    $sqlU = "INSERT INTO usuarios (nome, email, senha, nivel, status, telefone, tipo, associado_id, created_at) 
                             VALUES (?, ?, ?, ?, 'ativo', ?, ?, ?, NOW())";
                    $stmtU = $conn->prepare($sqlU);
                    $stmtU->execute([
                        $usuario_nome,
                        $usuario_email,
                        $senha_hash,
                        $nivel,
                        $telefone_int,
                        $tipo_usuario,
                        $associado_id
                    ]);

                    // Commit
                    $conn->commit();

                    $_SESSION['success_message'] = 'Associado e usuário criados com sucesso. ID: ' . $associado_id;
                    header('Location: ' . SITE_URL . '/associados');
                    exit();
                } catch (Exception $e) {
                    $conn->rollBack();
                    $message = 'Erro ao salvar: ' . htmlspecialchars($e->getMessage());
                    $message_type = 'danger';
                }
            }
        }
    }
}

$old = function ($key) {
    return htmlspecialchars((string)($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
$selectedEstado = (string)($_POST['estado'] ?? '');
$selectedCidade = (string)($_POST['cidade'] ?? '');
$selectedVinculo = (string)($_POST['vinculo'] ?? '');

// --- INÍCIO DO OUTPUT HTML ---
$page_title = "Cadastrar Associado";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Exibe erro de acesso visualmenete se não for POST (se for POST já morreu no die acima)
if (!$hasAccess) {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas administradores e gestores podem cadastrar associados.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<h1 class="mt-4">Cadastrar Novo Associado</h1>
<p>Preencha o formulário abaixo para adicionar um novo associado ao sistema.</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/associado/novo" method="POST" class="row g-3">
    <?php echo CSRFProtection::getTokenField(); ?>

    <div class="col-md-6">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $old('nome'); ?>" required>
    </div>
    <div class="col-md-6">
        <label for="sobrenome" class="form-label">Sobrenome</label>
        <input type="text" class="form-control" id="sobrenome" name="sobrenome" value="<?php echo $old('sobrenome'); ?>" required>
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo $old('email'); ?>" required>
    </div>
    <div class="col-md-6">
        <label for="data_nascimento" class="form-label">Data de Nascimento</label>
        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?php echo $old('data_nascimento'); ?>">
    </div>

    <div class="col-md-6">
        <label for="senha" class="form-label">Senha</label>
        <input type="password" class="form-control" id="senha" name="senha" required>
    </div>
    <div class="col-md-6">
        <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
    </div>

    <div class="col-md-6">
        <label for="cpf" class="form-label">CPF</label>
        <input type="text" class="form-control mask-cpf" id="cpf" name="cpf" value="<?php echo $old('cpf'); ?>" placeholder="000.000.000-00">
    </div>
    <div class="col-md-6">
        <label for="rg" class="form-label">RG</label>
        <input type="text" class="form-control mask-rg" id="rg" name="rg" value="<?php echo $old('rg'); ?>" placeholder="00.000.000-00">
    </div>

    <div class="col-12">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo $old('endereco'); ?>">
    </div>

    <div class="col-md-2">
        <label for="numero" class="form-label">Número</label>
        <input type="text" class="form-control" id="numero" name="numero" value="<?php echo $old('numero'); ?>">
    </div>
    <div class="col-md-4">
        <label for="complemento" class="form-label">Complemento</label>
        <input type="text" class="form-control" id="complemento" name="complemento" value="<?php echo $old('complemento'); ?>">
    </div>
    <div class="col-md-6">
        <label for="bairro" class="form-label">Bairro</label>
        <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo $old('bairro'); ?>">
    </div>

    <div class="col-md-4">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado" required>
            <option value="">Selecione um Estado</option>
            <?php
            $associado_helper = new Associado();
            $estados = $associado_helper->getEstados();
            foreach ($estados as $estado) {
                $selected = ((string)$estado['id'] === $selectedEstado) ? ' selected' : '';
                echo "<option value='{$estado['id']}'{$selected}>{$estado['nome']}</option>";
            }
            ?>
        </select>
    </div>
    <div class="col-md-5">
        <label for="cidade" class="form-label">Cidade</label>
        <select class="form-select" id="cidade" name="cidade" required <?php echo $selectedEstado === '' ? 'disabled' : ''; ?>>
            <option value=""><?php echo $selectedEstado === '' ? 'Selecione um Estado Primeiro' : 'Carregando...'; ?></option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="cep" class="form-label">CEP</label>
        <input type="text" class="form-control mask-cep" id="cep" name="cep" value="<?php echo $old('cep'); ?>" placeholder="00000-000">
    </div>
    <div class="col-md-2">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone"
            value="<?php echo $old('telefone'); ?>"
            placeholder="(00) 00000-0000">
    </div>

    <div class="col-md-12">
        <label for="vinculo" class="form-label">Associação</label>
        <select class="form-select" id="vinculo" name="vinculo">
            <option value="">Selecione uma Associação (Opcional)</option>
            <?php
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome ASC");
                $stmt->execute();
                $associacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($associacoes as $associacao) {
                    $nome_exibicao = $associacao['nome'];
                    if (!empty($associacao['sigla'])) {
                        $nome_exibicao .= ' (' . $associacao['sigla'] . ')';
                    }
                    $selected = ((string)$associacao['id'] === $selectedVinculo) ? ' selected' : '';
                    echo "<option value='{$associacao['id']}'{$selected}>{$nome_exibicao}</option>";
                }
            } catch (PDOException $e) {
                // Em caso de erro, não exibir opções
            }
            ?>
        </select>
        <small class="form-text text-muted">Selecione a associação à qual o associado pertence (opcional)</small>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Associado</button>
        <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
<script>
    const estadoSelect = document.getElementById('estado');
    const cidadeSelect = document.getElementById('cidade');
    const cidadeSelecionada = <?php echo json_encode($selectedCidade); ?>;

    function carregarCidades(estadoId, cidadePreSelecionada = '') {
        const cidadeSelect = document.getElementById('cidade');
        cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
        cidadeSelect.disabled = true;

        if (estadoId) {
            fetch(`<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=${estadoId}`)
                .then(response => response.json())
                .then(data => {
                    cidadeSelect.innerHTML = '<option value="">Selecione uma Cidade</option>';
                    data.forEach(cidade => {
                        const option = document.createElement('option');
                        option.value = cidade.id;
                        option.textContent = cidade.nome;
                        if (cidadePreSelecionada && String(cidade.id) === String(cidadePreSelecionada)) {
                            option.selected = true;
                        }
                        cidadeSelect.appendChild(option);
                    });
                    cidadeSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Erro ao buscar cidades:', error);
                    cidadeSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                });
        } else {
            cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
        }
    }

    estadoSelect.addEventListener('change', function () {
        carregarCidades(this.value);
    });

    if (estadoSelect.value) {
        carregarCidades(estadoSelect.value, cidadeSelecionada);
    }
</script>