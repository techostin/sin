<?php
$page_title = "Cadastrar Associação";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associacao.php';

$message = '';
$message_type = '';

// Apenas Admin ou Gestor podem cadastrar novas associações
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $associacao = new Associacao();
    
    // Sanitização básica dos dados
    $dados_associacao = [];
    foreach ($_POST as $key => $value) {
        $dados_associacao[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    if ($associacao->create($dados_associacao)) {
        $message = "Associação cadastrada com sucesso!";
        $message_type = "success";
    } else {
        $message = "Erro ao cadastrar associação. O nome já pode estar em uso.";
        $message_type = "danger";
    }
}
?>

<h1 class="mt-4">Cadastrar Nova Associação</h1>
<p>Preencha o formulário abaixo para adicionar uma nova associação ao sistema.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/associacao/novo" method="POST" class="row g-3">
    
    <div class="col-md-8">
        <label for="nome" class="form-label">Nome da Associação *</label>
        <input type="text" class="form-control" id="nome" name="nome" required>
    </div>
    <div class="col-md-4">
        <label for="sigla" class="form-label">Sigla</label>
        <input type="text" class="form-control" id="sigla" name="sigla" maxlength="30">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
    </div>

    <div class="col-md-12">
        <label for="objetivo" class="form-label">Objetivo</label>
        <textarea class="form-control" id="objetivo" name="objetivo" rows="3"></textarea>
    </div>

    <div class="col-md-6">
        <label for="data_fundacao" class="form-label">Data de Fundação</label>
        <input type="date" class="form-control" id="data_fundacao" name="data_fundacao">
    </div>
    <div class="col-md-6">
        <label for="responsavel" class="form-label">Responsável</label>
        <input type="text" class="form-control" id="responsavel" name="responsavel">
    </div>

    <div class="col-md-6">
        <label for="cargo_responsavel" class="form-label">Cargo do Responsável</label>
        <input type="text" class="form-control" id="cargo_responsavel" name="cargo_responsavel">
    </div>
    <div class="col-md-6">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone" placeholder="(00) 00000-0000">
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email">
    </div>
    <div class="col-md-6">
        <label for="site" class="form-label">Site</label>
        <input type="url" class="form-control" id="site" name="site" placeholder="https://www.exemplo.com">
    </div>

    <div class="col-12">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco">
    </div>

    <div class="col-md-4">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado" required>
            <option value="">Selecione um Estado</option>
            <?php
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT id, nome, uf FROM estados ORDER BY nome ASC");
                    $stmt->execute();
                    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($estados as $estado) {
                        echo "<option value='{$estado['id']}'>{$estado['nome']} ({$estado['uf']})</option>";
                    }
                } catch (PDOException $e) {
                    // Em caso de erro, não exibir opções
                }
            ?>
        </select>
    </div>
    <div class="col-md-5">
        <label for="cidade" class="form-label">Cidade</label>
        <select class="form-select" id="cidade" name="cidade" required disabled>
            <option value="">Selecione um Estado Primeiro</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="cep" class="form-label">CEP</label>
        <input type="text" class="form-control mask-cep" id="cep" name="cep" placeholder="00000-000">
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Associação</button>
        <a href="<?php echo SITE_URL; ?>/associacoes" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

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
            fetch(`<?php echo SITE_URL; ?>/api/cidades?estado_id=${estadoId}`)
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