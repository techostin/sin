<?php
/**
 * Template Padrão de Formulário - SINPORG
 * 
 * Uso:
 * 1. Copie este arquivo para seu módulo
 * 2. Defina as variáveis obrigatórias antes do include
 * 3. Personalize os campos no array $formFields
 * 
 * Variáveis obrigatórias:
 * - $page_title: string - Título da página
 * - $formAction: string - URL do action do form
 * - $formFields: array - Definição dos campos (ver exemplo abaixo)
 * - $submitLabel: string - Texto do botão submit (padrão: 'Salvar')
 * - $cancelUrl: string - URL do botão cancelar/voltar
 * - $hasAccess: bool - Se usuário tem permissão (padrão: true)
 * - $message: string - Mensagem de erro/sucesso
 * - $messageType: string - 'success'|'danger'|'warning'|'info'
 * - $extraScripts: string - Scripts extras (opcional)
 * 
 * Exemplo de $formFields:
 * $formFields = [
 *     'row1' => [
 *         ['nome', 'text', 'Nome', ['required' => true, 'col' => 'md-6']],
 *         ['sobrenome', 'text', 'Sobrenome', ['required' => true, 'col' => 'md-6']],
 *     ],
 *     'row2' => [
 *         ['email', 'email', 'E-mail', ['required' => true, 'col' => 'md-6']],
 *         ['telefone', 'tel', 'Telefone', ['col' => 'md-6', 'mask' => 'telefone']],
 *     ],
 * ];
 * 
 * Tipos suportados: text, email, password, date, datetime-local, tel, select, textarea, number, file
 * Opções: required, col, mask, options (para select), placeholder, readonly
 */

// Verificações de permissão padrão
if (!isset($hasAccess)) $hasAccess = true;
if (!isset($submitLabel)) $submitLabel = 'Salvar';
if (!isset($cancelUrl)) $cancelUrl = SITE_URL;
if (!isset($message)) $message = '';
if (!isset($messageType)) $messageType = 'danger';
if (!isset($extraScripts)) $extraScripts = '';

// Função helper para gerar campo
function renderField($field) {
    [$name, $type, $label, $options] = array_pad($field, 4, []);
    $required = !empty($options['required']) ? 'required' : '';
    $col = $options['col'] ?? 'md-6';
    $placeholder = $options['placeholder'] ?? '';
    $readonly = !empty($options['readonly']) ? 'readonly' : '';
    $mask = $options['mask'] ?? '';
    $value = isset($options['value']) ? htmlspecialchars($options['value']) : (htmlspecialchars($_POST[$name] ?? ''));
    $id = $options['id'] ?? $name;
    
    $html = "<div class=\"col-$col\">\n";
    $html .= "    <label for=\"$id\" class=\"form-label\">$label</label>\n";
    
    switch ($type) {
        case 'select':
            $html .= "    <select class=\"form-select\" id=\"$id\" name=\"$name\" $required $readonly>\n";
            if (isset($options['options'])) {
                foreach ($options['options'] as $optValue => $optLabel) {
                    $sel = (($_POST[$name] ?? '') == $optValue) ? 'selected' : '';
                    $html .= "        <option value=\"$optValue\" $sel>$optLabel</option>\n";
                }
            }
            $html .= "    </select>\n";
            break;
        case 'textarea':
            $rows = $options['rows'] ?? 3;
            $html .= "    <textarea class=\"form-control\" id=\"$id\" name=\"$name\" rows=\"$rows\" $required $readonly placeholder=\"$placeholder\">$value</textarea>\n";
            break;
        case 'file':
            $html .= "    <input type=\"file\" class=\"form-control\" id=\"$id\" name=\"$name\" accept=\"{$options['accept']}\" $required>\n";
            break;
        default:
            $inputType = $type === 'tel' ? 'tel' : ($type === 'email' ? 'email' : ($type === 'password' ? 'password' : 'text'));
            $maskAttr = $mask ? " data-mask=\"$mask\"" : '';
            $html .= "    <input type=\"$inputType\" class=\"form-control\" id=\"$id\" name=\"$name\" value=\"$value\" $required $readonly placeholder=\"$placeholder\"$maskAttr>\n";
    }
    
    $html .= "</div>\n";
    return $html;
}
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (isset($page_subtitle)): ?>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($page_subtitle); ?></p>
            <?php endif; ?>
        </div>
        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <?php if (!$hasAccess): ?>
    <div class="alert alert-danger">
        Acesso negado. Você não tem permissão para acessar esta página.
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i><?php echo htmlspecialchars($page_title); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" method="POST" <?php echo isset($enctype) ? 'enctype="multipart/form-data"' : ''; ?> class="row g-3 needs-validation" novalidate>
                <?php echo CSRFProtection::getTokenField(); ?>
                
                <?php if (isset($formFields)): ?>
                    <?php foreach ($formFields as $rowName => $fields): ?>
                        <div class="row g-3">
                            <?php foreach ($fields as $field): ?>
                                <?php echo renderField($field); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Campos manuais aqui se necessário -->
                <?php endif; ?>
                
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
</div>

<?php if ($extraScripts): ?>
<?php echo $extraScripts; ?>
<?php endif; ?>