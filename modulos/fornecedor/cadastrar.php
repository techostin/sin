<?php
/**
 * Cadastrar Fornecedor - Padrão Unificado
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Fornecedor.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Produto.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
$message = '';
$message_type = '';

// Permitir Admin, Gestor e Usuários comuns cadastrarem fornecedores
if (!$user->hasPermission(NIVEL_USUARIO)) {
    $message = 'Você não tem permissão para acessar esta página.';
    $message_type = 'danger';
    $hasAccess = false;
} else {
    $hasAccess = true;
}

// ---- Unidades acessíveis ao usuário para o bloco "Atende às unidades" ----
$produtoClass = new Produto();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $unidadesDisponiveis = (new Fornecedor())->getTodasUnidades();
    $todosTipos = ['propriedade', 'agroindustria', 'animal'];
    $tipoPadrao = '';
} else {
    $unidadesDisponiveis = $produtoClass->getUnidadesDoAssociado($user->getAssociadoId() ?? -1);
    $todosTipos = array_values(array_unique(array_filter(array_map(function ($u) {
        return $u['tipo'] ?? '';
    }, $unidadesDisponiveis))));
    $perfil = $user->perfilDashboard();
    if (in_array('agroindustria', $todosTipos, true) && $perfil === 'agroindustria') {
        $tipoPadrao = 'agroindustria';
    } elseif (in_array('propriedade', $todosTipos, true)) {
        $tipoPadrao = 'propriedade';
    } elseif (count($todosTipos) === 1) {
        $tipoPadrao = $todosTipos[0];
    } else {
        $tipoPadrao = $todosTipos[0] ?? '';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST') {
    $fornecedor = new Fornecedor();
    $resultado = $fornecedor->create($_POST);

    if (is_numeric($resultado)) {
        $permitidas = [];
        foreach ($unidadesDisponiveis as $ud) {
            $permitidas[($ud['tipo'] ?? '') . ':' . (int)($ud['id'] ?? 0)] = true;
        }
        $vincular = [];
        if (!empty($_POST['unidade_id'])) {
            foreach ($_POST['unidade_id'] as $idx => $uid) {
                $tipo = $_POST['unidade_tipo'][$idx] ?? '';
                if ($uid && $tipo) {
                    $chave = $tipo . ':' . (int)$uid;
                    if (isset($permitidas[$chave])) {
                        $vincular[] = ['tipo' => $tipo, 'id' => (int)$uid];
                    }
                }
            }
        }
        $fornecedor->vincularUnidades($resultado, $vincular);
        $message = 'Fornecedor cadastrado com sucesso!';
        $message_type = 'success';
    }
}

// Estados
$estados = (new Associado())->getEstados();
$tiposLabels = ['propriedade' => 'Propriedade', 'agroindustria' => 'Agroindústria', 'animal' => 'Criação Animal'];

// Configuração da página
$page_title = 'Cadastrar Fornecedor';
$page_subtitle = 'Cadastre um novo fornecedor para suas unidades produtivas.';
$formAction = SITE_URL . '/fornecedor/cadastrar';
$cancelUrl = SITE_URL . '/fornecedor';
$submitLabel = 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? true;

$formFields = [
    'row1' => [
        ['nome', 'text', 'Nome/Razão Social', ['required' => true, 'col' => 'md-8']],
        ['cnpj', 'text', 'CNPJ', ['col' => 'md-4', 'placeholder' => '00.000.000/0000-00']],
    ],
    'row2' => [
        ['razao_social', 'text', 'Razão Social', ['col' => 'md-6']],
        ['representante', 'text', 'Representante', ['col' => 'md-6']],
    ],
    'row3' => [
        ['email', 'email', 'E-mail', ['col' => 'md-6']],
        ['telefone', 'tel', 'Telefone', ['col' => 'md-6', 'placeholder' => '(00) 00000-0000']],
    ],
    'row4' => [
        ['produto_fornecido', 'text', 'Principal Produto/Serviço Fornecido', ['col' => 'md-12']],
    ],
    'row5' => [
        ['endereco', 'text', 'Endereço', ['col' => 'md-6']],
        ['bairro', 'text', 'Bairro', ['col' => 'md-3']],
        ['estado', 'select', 'Estado', ['required' => true, 'col' => 'md-3', 'options' => array_column($estados, 'nome', 'id')]],
    ],
];

$page_title = 'Cadastrar Fornecedor';
$page_subtitle = 'Cadastre um novo fornecedor para suas unidades produtivas.';
$formAction = SITE_URL . '/fornecedor/cadastrar';
$cancelUrl = SITE_URL . '/fornecedor';
$submitLabel = 'Cadastrar';
$message = $message ?? '';
$message_type = $message_type ?? 'danger';
$hasAccess = $hasAccess ?? true;

$page_title = "Cadastrar Fornecedor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-truck me-2"></i><?php echo htmlspecialchars($page_title); ?></h1>
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
            <h5 class="mb-0"><i class="bi bi-truck me-2"></i><?php echo htmlspecialchars($page_title); ?></h5>
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
                            <?php else: ?>
                                <?php $inputType = in_array($type, ['email', 'password', 'date']) ? $type : 'text'; ?>
                                <input type="<?php echo $inputType; ?>" class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($_POST[$name] ?? ''); ?>" <?php echo $required; ?> placeholder="<?php echo htmlspecialchars($options['placeholder'] ?? ''); ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="col-12 subtitulo-secao"><h6 class="fw-bold">Unidades atendidas por este fornecedor</h6></div>
                <div class="col-12">
                    <p class="text-muted small">Selecione em qual(is) unidade(s) produtiva(s) — <?php echo implode(', ', array_map(function($t){ return $tiposLabels[$t] ?? $t; }, $todosTipos ?: ['propriedade', 'agroindustria', 'animal'])); ?> — este fornecedor fornece produtos/serviços. Somente os associados dessas unidades verão este fornecedor.</p>
                    <div id="unidade-lista">
                        <div class="input-group mb-2 unidade-linha">
                            <select name="unidade_tipo[]" class="form-select unidade-tipo" aria-label="Tipo de unidade">
                                <?php foreach ($todosTipos as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo ($t === $tipoPadrao) ? 'selected' : ''; ?>><?php echo $tiposLabels[$t] ?? $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="unidade_id[]" class="form-select unidade-select" aria-label="Unidade">
                                <option value="">Selecione a unidade...</option>
                                <?php foreach ($todosTipos as $t): ?>
                                    <optgroup label="<?php echo $tiposLabels[$t] ?? $t; ?>" data-tipo="<?php echo $t; ?>">
                                        <?php foreach ($unidadesDisponiveis as $ud): ?>
                                            <?php if (($ud['tipo'] ?? '') === $t): ?>
                                            <option value="<?php echo $ud['id']; ?>" data-tipo="<?php echo $ud['tipo']; ?>"><?php echo htmlspecialchars($ud['nome']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-danger remover-unidade" aria-label="Remover linha">Remover</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-unidade">+ Adicionar unidade</button>
                    <p class="text-muted small">Deixe vazio para que este fornecedor fique visível apenas a administradores/gestores.</p>
                </div>

                <div class="col-12 mt-4">
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
    const unidadeLista = document.getElementById('unidade-lista');
    const addBtn = document.getElementById('add-unidade');
    const todosTipos = <?php echo json_encode($todosTipos); ?>;
    const todosTiposLabels = <?php echo json_encode($tiposLabels); ?>;
    const unidadesDisponiveis = <?php echo json_encode($unidadesDisponiveis); ?>;

    function mostraGrupos(selectTipo, selectUnid) {
        const tipo = selectTipo.value;
        selectUnid.querySelectorAll('optgroup').forEach(function(g) {
            g.hidden = Boolean(tipo) && g.dataset.tipo !== tipo;
        });
        const opt = selectUnid.options[selectUnid.selectedIndex];
        if (opt && opt.dataset && opt.dataset.tipo && tipo && opt.dataset.tipo !== tipo) {
            selectUnid.value = '';
        }
    }

    unidadeLista.addEventListener('change', function(e) {
        if (e.target.classList.contains('unidade-tipo')) {
            const linha = e.target.closest('.unidade-linha');
            mostraGrupos(e.target, linha.querySelector('.unidade-select'));
        }
    });

    unidadeLista.addEventListener('click', function(e) {
        if (e.target.classList.contains('remover-unidade')) {
            e.target.closest('.unidade-linha').remove();
        }
    });

    addBtn.addEventListener('click', function() {
        const linha = document.createElement('div');
        linha.className = 'input-group mb-2 unidade-linha';
        linha.innerHTML = `
            <select name="unidade_tipo[]" class="form-select unidade-tipo" aria-label="Tipo de unidade">
                ${todosTipos.map(t => `<option value="${t}">${todosTiposLabels[t] ?? t}</option>`).join('')}
            </select>
            <select name="unidade_id[]" class="form-select unidade-select" aria-label="Unidade">
                <option value="">Selecione a unidade...</option>
                ${todosTipos.map(t => `<optgroup label="${todosTiposLabels[t] ?? t}" data-tipo="${t}">${unidadesDisponiveis.filter(u => (u['tipo'] ?? '') === t).map(u => `<option value="${u['id']}" data-tipo="${u['tipo']}">${u['nome']}</option>`).join('')}</optgroup>`).join('')}
            </select>
            <button type="button" class="btn btn-outline-danger remover-unidade" aria-label="Remover linha">Remover</button>
        `;
        unidadeLista.appendChild(linha);
        mostraGrupos(linha.querySelector('.unidade-tipo'), linha.querySelector('.unidade-select'));
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>