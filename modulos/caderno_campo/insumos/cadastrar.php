<?php
$page_title = "Cadastrar Insumo";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Insumo.php';
require_once __DIR__ . '/../../../classes/Associado.php';
require_once __DIR__ . '/../../../classes/Database.php';

$message = '';
$message_type = '';

// Verificar permissões
if (!$user->isLoggedIn()) {
    $message = "Você não tem permissão para acessar esta página.";
    $message_type = "danger";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar listas para os selects
$associado = new Associado();
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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $insumo = new Insumo();
    
    $dados = [];
    foreach ($_POST as $key => $value) {
        $dados[$key] = htmlspecialchars(strip_tags(trim($value)));
    }
    
    // Converter valores vazios para NULL ou 0
    $dados['fornecedor_id'] = !empty($dados['fornecedor_id']) ? $dados['fornecedor_id'] : null;
    $dados['associado_id'] = !empty($dados['associado_id']) ? $dados['associado_id'] : null;
    $dados['estoq_anterior'] = !empty($dados['estoq_anterior']) ? (float)$dados['estoq_anterior'] : 0;
    $dados['quant_recebida'] = !empty($dados['quant_recebida']) ? (float)$dados['quant_recebida'] : 0;
    
    // Soma automática
    $dados['quantidade_estoque'] = $dados['estoq_anterior'] + $dados['quant_recebida'];
    
    $dados['observacoes'] = $dados['observacoes'] ?? '';
    $dados['data_validade'] = !empty($dados['data_validade']) ? $dados['data_validade'] : null;

    if ($insumo->create($dados)) {
        $message = "Insumo cadastrado com sucesso!";
        $message_type = "success";
        // Limpar os dados do formulário após o cadastro
        $_POST = [];
    } else {
        $message = "Erro ao cadastrar insumo. Por favor, verifique os dados e tente novamente.";
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Cadastrar Novo Insumo</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="btn btn-success">
        <i class="bi bi-list-ul"></i> Listar Insumos
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
</div>
<?php endif; ?>

<form action="" method="POST" class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Insumo <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="nome" name="nome" 
               value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
    </div>
    
    <div class="col-md-6">
        <label for="associado_id" class="form-label">Associado <span class="text-danger">*</span></label>
        <select class="form-select" id="associado_id" name="associado_id" required>
            <option value="">Selecione um associado...</option>
            <?php foreach ($associados as $assoc): ?>
                <option value="<?php echo $assoc['id']; ?>"
                    <?php echo (isset($_POST['associado_id']) && $_POST['associado_id'] == $assoc['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($assoc['nome'] . ' ' . ($assoc['sobrenome'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="2"><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
    </div>
    
    <div class="col-md-6">
        <label for="fornecedor_id" class="form-label">Fornecedor</label>
        <select class="form-select" id="fornecedor_id" name="fornecedor_id">
            <option value="">Selecione um fornecedor...</option>
            <?php foreach ($fornecedores as $forn): ?>
                <option value="<?php echo $forn['id']; ?>"
                    <?php echo (isset($_POST['fornecedor_id']) && $_POST['fornecedor_id'] == $forn['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($forn['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-6">
        <label for="data_validade" class="form-label">Data de Validade</label>
        <input type="date" class="form-control" id="data_validade" name="data_validade"
               value="<?php echo htmlspecialchars($_POST['data_validade'] ?? ''); ?>">
    </div>
    
    <div class="col-md-4">
        <label for="estoq_anterior" class="form-label">Estoque Anterior</label>
        <input type="number" step="0.01" class="form-control" id="estoq_anterior" name="estoq_anterior"
               value="<?php echo isset($_POST['estoq_anterior']) ? floatval($_POST['estoq_anterior']) : '0'; ?>">
    </div>
    
    <div class="col-md-4">
        <label for="quant_recebida" class="form-label">Quantidade Recebida</label>
        <input type="number" step="0.01" class="form-control" id="quant_recebida" name="quant_recebida"
               value="<?php echo isset($_POST['quant_recebida']) ? floatval($_POST['quant_recebida']) : '0'; ?>">
    </div>
    
    <div class="col-md-4">
        <label for="quantidade_estoque" class="form-label">Quantidade em Estoque</label>
        <input type="number" step="0.01" class="form-control bg-light" id="quantidade_estoque" name="quantidade_estoque"
               value="<?php echo isset($_POST['quantidade_estoque']) ? floatval($_POST['quantidade_estoque']) : '0'; ?>" readonly>
    </div>
    
    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Cadastrar Insumo
        </button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
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
        // Atualiza e não deixa "NaN" se apagar o campo. Number() remove zeros à direita indesejados.
        quantidadeEstoque.value = Number((estqAnt + quantRec).toFixed(2));
    }

    estoqueAnterior.addEventListener('input', atualizarEstoque);
    quantRecebida.addEventListener('input', atualizarEstoque);
});
</script>
