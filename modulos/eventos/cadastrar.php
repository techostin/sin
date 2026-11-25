<?php
/**
 * Cadastrar Evento - Padrão Unificado
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
$message = '';
$message_type = '';

// Verificação de autenticação e autorização
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

if (!$user->hasPermission(NIVEL_GESTOR)) {
    $message = 'Você não tem permissão para acessar esta página.';
    $message_type = 'danger';
    $hasAccess = false;
} else {
    $hasAccess = true;
}

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasAccess) {
    if (!CSRFProtection::validatePostRequest()) {
        $message = 'Erro de segurança: token inválido.';
        $message_type = 'danger';
    } else {
        $db = Database::getInstance()->getConnection();
        try {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $data_evento = $_POST['data_evento'] ?? '';
            $local = trim($_POST['local'] ?? '');
            $status = $_POST['status'] ?? 'Agendado';

            if ($nome === '' || $data_evento === '') {
                $message = 'Nome e Data/Hora são obrigatórios.';
                $message_type = 'danger';
            } else {
                $stmt = $db->prepare("INSERT INTO eventos (nome, descricao, data_evento, local, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nome, $descricao, $data_evento, $local, $status]);
                $message = 'Evento cadastrado com sucesso!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Erro ao cadastrar: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Configuração da página
$page_title = 'Cadastrar Evento';
$page_subtitle = 'Preencha os dados do evento.';
$formAction = SITE_URL . '/eventos/cadastrar';
$cancelUrl = SITE_URL . '/eventos';
$submitLabel = 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? false;

$formFields = [
    'row1' => [
        ['nome', 'text', 'Nome', ['required' => true, 'col' => 'md-6']],
    ],
    'row2' => [
        ['descricao', 'textarea', 'Descrição', ['col' => 'md-12', 'rows' => 3]],
    ],
    'row3' => [
        ['data_evento', 'datetime-local', 'Data e Hora', ['required' => true, 'col' => 'md-6']],
        ['local', 'text', 'Local', ['col' => 'md-6']],
    ],
    'row4' => [
        ['status', 'select', 'Status', ['required' => true, 'col' => 'md-6', 'options' => [
            'Agendado' => 'Agendado',
            'Realizado' => 'Realizado',
            'Cancelado' => 'Cancelado',
        ]]],
    ],
];

$page_title = "Cadastrar Evento";
$page_subtitle = 'Preencha os dados do evento.';
$formAction = SITE_URL . '/eventos/cadastrar';
$cancelUrl = SITE_URL . '/eventos';
$submitLabel = 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? true;

$page_title = "Cadastrar Evento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-calendar-plus me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($page_subtitle); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <?php if (!$hasAccess): ?>
    <div class="alert alert-danger">Acesso negado.</div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-calendar-plus me-2"></i><?php echo htmlspecialchars($page_title); ?></h5>
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
                            $value = htmlspecialchars($_POST[$name] ?? '');
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
                                <textarea class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" rows="<?php echo $options['rows'] ?? 3; ?>" <?php echo $required; ?>><?php echo htmlspecialchars($_POST[$name] ?? ''); ?></textarea>
                            <?php else: ?>
                                <?php $inputType = in_array($type, ['email', 'password', 'date', 'datetime-local']) ? $type : 'text'; ?>
                                <input type="<?php echo $inputType; ?>" class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($_POST[$name] ?? ''); ?>" <?php echo $required; ?> placeholder="<?php echo htmlspecialchars($options['placeholder'] ?? ''); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>