<?php
/**
 * Cadastrar Associado - Padrão Unificado
 */
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
    header("Location: " . SITE_URL . "/login");
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
                $conn->beginTransaction();

                try {
                    // 1. Criar Associado
                    $associadoModel = new Associado();
                    $dados = $_POST;
                    $resultado = $associadoModel->create($dados);

                    if (!is_numeric($resultado)) {
                        throw new Exception($resultado);
                    }
                    $associado_id = $resultado;

                    // 2. Criar Usuário vinculado
                    $nivel = NIVEL_USUARIO;
                    $telefone_int = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
                    $tipo_usuario = $_POST['tipo_usuario'] ?? 'agricultor';
                    $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("
                        INSERT INTO usuarios (nome, email, senha, nivel, telefone, tipo, associado_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['nome'],
                        $_POST['email'],
                        $senha_hash,
                        $nivel,
                        $telefone_int,
                        $tipo_usuario,
                        $associado_id
                    ]);

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
$selectedAssociacoes = isset($_POST['associacoes']) && is_array($_POST['associacoes'])
    ? array_map('intval', $_POST['associacoes'])
    : [];
$associacoesAtivas = (new Associado())->getAssociacoesAtivas();
$estados = (new Associado())->getEstados();

// Definição dos campos do formulário
$formFields = [
    'row1' => [
        ['nome', 'text', 'Nome', ['required' => true, 'col' => 'md-6', 'value' => $old('nome')]],
        ['sobrenome', 'text', 'Sobrenome', ['required' => true, 'col' => 'md-6', 'value' => $old('sobrenome')]],
    ],
    'row2' => [
        ['email', 'email', 'E-mail', ['required' => true, 'col' => 'md-6', 'value' => $old('email')]],
        ['data_nascimento', 'date', 'Data de Nascimento', ['col' => 'md-6', 'value' => $old('data_nascimento')]],
    ],
    'row3' => [
        ['senha', 'password', 'Senha', ['required' => true, 'col' => 'md-6']],
        ['confirmar_senha', 'password', 'Confirmar Senha', ['required' => true, 'col' => 'md-6']],
    ],
    'row4' => [
        ['cpf', 'text', 'CPF', ['col' => 'md-6', 'value' => $old('cpf'), 'placeholder' => '000.000.000-00', 'mask' => 'cpf']],
        ['rg', 'text', 'RG', ['col' => 'md-6', 'value' => $old('rg'), 'placeholder' => '00.000.000-00', 'mask' => 'rg']],
    ],
    'row5' => [
        ['tipo_usuario', 'select', 'Perfil de Atuação', ['col' => 'md-6', 'options' => [
            'agricultor' => 'Agricultor',
            'produtor_agroindustria' => 'Produtor de Agroindústria',
            'agricultor_agroindustria' => 'Agricultor com Agroindústria',
        ]]],
    ],
    'row6' => [
        ['tipo_associado', 'select', 'Tipo de Associado', ['col' => 'md-4', 'options' => [
            'Efetivo' => 'Efetivo',
            'Honorario' => 'Honorário',
            'Benemerito' => 'Benemérito',
            'Dependente' => 'Dependente',
            'Aspirante' => 'Aspirante',
        ]]],
        ['status_associado', 'select', 'Status do Associado', ['col' => 'md-4', 'options' => [
            'Ativo' => 'Ativo',
            'Inadimplente' => 'Inadimplente',
            'Afastado' => 'Afastado',
            'Desligado' => 'Desligado',
            'Falecido' => 'Falecido',
        ]]],
        ['data_admissao', 'date', 'Data de Admissão', ['col' => 'md-4', 'value' => $old('data_admissao')]],
    ],
    'row7' => [
        ['profissao', 'text', 'Profissão', ['col' => 'md-6', 'value' => $old('profissao')]],
        ['data_desligamento', 'date', 'Data de Desligamento', ['col' => 'md-6', 'value' => $old('data_desligamento')]],
    ],
    'row8' => [
        ['endereco', 'text', 'Endereço', ['col' => 'md-6', 'value' => $old('endereco')]],
        ['numero', 'text', 'Número', ['col' => 'md-2', 'value' => $old('numero')]],
        ['complemento', 'text', 'Complemento', ['col' => 'md-4', 'value' => $old('complemento')]],
    ],
    'row9' => [
        ['bairro', 'text', 'Bairro', ['col' => 'md-4', 'value' => $old('bairro')]],
        ['cep', 'text', 'CEP', ['col' => 'md-4', 'value' => $old('cep'), 'placeholder' => '00000-000', 'mask' => 'cep']],
        ['cidade', 'text', 'Cidade', ['col' => 'md-4', 'value' => $old('cidade'), 'readonly' => true]],
    ],
    'row10' => [
        ['estado', 'select', 'Estado', ['col' => 'md-6', 'options' => array_column($estados, 'nome', 'id')]],
    ],
];

// Associações
$assoc_ativas = $associacoesAtivas;
$assoc_selecionadas = $selectedAssociacoes;

// Configuração da página
$page_title = 'Cadastrar Associado';
$page_subtitle = 'Preencha o formulário abaixo para adicionar um novo associado ao sistema.';
$formAction = SITE_URL . '/associado/novo';
$cancelUrl = SITE_URL . '/associados';
$submitLabel = 'Cadastrar';
$isViewMode = false;
$enctype = false;
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess;

$page_title = "Cadastrar Associado";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-plus-fill me-2" style="color:var(--am-primary);"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($page_subtitle); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar à lista
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <?php if (!$hasAccess): ?>
    <div class="alert alert-danger">Acesso negado. Apenas administradores e gestores podem cadastrar associados.</div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2" style="color:var(--am-primary);"></i>Dados do Associado</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="row g-3 needs-validation" novalidate>
                <?php echo CSRFProtection::getTokenField(); ?>

                <?php foreach ($formFields as $rowName => $fields): ?>
                    <div class="row g-3">
                        <?php foreach ($fields as $field): 
                            [$name, $type, $label, $options] = array_pad($field, 4, []);
                            $required = !empty($options['required']) ? 'required' : '';
                            $col = $options['col'] ?? 'md-6';
                            $placeholder = $options['placeholder'] ?? '';
                            $readonly = !empty($options['readonly']) ? 'readonly' : '';
                            $mask = $options['mask'] ?? '';
                            $value = $options['value'] ?? (htmlspecialchars($_POST[$name] ?? ''));
                            $id = $options['id'] ?? $name;
                        ?>
                        <div class="col-<?php echo $col; ?>">
                            <label for="<?php echo $id; ?>" class="form-label"><?php echo htmlspecialchars($label); ?></label>
                            <?php if ($type === 'select'): ?>
                                <select class="form-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $required; ?>>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($options['options'] as $val => $optLabel): ?>
                                        <option value="<?php echo $val; ?>" <?php echo (($_POST[$name] ?? '') == $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($optLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" rows="3" <?php echo $required; ?>><?php echo $value; ?></textarea>
                            <?php else: ?>
                                <?php $inputType = in_array($type, ['email', 'password', 'date']) ? $type : 'text'; ?>
                                <input type="<?php echo $inputType; ?>" class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" <?php echo $required; ?> placeholder="<?php echo htmlspecialchars($options['placeholder'] ?? ''); ?>" <?php echo $mask ? 'data-mask="' . $mask . '"' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Associações -->
                <div class="col-12">
                    <label class="form-label">Associações</label>
                    <div class="border rounded p-3 assoc-checkbox-grid" style="max-height:220px; overflow-y:auto;">
                        <?php if (empty($associacoesAtivas)): ?>
                            <div class="text-muted small">Nenhuma associação ativa cadastrada.</div>
                        <?php else: ?>
                            <?php foreach ($associacoesAtivas as $assoc): ?>
                                <?php $nome_assoc = !empty($assoc['sigla']) ? $assoc['sigla'] : $assoc['nome']; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="associacoes[]" value="<?php echo $assoc['id']; ?>" id="assoc_<?php echo $assoc['id']; ?>"
                                        <?php echo in_array((int)$assoc['id'], $selectedAssociacoes, true) ? 'checked' : ''; ?>
                                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="assoc_<?php echo $assoc['id']; ?>">
                                        <?php echo htmlspecialchars($nome_assoc); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <small class="form-text text-muted">Selecione uma ou mais associações às quais o associado pertence.</                    <input type="hidden" name="associacoes_enviado" value="1">
                </div>

                <div class="col-12 mt-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> <?php echo htmlspecialchars($submitLabel); ?>
                        </button>
                        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x me-1"></i> Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var estadoSelect = document.getElementById('estado');
    var cidadeSelect = document.getElementById('cidade');

    function carregarCidades(estadoId, cidadePreSelecionada = '') {
        const cidadeSelect = document.getElementById('cidade');
        cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
        cidadeSelect.disabled = true;

        if (estadoId) {
            fetch('<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=' + estadoId)
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
                    cidadeSelect.disabled = false;
                });
        } else {
            cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
            cidadeSelect.disabled = true;
        }
    }

    estadoSelect.addEventListener('change', function () {
        carregarCidades(this.value);
    });

    if (estadoSelect.value) {
        carregarCidades(estadoSelect.value, cidadeSelecionada);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>