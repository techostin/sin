<?php
$page_title = "Cadastrar Fornecedor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Fornecedor.php';
require_once __DIR__ . '/../../classes/Associado.php';

$message = '';
$message_type = '';

// Permitir Admin, Gestor e Usuários comuns cadastrarem fornecedores
if (!$user->hasPermission(NIVEL_USUARIO)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fornecedor = new Fornecedor();
    
    // O método create agora lida com a sanitização e retorna o erro específico
    $resultado = $fornecedor->create($_POST);

    if ($resultado === true) {
        $message = "Fornecedor cadastrado com sucesso!";
        $message_type = "success";
    } else {
        $message = $resultado; // A mensagem de erro vem diretamente do método create()
        $message_type = "danger";
    }
}

$associado_helper = new Associado();
$estados = $associado_helper->getEstados();
?>

<h1 class="mt-4">Cadastrar Novo Fornecedor</h1>
<p>Preencha o formulário abaixo para adicionar um novo fornecedor.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/fornecedor/novo" method="POST" class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome Fantasia</label>
        <input type="text" class="form-control" id="nome" name="nome" required>
    </div>
    <div class="col-md-6">
        <label for="razao_social" class="form-label">Razão Social</label>
        <input type="text" class="form-control" id="razao_social" name="razao_social">
    </div>
    <div class="col-md-6">
        <label for="cnpj" class="form-label">CNPJ</label>
        <input type="text" class="form-control mask-cnpj" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00">
    </div>
    <div class="col-md-6">
        <label for="representante" class="form-label">Representante</label>
        <input type="text" class="form-control" id="representante" name="representante">
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email">
    </div>
    <div class="col-md-6">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone" placeholder="(00) 00000-0000">
    </div>
    <div class="col-12">
        <label for="produto_fornecido" class="form-label">Principal Produto/Serviço Fornecido</label>
        <input type="text" class="form-control" id="produto_fornecido" name="produto_fornecido">
    </div>
    <div class="col-md-9">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco">
    </div>
    <div class="col-md-3">
        <label for="bairro" class="form-label">Bairro</label>
        <input type="text" class="form-control" id="bairro" name="bairro">
    </div>
    <div class="col-md-4">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado" required>
            <option value="">Selecione um Estado</option>
            <?php foreach ($estados as $estado): ?>
                <option value="<?php echo htmlspecialchars($estado['id']); ?>"><?php echo htmlspecialchars($estado['nome']); ?></option>
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
        <button type="submit" class="btn btn-primary">Cadastrar Fornecedor</button>
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
    
    function carregarCidades(estadoId) {
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
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?> 