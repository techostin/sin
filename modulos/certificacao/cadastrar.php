<?php
/**
 * Cadastrar/Editar Certificação - Padrão Unificado
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
$message = '';
$message_type = '';

// Verificação de autenticação
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

// Apenas Admin/Gestor podem cadastrar ou editar certificações
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $message = 'Acesso restrito a gestores e administradores.';
    $message_type = 'danger';
    $hasAccess = false;
} else {
    $hasAccess = true;
}

$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$certificacaoId = $isEdit ? (int)$_GET['id'] : null;

// Buscar certificação se edição
$certificacao = null;
if ($isEdit) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM certificacao_participativa WHERE id = ?");
    $stmt->execute([$certificacaoId]);
    $certificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificacao) {
        $message = 'Certificação não encontrada.';
        $message_type = 'danger';
        $hasAccess = false;
    }
}

// Buscar associados para o select
$db = Database::getInstance()->getConnection();
$stmtAssociados = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome");
$associados = $stmtAssociados->fetchAll(PDO::FETCH_ASSOC);

// Buscar certificadoras para o select
$stmtCertificadoras = $db->query("SELECT id, nome FROM certificadoras ORDER BY nome");
$certificadoras = $stmtCertificadoras->fetchAll(PDO::FETCH_ASSOC);

// Determinar sigla da certificadora vinculada ao gestor (se aplicável)
$gestorCertificadoraSigla = '';
if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_GESTOR) {
    $currentUser = $user->getCurrentUser();
    if ($currentUser && isset($currentUser['certificadora_id'])) {
        foreach ($certificadoras as $cert) {
            if ($cert['id'] == $currentUser['certificadora_id']) {
                $gestorCertificadoraSigla = $cert['sigla'];
                break;
            }
        }
    }
}

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasAccess) {
    if (!CSRFProtection::validatePostRequest()) {
        $message = 'Erro de segurança: token inválido.';
        $message_type = 'danger';
    } else {
        $db = Database::getInstance()->getConnection();
        try {
            $dados = $_POST;
            
            if ($isEdit) {
                // Atualizar
                $sets = [];
                $params = [':id' => $certificacaoId];
                
                $campos = ['associado_id', 'certificadora_id', 'numero_certificado', 'tipo_certificacao', 
                          'data_emissao', 'data_vencimento', 'status', 'escopo', 'documento_url',
                          'declaracao_compromisso_url', 'observacoes'];
                
                foreach ($campos as $campo) {
                    if (isset($dados[$campo])) {
                        $sets[] = "$campo = :$campo";
                        $params[":$campo"] = $dados[$campo];
                    }
                }
                
                $sql = "UPDATE certificacao_participativa SET " . implode(', ', $sets) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $message = 'Certificação atualizada com sucesso!';
                $message_type = 'success';
            } else {
                // Inserir nova
                $campos = ['associado_id', 'certificadora_id', 'numero_certificado', 'tipo_certificacao', 
                          'data_emissao', 'data_vencimento', 'status', 'escopo', 'documento_url',
                          'declaracao_compromisso_url', 'observacoes'];
                
                $sets = [];
                $params = [];
                
                foreach ($campos as $campo) {
                    if (isset($dados[$campo])) {
                        $sets[] = ":$campo";
                        $params[":$campo"] = $dados[$campo];
                    }
                }
                
                $sql = "INSERT INTO certificacao_participativa (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $sets) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $certificacaoId = $db->lastInsertId();
                
                $message = 'Certificação cadastrada com sucesso!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Erro ao salvar: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Preparar dados para o formulário
$produtosSelecionados = [];
if ($isEdit) {
    try {
        $stmt = $db->prepare("SELECT produto_id FROM certificacao_produtos WHERE certificacao_id = ?");
        $stmt->execute([$certificacaoId]);
        $produtosSelecionados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Tabela certificacao_produtos não existe, ignora silenciosamente
        $produtosSelecionados = [];
    }
}

// Configuração da página
$page_title = $isEdit ? 'Editar Certificação' : 'Nova Certificação';
$page_subtitle = 'Preencha os dados da certificação participativa.';
$formAction = $isEdit ? SITE_URL . "/certificacao/{$certificacaoId}/editar" : SITE_URL . '/certificacao/novo';
$cancelUrl = SITE_URL . '/certificacoes';
$submitLabel = $isEdit ? 'Atualizar' : 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? true;

$formFields = [
    'row1' => [
        ['associado_id', 'select', 'Associado Produtor', ['required' => true, 'col' => 'md-6', 'options' => array_column($associados, 'nome', 'id')]],
        ['certificadora_id', 'select', 'Organismo  Certificador', ['required' => true, 'col' => 'md-6', 'options' => array_column($certificadoras, 'nome', 'id')]],
    ],
    'row2' => [
        ['numero_certificado', 'text', 'Número do Certificado', ['required' => true, 'col' => 'md-6']],
        ['tipo_certificacao', 'select', 'Tipo de Certificação', ['required' => true, 'col' => 'md-6', 'options' => [
            'OCS' => 'OCS - Organismo de Certificação Social',
            'SPG' => 'SPG - Sistema Participativo de Garantia',
        ]]],
    ],
    'row3' => [
        ['data_emissao', 'date', 'Data de Emissão', ['required' => true, 'col' => 'md-6']],
        ['data_vencimento', 'date', 'Data de Vencimento', ['required' => true, 'col' => 'md-6']],
    ],
    'row4' => [
        ['status', 'select', 'Status', ['required' => true, 'col' => 'md-4', 'options' => [
            'Ativa' => 'Ativa',
            'Suspensa' => 'Suspensa',
            'Cancelada' => 'Cancelada',
            'Vencida' => 'Vencida',
        ]]],
        ['escopo', 'select', 'Escopo', ['required' => true, 'col' => 'md-4', 'options' => [
            'Producao' => 'Produção',
            'Processamento' => 'Processamento',
            'Comercializacao' => 'Comercialização',
            'Completo' => 'Completo (Produção + Processamento + Comercialização)',
        ]]],
    ],
];

$page_title = $isEdit ? 'Editar Certificação' : 'Nova Certificação';
$page_subtitle = 'Preencha os dados da certificação participativa.';
$formAction = $isEdit ? SITE_URL . "/certificacao/{$certificacaoId}/editar" : SITE_URL . '/certificacao/novo';
$cancelUrl = SITE_URL . '/certificacoes';
$submitLabel = $isEdit ? 'Atualizar' : 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? true;

$page_title = $isEdit ? 'Editar Certificação' : 'Nova Certificação';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-award me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
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
            <h5 class="mb-0"><i class="bi bi-award me-2"></i><?php echo htmlspecialchars($page_title); ?></h5>
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
                            $value = isset($certificacao[$name]) ? htmlspecialchars($certificacao[$name]) : htmlspecialchars($_POST[$name] ?? '');
                            $id = $options['id'] ?? $name;
                        ?>
                        <div class="col-<?php echo $col; ?>">
                            <label for="<?php echo $id; ?>" class="form-label"><?php echo htmlspecialchars($label); ?></label>
                            <?php if ($type === 'select'): ?>
                                <select class="form-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $required; ?>>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($options['options'] as $val => $optLabel): ?>
                                        <option value="<?php echo $val; ?>" <?php echo (($certificacao[$name] ?? $_POST[$name] ?? '') == $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($optLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" rows="3" <?php echo $required; ?>><?php echo $value; ?></textarea>
                            <?php else: ?>
                                <?php $inputType = in_array($type, ['email', 'password', 'date', 'url']) ? $type : 'text'; ?>
                                <input type="<?php echo $inputType; ?>" class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" <?php echo $required; ?> placeholder="<?php echo htmlspecialchars($options['placeholder'] ?? ''); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Escopo (condicional) -->
                <div class="row g-3" id="secaoEscopo">
                    <div class="col-md-6">
                        <label for="escopo" class="form-label">Escopo</label>
                        <select class="form-select" id="escopo" name="escopo" required>
                            <option value="">Selecione...</option>
                            <?php foreach (['Producao' => 'Produção', 'Processamento' => 'Processamento', 'Comercializacao' => 'Comercialização', 'Completo' => 'Completo'] as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo (($certificacao['escopo'] ?? $_POST['escopo'] ?? '') == $val) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- URLs de Documentos -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="documento_url" class="form-label">URL do Certificado</label>
                        <input type="url" class="form-control" id="documento_url" name="documento_url" value="<?php echo htmlspecialchars($certificacao['documento_url'] ?? ''); ?>" placeholder="https://exemplo.com/certificado.pdf">
                    </div>
                    <div class="col-md-6">
                        <label for="declaracao_compromisso_url" class="form-label">URL da Declaração de Compromisso</label>
                        <input type="url" class="form-control" id="declaracao_compromisso_url" name="declaracao_compromisso_url" value="<?php echo htmlspecialchars($certificacao['declaracao_compromisso_url'] ?? ''); ?>" placeholder="https://exemplo.com/declaracao.pdf">
                    </div>
                </div>

                <!-- Observações -->
                <div class="row g-3">
                    <div class="col-12">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?php echo htmlspecialchars($certificacao['observacoes'] ?? ''); ?></textarea>
                    </div>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>