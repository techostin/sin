<?php
$page_title = "Editar Produtos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';

$message = '';
$message_type = '';

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID do produto não informado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$produtos = new Produto();

// Buscar dados do produto
$dados_produtos = $produtos->getById($id);
if (!$dados_produtos) {
    echo '<div class="alert alert-danger">Produto não encontrado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// IDOR Check
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $associado_id = $user->getAssociadoId() ?? -1;
    $acessivel = $produtos->getProdutosDoAssociado($associado_id);
    $podeAcessar = false;
    foreach ($acessivel as $p) {
        if ((int)$p['id'] === $id) { $podeAcessar = true; break; }
    }
    if (!$podeAcessar) {
        echo "<div class='alert alert-danger'>Acesso Negado: Este produto pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

// Buscar lavouras, agroindústrias e ambientes de criação para os selects
$lavoura_obj = new Lavoura();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $lavouras = $lavoura_obj->getAll();
} else {
    $lavouras = $lavoura_obj->getByAssociadoId($user->getAssociadoId() ?? -1);
}
$unidades = $produtos->getUnidadesDoAssociado($user->getAssociadoId() ?? -1);

// Perfil exclusivamente de agroindústria ou produto vinculado a agroindústria:
// quantidade e data de colheita/produção não são digitadas no produto — vêm da OP.
$ehUsuarioAgroindustria = ($user->perfilDashboard() === 'agroindustria');
$ehProdutoAgroindustria = (($dados_produtos['unidade_tipo'] ?? '') === 'agroindustria');
$qtdDesabilitado = ($ehUsuarioAgroindustria || $ehProdutoAgroindustria) ? 'disabled' : '';
$helperVisivel = ($ehUsuarioAgroindustria || $ehProdutoAgroindustria);

// Setores de origem (agroindústria): carrega quando usuário agroindústria
$setores = [];
if ($ehUsuarioAgroindustria) {
    require_once __DIR__ . '/../../../classes/AgroSetor.php';
    $setorClass = new AgroSetor();
    $setores = $setorClass->getSetoresDoAssociado($user->getAssociadoId() ?? -1);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados = $_POST;
    $dados['id'] = $id;

    if ($produtos->update($dados)) {
        $message = "Produto atualizado com sucesso!";
        $message_type = "success";
        // Recarregar dados após atualização
        $dados_produtos = $produtos->getById($id);
    } else {
        $message = "Erro ao atualizar produto.";
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Produto</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-success">
        <i class="bi bi-list"></i> Listar Produtos
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">

    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Produto</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dados_produtos['nome'] ?? ''); ?>" required>
    </div>

    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo/Variedade</label>
        <input type="text" class="form-control" id="tipo" name="tipo" value="<?php echo htmlspecialchars($dados_produtos['tipo'] ?? ''); ?>">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo htmlspecialchars($dados_produtos['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <select class="form-select" id="unidade_medida" name="unidade_medida">
            <option value="kg" <?php echo ($dados_produtos['unidade_medida'] == 'kg') ? 'selected' : ''; ?>>Quilograma (kg)</option>
            <option value="unidade" <?php echo ($dados_produtos['unidade_medida'] == 'unidade') ? 'selected' : ''; ?>>Unidade</option>
            <option value="caixa" <?php echo ($dados_produtos['unidade_medida'] == 'caixa') ? 'selected' : ''; ?>>Caixa</option>
            <option value="litro" <?php echo ($dados_produtos['unidade_medida'] == 'litro') ? 'selected' : ''; ?>>Litro</option>
            <option value="saca" <?php echo ($dados_produtos['unidade_medida'] == 'saca') ? 'selected' : ''; ?>>Saca</option>
        </select>
    </div>

    <div class="col-md-4">
        <label for="preco_unitario" class="form-label">Preço Unitário (R$)</label>
        <input type="text" class="form-control" id="preco_unitario" name="preco_unitario" value="<?php echo htmlspecialchars($dados_produtos['preco_unitario'] ?? ''); ?>">
    </div>

    <div class="col-md-4">
        <label for="quantidade_produzida" class="form-label">Quantidade Produzida</label>
        <input type="text" class="form-control" id="quantidade_produzida" name="quantidade_produzida" value="<?php echo htmlspecialchars($qtdDesabilitado ? '' : ($dados_produtos['quantidade_produzida'] ?? '')); ?>" <?php echo $qtdDesabilitado; ?>>
        <div class="form-text" id="qtd-helper" style="<?php echo $helperVisivel ? '' : 'display:none;'; ?>">Para produtos da agroindústria, a quantidade é registrada na Ordem de Produção.</div>
    </div>

    <div class="col-md-6">
        <label for="data_colheita" class="form-label">Data de Colheita/Produção</label>
        <input type="date" class="form-control" id="data_colheita" name="data_colheita" value="<?php echo htmlspecialchars($qtdDesabilitado ? '' : ($dados_produtos['data_colheita'] ?? '')); ?>" <?php echo $qtdDesabilitado; ?>>
    </div>

    <?php if ($ehUsuarioAgroindustria): ?>
    <div class="col-md-6">
        <label for="setor_id" class="form-label">Setor de Origem (opcional)</label>
        <select class="form-select" id="setor_id" name="setor_id">
            <option value="">Selecione o setor...</option>
            <?php foreach ($setores as $set): ?>
                <option value="<?php echo $set['id']; ?>" data-agro="<?php echo $set['agroindustria_id']; ?>"
                    <?php echo ($dados_produtos['setor_id'] == $set['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($set['nome'] . ' (' . $set['agroindustria_nome'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Selecione o setor da agroindústria de origem.</div>
    </div>
    <?php else: ?>
    <div class="col-md-6">
        <label for="lavoura_id" class="form-label">Lavoura de Origem (opcional)</label>
        <select class="form-select" id="lavoura_id" name="lavoura_id">
            <option value="">Selecione a lavoura...</option>
            <?php foreach ($lavouras as $lav): ?>
                <option value="<?php echo $lav['id']; ?>" <?php echo ($dados_produtos['lavoura_id'] == $lav['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lav['nome'] . " (" . $lav['propriedade_nome'] . ")"); ?>
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
            <option value="propriedade" <?php echo ($dados_produtos['unidade_tipo'] == 'propriedade') ? 'selected' : ''; ?>>Propriedade</option>
            <option value="agroindustria" <?php echo ($dados_produtos['unidade_tipo'] == 'agroindustria') ? 'selected' : ''; ?>>Agroindústria</option>
            <option value="animal" <?php echo ($dados_produtos['unidade_tipo'] == 'animal') ? 'selected' : ''; ?>>Criação Animal</option>
        </select>
    </div>
    <div class="col-md-6">
        <label for="unidade_id" class="form-label">Unidade Específica</label>
        <select class="form-select" id="unidade_id" name="unidade_id">
            <option value="">Selecione a unidade...</option>
            <?php foreach ($unidades as $un): ?>
                <option value="<?php echo $un['id']; ?>" data-tipo="<?php echo $un['tipo']; ?>"
                    <?php echo ($dados_produtos['unidade_tipo'] == $un['tipo'] && $dados_produtos['unidade_id'] == $un['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($un['nome'] . ' (' . ($un['tipo'] == 'propriedade' ? 'Propriedade' : ($un['tipo'] == 'agroindustria' ? 'Agroindústria' : 'Criação Animal')) . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($dados_produtos['observacoes'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
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
        // Se unidade selecionada é propriedade/agroindústria, lavoura fica opcional
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
