<?php
$page_title = "Cadastrar Produto";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/Lavoura.php'; // Para buscar lavouras

$message = '';
$message_type = '';

// Apenas usuários logados
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lavoura_obj = new Lavoura();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $lavouras = $lavoura_obj->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $lavouras = $lavoura_obj->getByAssociadoId($associado_id);
}

$produto_obj = new Produto();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $unidades = [];
    // Gestor/Admin pode escolher qualquer unidade: não listamos aqui (usa
    // apenas lavoura). Para usuário comum, listamos as unidades dele.
} else {
    $unidades = $produto_obj->getUnidadesDoAssociado($user->getAssociadoId() ?? -1);
}

// Perfil exclusivamente de agroindústria: quantidade e data de colheita/produção
// não são digitadas no produto — vêm da Ordem de Produção (quantidade_final_obtida).
$ehUsuarioAgroindustria = ($user->perfilDashboard() === 'agroindustria');
$qtdDesabilitado = $ehUsuarioAgroindustria ? 'disabled' : '';

// Setores de origem (agroindústria): carrega apenas para perfil agroindústria
$setores = [];
if ($ehUsuarioAgroindustria) {
    require_once __DIR__ . '/../../../classes/AgroSetor.php';
    $setorClass = new AgroSetor();
    $setores = $setorClass->getSetoresDoAssociado($user->getAssociadoId() ?? -1);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST') {
    $produto = new Produto();

    $dados_produto = [];
    foreach ($_POST as $key => $value) {
        $dados_produto[$key] = htmlspecialchars(strip_tags(trim($value)));
    }
    // Sempre grava o dono
    if (!$user->hasPermission(NIVEL_GESTOR)) {
        $dados_produto['associado_id'] = $user->getAssociadoId();
    } elseif (isset($dados_produto['associado_id']) && $dados_produto['associado_id'] === '') {
        unset($dados_produto['associado_id']);
    }

    if ($produto->create($dados_produto)) {
        $message = "Produto cadastrado com sucesso!";
        $message_type = "success";
        $_POST = [];
    } else {
        $message = "Erro ao cadastrar produto.";
        $message_type = "danger";
    }
}
?>

<h1 class="mt-4">Cadastrar Novo Produto</h1>
<p>Preencha o formulário abaixo para adicionar um novo produto.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/caderno-campo/produto/novo" method="POST" class="row g-3">
    
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Produto</label>
        <input type="text" class="form-control" id="nome" name="nome" required>
    </div>
    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo/Variedade</label>
        <input type="text" class="form-control" id="tipo" name="tipo">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"></textarea>
    </div>

    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <select class="form-select" id="unidade_medida" name="unidade_medida">
            <option value="kg">Quilograma (kg)</option>
            <option value="unidade">Unidade</option>
            <option value="caixa">Caixa</option>
            <option value="litro">Litro</option>
            <option value="saca">Saca</option>
        </select>
    </div>
    <div class="col-md-4">
        <label for="preco_unitario" class="form-label">Preço Unitário (R$)</label>
        <input type="text" class="form-control" id="preco_unitario" name="preco_unitario">
    </div>
    <div class="col-md-4">
        <label for="quantidade_produzida" class="form-label">Quantidade Produzida</label>
        <input type="text" class="form-control" id="quantidade_produzida" name="quantidade_produzida" <?php echo $qtdDesabilitado; ?>>
        <div class="form-text" id="qtd-helper" style="<?php echo $ehUsuarioAgroindustria ? '' : 'display:none;'; ?>">Para produtos da agroindústria, a quantidade é registrada na Ordem de Produção.</div>
    </div>
    <div class="col-md-4">
        <label for="data_colheita" class="form-label">Data de Colheita/Produção</label>
        <input type="date" class="form-control" id="data_colheita" name="data_colheita" <?php echo $qtdDesabilitado; ?>>
    </div>

    <?php if ($ehUsuarioAgroindustria): ?>
    <div class="col-md-12">
        <label for="setor_id" class="form-label">Setor de Origem (opcional)</label>
        <select class="form-select" id="setor_id" name="setor_id">
            <option value="">Selecione o setor...</option>
            <?php foreach ($setores as $set): ?>
                <?php
                    $selectedSetor = (isset($_POST['setor_id']) && $_POST['setor_id'] == $set['id']) ? 'selected' : '';
                ?>
                <option value="<?php echo $set['id']; ?>" data-agro="<?php echo $set['agroindustria_id']; ?>" <?php echo $selectedSetor; ?>>
                    <?php echo htmlspecialchars($set['nome'] . ' (' . $set['agroindustria_nome'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Selecione o setor da agroindústria de origem.</div>
    </div>
    <?php else: ?>
    <div class="col-md-12">
        <label for="lavoura_id" class="form-label">Lavoura de Origem (opcional)</label>
        <select class="form-select" id="lavoura_id" name="lavoura_id">
            <option value="">Selecione uma Lavoura</option>
            <?php foreach ($lavouras as $lav): ?>
                <?php
                    $selected = (isset($_POST['lavoura_id']) && $_POST['lavoura_id'] == $lav['id']) ? 'selected' : '';
                ?>
                <option value="<?php echo $lav['id']; ?>" <?php echo $selected; ?>>
                    <?php echo $lav['nome'] . " (" . $lav['propriedade_nome'] . ")"; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Selecione para produto agrícola.</div>
    </div>
    <?php endif; ?>

    <div class="col-md-6">
        <label for="unidade_tipo" class="form-label">Unidade de Origem</label>
        <select class="form-select" id="unidade_tipo" name="unidade_tipo">
            <option value="">Selecione o tipo de unidade...</option>
            <option value="propriedade" <?php echo (isset($_POST['unidade_tipo']) && $_POST['unidade_tipo'] == 'propriedade') ? 'selected' : ''; ?>>Propriedade</option>
            <option value="agroindustria" <?php echo (isset($_POST['unidade_tipo']) && $_POST['unidade_tipo'] == 'agroindustria') ? 'selected' : ''; ?>>Agroindústria</option>
            <option value="animal" <?php echo (isset($_POST['unidade_tipo']) && $_POST['unidade_tipo'] == 'animal') ? 'selected' : ''; ?>>Criação Animal</option>
        </select>
    </div>
    <div class="col-md-6">
        <label for="unidade_id" class="form-label">Unidade Específica</label>
        <select class="form-select" id="unidade_id" name="unidade_id">
            <option value="">Selecione a unidade...</option>
            <?php foreach ($unidades as $un): ?>
                <option value="<?php echo $un['id']; ?>" data-tipo="<?php echo $un['tipo']; ?>"
                    <?php echo (isset($_POST['unidade_tipo']) && $_POST['unidade_tipo'] == $un['tipo'] && isset($_POST['unidade_id']) && $_POST['unidade_id'] == $un['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($un['nome'] . ' (' . ($un['tipo'] == 'propriedade' ? 'Propriedade' : ($un['tipo'] == 'agroindustria' ? 'Agroindústria' : 'Criação Animal')) . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Produto</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Produtos
        </a>
    </div>
</form>

<script>
(function() {
    var tipoSel = document.getElementById('unidade_tipo');
    var unidSel = document.getElementById('unidade_id');
    var lavouraSel = document.getElementById('lavoura_id');
    var setorSel = document.getElementById('setor_id');
    var qtdInput = document.getElementById('quantidade_produzida');
    var dataInput = document.getElementById('data_colheita');
    var qtdHelper = document.getElementById('qtd-helper');
    var ehAgro = false;
    var usuarioEhAgro = <?php echo $ehUsuarioAgroindustria ? 'true' : 'false'; ?>;

    function filtrarSetores() {
        if (!setorSel) return;
        var agroId = unidSel.value;
        var atual = setorSel.value;
        var precisaSelecionar = false;
        for (var i = 0; i < setorSel.options.length; i++) {
            var opt = setorSel.options[i];
            var mostrar = (!agroId || opt.dataset.agro === agroId);
            opt.style.display = mostrar ? '' : 'none';
            if (mostrar && opt.value === atual) precisaSelecionar = true;
        }
        if (!precisaSelecionar) setorSel.value = '';
    }

    function filtrarUnidades() {
        var tipo = tipoSel.value;
        var atual = unidSel.value;
        var precisaSelecionar = false;
        for (var i = 0; i < unidSel.options.length; i++) {
            var opt = unidSel.options[i];
            var mostrar = (!tipo || opt.dataset.tipo === tipo);
            opt.style.display = mostrar ? '' : 'none';
            if (mostrar && opt.value === atual) precisaSelecionar = true;
        }
        if (!precisaSelecionar) unidSel.value = '';
        if (lavouraSel) lavouraSel.disabled = (tipo === 'animal');

        ehAgro = usuarioEhAgro || tipo === 'agroindustria';
        qtdInput.disabled = ehAgro;
        dataInput.disabled = ehAgro;
        if (ehAgro) {
            qtdInput.value = '';
            dataInput.value = '';
        }
        if (qtdHelper) qtdHelper.style.display = ehAgro ? '' : 'none';

        filtrarSetores();
    }

    if (tipoSel) tipoSel.addEventListener('change', filtrarUnidades);
    if (unidSel) unidSel.addEventListener('change', filtrarSetores);
    if (tipoSel && unidSel) filtrarUnidades();
})();
</script>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
