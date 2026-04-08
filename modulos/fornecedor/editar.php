<?php
$page_title = "Editar Fornecedor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Associado.php';

$message = '';
$message_type = '';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de fornecedor inválido.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = intval($_GET['id']);

// Buscar dados atuais
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM fornecedores WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fornecedor = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fornecedor) {
        echo "<div class='alert alert-danger'>Fornecedor não encontrado.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar fornecedor.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$associado_helper = new Associado();
$estados = $associado_helper->getEstados();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE fornecedores SET nome = :nome, representante = :representante, razao_social = :razao_social, cnpj = :cnpj, telefone = :telefone, email = :email, endereco = :endereco, bairro = :bairro, cidade = :cidade, estado = :estado, produto_fornecido = :produto_fornecido WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':nome', $_POST['nome']);
        $stmt->bindParam(':representante', $_POST['representante']);
        $stmt->bindParam(':razao_social', $_POST['razao_social']);
        $stmt->bindParam(':cnpj', $_POST['cnpj']);
        $stmt->bindParam(':telefone', $_POST['telefone']);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':endereco', $_POST['endereco']);
        $stmt->bindParam(':bairro', $_POST['bairro']);
        $stmt->bindParam(':cidade', $_POST['cidade']);
        $stmt->bindParam(':estado', $_POST['estado']);
        $stmt->bindParam(':produto_fornecido', $_POST['produto_fornecido']);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $message = "Fornecedor atualizado com sucesso!";
            $message_type = "success";
            // Atualizar dados exibidos
            $fornecedor = array_merge($fornecedor, $_POST);
        } else {
            $message = "Erro ao atualizar fornecedor.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        $message = "Erro ao atualizar fornecedor.";
        $message_type = "danger";
    }
}
?>

<h1 class="mt-4">Editar Fornecedor</h1>
<p>Altere os dados do fornecedor abaixo:</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/fornecedor/<?php echo $id; ?>/editar" method="POST" class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($fornecedor['nome']); ?>" required>
    </div>
    <div class="col-md-6">
        <label for="representante" class="form-label">Representante</label>
        <input type="text" class="form-control" id="representante" name="representante" value="<?php echo htmlspecialchars($fornecedor['representante']); ?>">
    </div>
    <div class="col-md-6">
        <label for="razao_social" class="form-label">Razão Social</label>
        <input type="text" class="form-control" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($fornecedor['razao_social']); ?>">
    </div>
    <div class="col-md-6">
        <label for="cnpj" class="form-label">CNPJ</label>
        <input type="text" class="form-control mask-cnpj" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($fornecedor['cnpj']); ?>" placeholder="00.000.000/0000-00">
    </div>
    <div class="col-md-4">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone" value="<?php echo htmlspecialchars($fornecedor['telefone']); ?>" placeholder="(00) 00000-0000">
    </div>
    <div class="col-md-4">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($fornecedor['email']); ?>">
    </div>
    <div class="col-md-4">
        <label for="produto_fornecido" class="form-label">Produto Fornecido</label>
        <input type="text" class="form-control" id="produto_fornecido" name="produto_fornecido" value="<?php echo htmlspecialchars($fornecedor['produto_fornecido']); ?>">
    </div>
    <div class="col-md-6">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($fornecedor['endereco']); ?>">
    </div>
    <div class="col-md-3">
        <label for="bairro" class="form-label">Bairro</label>
        <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo htmlspecialchars($fornecedor['bairro']); ?>">
    </div>
    <div class="col-md-4">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado" required>
            <option value="">Selecione um Estado</option>
            <?php foreach ($estados as $estado): ?>
                <option value="<?php echo $estado['id']; ?>" <?php echo (isset($fornecedor['estado']) && is_numeric($fornecedor['estado']) && $fornecedor['estado'] == $estado['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($estado['nome'] . ' (' . $estado['uf'] . ')'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8">
        <label for="cidade" class="form-label">Cidade</label>
        <select class="form-select" id="cidade" name="cidade" required disabled>
            <option value="">Selecione um Estado Primeiro</option>
        </select>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="<?php echo SITE_URL; ?>/fornecedor" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const estadoSelect = document.getElementById('estado');
    const cidadeSelect = document.getElementById('cidade');
    
    if (!estadoSelect || !cidadeSelect) {
        return;
    }
    
    const cidadeAtual = <?php 
        // O campo 'cidade' do fornecedor é o ID
        $cidade_id = isset($fornecedor['cidade']) && is_numeric($fornecedor['cidade']) ? intval($fornecedor['cidade']) : null;
        echo $cidade_id !== null ? $cidade_id : 'null'; 
    ?>;
    
    function carregarCidades(estadoId, cidadeSelecionar = null) {
        cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
        cidadeSelect.disabled = true;
        
        if (estadoId) {
            fetch(`<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=${estadoId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta da API');
                    }
                    return response.json();
                })
                .then(data => {
                    cidadeSelect.innerHTML = '<option value="">Selecione uma Cidade</option>';
                    if (Array.isArray(data)) {
                        data.forEach(cidade => {
                            const option = document.createElement('option');
                            option.value = cidade.id;
                            option.textContent = cidade.nome;
                            
                            // Selecionar cidade se for a mesma do banco
                            if (cidadeSelecionar && (cidade.id == cidadeSelecionar || cidade.nome == cidadeSelecionar)) {
                                option.selected = true;
                            }
                            
                            cidadeSelect.appendChild(option);
                        });
                    }
                    cidadeSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Erro ao buscar cidades:', error);
                    cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
                    cidadeSelect.disabled = false;
                });
        } else {
            cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
            cidadeSelect.disabled = true;
        }
    }
    
    estadoSelect.addEventListener('change', function() {
        carregarCidades(this.value);
    });
    
    // Carregar cidades ao abrir a página se já houver valor
    if (estadoSelect.value && cidadeAtual) {
        carregarCidades(estadoSelect.value, cidadeAtual);
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?> 