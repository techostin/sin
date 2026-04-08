<?php
$page_title = "Editar Insumos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Insumo.php';
require_once __DIR__ . '/../../../classes/Associado.php';
require_once __DIR__ . '/../../../classes/Database.php';

$message = '';
$message_type = '';

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID do insumo não informado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$insumos = new Insumo();
$associado = new Associado();

// Buscar listas para os selects
if (!$user->hasPermission(NIVEL_GESTOR)) {
    if (isset($user) && method_exists($user, 'getAssociadoId')) {
        $assoc_dados = $associado->getById($user->getAssociadoId());
        $associados = $assoc_dados ? [$assoc_dados] : [];
    } else {
        $associados = [];
    }
} else {
    $associados = $associado->getAll();
}

// Buscar fornecedores direto do banco
$fornecedores = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, nome FROM fornecedores ORDER BY nome ASC");
    $stmt->execute();
    $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erro ao carregar a lista de fornecedores. Por favor, tente novamente.";
    $message_type = "danger";
    error_log("Erro ao buscar fornecedores: " . $e->getMessage());
}

// Buscar dados do insumo
$dados_insumos = $insumos->getById($id);
if (!$dados_insumos) {
    echo '<div class="alert alert-danger">Insumo não encontrado.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// IDOR Check
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $associado_id = $user->getAssociadoId();
    if ($dados_insumos['associado_id'] != $associado_id) {
        echo "<div class='alert alert-danger'>Acesso Negado: Este insumo pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dados = [];
    foreach ($_POST as $key => $value) {
        $dados[$key] = htmlspecialchars(strip_tags(trim($value)));
    }
    $dados['id'] = $id;

    // Converter valores vazios para NULL
    $dados['fornecedor_id'] = !empty($dados['fornecedor_id']) ? $dados['fornecedor_id'] : null;
    $dados['associado_id'] = !empty($dados['associado_id']) ? $dados['associado_id'] : null;
    $dados['estoq_anterior'] = !empty($dados['estoq_anterior']) ? (float)$dados['estoq_anterior'] : 0;
    $dados['quant_recebida'] = !empty($dados['quant_recebida']) ? (float)$dados['quant_recebida'] : 0;
    
    // Soma automática
    $dados['quantidade_estoque'] = $dados['estoq_anterior'] + $dados['quant_recebida'];

    if ($insumos->update($dados)) {
        $message = "Insumo atualizado com sucesso!";
        $message_type = "success";
        // Recarregar dados após atualização
        $dados_insumos = $insumos->getById($id);
    } else {
        $message = "Erro ao atualizar insumo. Por favor, tente novamente.";
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Insumo</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="btn btn-success">Listar Insumos</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">
    <div class="col-md-6">
        <label for="associado_id" class="form-label">Associado</label>
        <select class="form-select" id="associado_id" name="associado_id" required>
            <option value="">Selecione um associado...</option>
            <?php foreach ($associados as $assoc): ?>
                <option value="<?php echo $assoc['id']; ?>" 
                    <?php echo (isset($dados_insumos['associado_id']) && $dados_insumos['associado_id'] == $assoc['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($assoc['nome'] . ' ' . ($assoc['sobrenome'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" class="form-control" id="nome" name="nome" 
               value="<?php echo htmlspecialchars($dados_insumos['nome'] ?? ''); ?>" required>
    </div>

    <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo htmlspecialchars($dados_insumos['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="col-md-6">
        <label for="fornecedor_id" class="form-label">Fornecedor</label>
        <select class="form-select" id="fornecedor_id" name="fornecedor_id">
            <option value="">Selecione um fornecedor...</option>
            <?php foreach ($fornecedores as $forn): ?>
                <option value="<?php echo $forn['id']; ?>" 
                    <?php echo (isset($dados_insumos['fornecedor_id']) && $dados_insumos['fornecedor_id'] == $forn['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($forn['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="quantidade_estoque" class="form-label">Quantidade em Estoque</label>
        <input type="number" step="0.01" class="form-control bg-light" id="quantidade_estoque" 
               name="quantidade_estoque" value="<?php echo floatval($dados_insumos['quantidade_estoque'] ?? '0'); ?>" readonly>
    </div>

    <div class="col-md-6">
        <label for="estoq_anterior" class="form-label">Estoque Anterior</label>
        <input type="number" step="0.01" class="form-control" id="estoq_anterior" 
               name="estoq_anterior" value="<?php echo floatval($dados_insumos['estoq_anterior'] ?? '0'); ?>">
    </div>

    <div class="col-md-6">
        <label for="quant_recebida" class="form-label">Quantidade Recebida</label>
        <input type="number" step="0.01" class="form-control" id="quant_recebida" 
               name="quant_recebida" value="<?php echo floatval($dados_insumos['quant_recebida'] ?? '0'); ?>">
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Atualizar Insumo
        </button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Insumos
        </a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const estoqueAnterior = document.getElementById('estoq_anterior');
    const quantRecebida = document.getElementById('quant_recebida');
    const quantidadeEstoque = document.getElementById('quantidade_estoque');

    function atualizarEstoque() {
        const estqAnt = parseFloat(estoqueAnterior.value) || 0;
        const quantRec = parseFloat(quantRecebida.value) || 0;
        quantidadeEstoque.value = Number((estqAnt + quantRec).toFixed(2));
    }

    estoqueAnterior.addEventListener('input', atualizarEstoque);
    quantRecebida.addEventListener('input', atualizarEstoque);
});
</script>
