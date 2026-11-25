<?php
$page_title = "Editar Lote de Produção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/LoteProducao.php';
require_once __DIR__ . '/../../../classes/Produto.php';

$message = '';
$message_type = '';

// Verificar se o usuário está logado
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || (!is_numeric($_GET['id']) && strlen($_GET['id']) !== 36)) {
    echo "<div class='alert alert-danger'>ID ou UUID de lote inválido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lote_id_param = $_GET['id'];
$lote_obj = new LoteProducao();

if (strlen($lote_id_param) === 36) {
    $lote = $lote_obj->getByUuid($lote_id_param);
} else {
    $lote = $lote_obj->getById((int)$lote_id_param);
}

if (!$lote) {
    echo "<div class='alert alert-danger'>Lote de produção não encontrado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lote_id = (int)$lote['id'];
$lote_uuid = $lote['uuid'] ?? '';

// IDOR Validation
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT p.associado_id 
                          FROM produtos pr 
                          JOIN lavouras l ON pr.lavoura_id = l.id 
                          JOIN propriedades p ON l.propriedade_id = p.id 
                          WHERE pr.id = ?");
    $stmt->execute([$lote['produto_id']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res || $res['associado_id'] != $user->getAssociadoId()) {
        echo "<div class='alert alert-danger'>Acesso Negado: Este lote pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

$produto_obj = new Produto();

if ($user->hasPermission(NIVEL_GESTOR)) {
    $produtos = $produto_obj->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $produtos = $produto_obj->getProdutosDoAssociado($associado_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados_lote = [];
    foreach ($_POST as $key => $value) {
        $dados_lote[$key] = htmlspecialchars(strip_tags(trim($value)));
    }
    if ($lote_obj->update($lote_id, $dados_lote)) {
        $message = "Lote de produção atualizado com sucesso!";
        $message_type = "success";
        echo "<script>setTimeout(function(){ window.location.href='" . SITE_URL . "/lotes'; }, 1500);</script>";
    } else {
        $message = "Erro ao atualizar lote de produção.";
        $message_type = "danger";
    }
    // Atualizar os dados do lote para refletir possíveis mudanças
    $lote = $lote_obj->getById($lote_id);
}
?>

<div class="d-flex justify-content-between align-items-center">
    <h1 class="mt-4">Editar Lote de Produção</h1>
    <a href="<?php echo SITE_URL; ?>/lotes" class="btn btn-success"><i class="bi bi-list"></i> Listar Lotes</a>
</div>
<p>Altere os dados do lote de produção conforme necessário.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/lote/<?php echo $lote_uuid ?: $lote_id; ?>/editar" method="POST" class="row g-3">
    <div class="col-md-6">
        <label for="nome_lote" class="form-label">Nome do Lote</label>
        <input type="text" class="form-control" id="nome_lote" name="nome_lote" value="<?php echo htmlspecialchars($lote['nome_lote']); ?>" required>
    </div>
    <div class="col-md-6">
        <label for="produto_id" class="form-label">Produto</label>
        <select class="form-select" id="produto_id" name="produto_id" required>
            <option value="">Selecione um Produto</option>
            <?php foreach ($produtos as $prod): ?>
                <option value="<?php echo $prod['id']; ?>" <?php if ($lote['produto_id'] == $prod['id']) echo 'selected'; ?>><?php echo htmlspecialchars($prod['nome'] . (isset($prod['nome_unidade']) && $prod['nome_unidade'] ? ' (' . $prod['nome_unidade'] . ')' : '')); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label for="data_producao" class="form-label">Data de Produção</label>
        <input type="date" class="form-control" id="data_producao" name="data_producao" value="<?php echo htmlspecialchars($lote['data_producao']); ?>" required>
    </div>
    <div class="col-md-4">
        <label for="quantidade" class="form-label">Quantidade</label>
        <input type="text" class="form-control" id="quantidade" name="quantidade" value="<?php echo htmlspecialchars($lote['quantidade']); ?>" required>
    </div>
    <div class="col-md-4">
        <label for="unidade_medida" class="form-label">Unidade de Medida</label>
        <input type="text" class="form-control" id="unidade_medida" name="unidade_medida" value="<?php echo htmlspecialchars($lote['unidade_medida']); ?>">
    </div>
    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($lote['observacoes']); ?></textarea>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="<?php echo SITE_URL; ?>/lotes" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?> 