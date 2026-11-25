<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associacao.php';

// Dado GLOBAL gerido pela equipe: edição restrita a Admin/Gestor.
// Antes, qualquer usuário logado (nível 3) abria e alterava associações.
if (!$user->isGestorOuAdmin()) {
    echo "<div class='alert alert-danger mt-4'>Acesso negado: você não tem permissão para editar associações.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = intval($_GET['id']);
$mensagem = '';

try {
    $associacao = new Associacao();
    $dados_associacao = $associacao->getById($id);
    
    if (!$dados_associacao) {
        echo "<div class='alert alert-danger'>Associação não encontrada.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar associação: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    $dados_atualizados = [];
    foreach ($_POST as $key => $value) {
        $dados_atualizados[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    if ($associacao->update($id, $dados_atualizados)) {
        $mensagem = '<div class="alert alert-success">Associação atualizada com sucesso!</div>';
        // Recarregar os dados
        $dados_associacao = $associacao->getById($id);
    } else {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar associação.</div>';
    }
}
?>

<h1 class="mt-4">Editar Associação</h1>
<?php if ($mensagem) echo $mensagem; ?>

<form action="<?php echo SITE_URL; ?>/associacao/editar" method="POST" class="row g-3">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    
    <div class="col-md-8">
        <label for="nome" class="form-label">Nome da Associação *</label>
        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($dados_associacao['nome']); ?>" required>
    </div>
    <div class="col-md-4">
        <label for="sigla" class="form-label">Sigla</label>
        <input type="text" class="form-control" id="sigla" name="sigla" value="<?php echo htmlspecialchars($dados_associacao['sigla']); ?>" maxlength="30">
    </div>

    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($dados_associacao['descricao']); ?></textarea>
    </div>

    <div class="col-md-12">
        <label for="objetivo" class="form-label">Objetivo</label>
        <textarea class="form-control" id="objetivo" name="objetivo" rows="3"><?php echo htmlspecialchars($dados_associacao['objetivo']); ?></textarea>
    </div>

    <div class="col-md-6">
        <label for="data_fundacao" class="form-label">Data de Fundação</label>
        <input type="date" class="form-control" id="data_fundacao" name="data_fundacao" value="<?php echo $dados_associacao['data_fundacao']; ?>">
    </div>
    <div class="col-md-6">
        <label for="responsavel" class="form-label">Responsável</label>
        <input type="text" class="form-control" id="responsavel" name="responsavel" value="<?php echo htmlspecialchars($dados_associacao['responsavel']); ?>">
    </div>

    <div class="col-md-6">
        <label for="cargo_responsavel" class="form-label">Cargo do Responsável</label>
        <input type="text" class="form-control" id="cargo_responsavel" name="cargo_responsavel" value="<?php echo htmlspecialchars($dados_associacao['cargo_responsavel']); ?>">
    </div>
    <div class="col-md-6">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone" value="<?php echo htmlspecialchars($dados_associacao['telefone']); ?>" placeholder="(00) 00000-0000">
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($dados_associacao['email']); ?>">
    </div>
    <div class="col-md-6">
        <label for="site" class="form-label">Site</label>
        <input type="url" class="form-control" id="site" name="site" value="<?php echo htmlspecialchars($dados_associacao['site']); ?>" placeholder="https://www.exemplo.com">
    </div>

    <div class="col-12">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($dados_associacao['endereco']); ?>">
    </div>

    <div class="col-md-4">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Selecione um Estado</option>
            <?php
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT id, nome, uf FROM estados ORDER BY nome ASC");
                    $stmt->execute();
                    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($estados as $estado) {
                        $selected = ($dados_associacao['estado'] == $estado['id']) ? 'selected' : '';
                        echo "<option value='{$estado['id']}' {$selected}>{$estado['nome']} ({$estado['uf']})</option>";
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
        <input type="text" class="form-control mask-cep" id="cep" name="cep" value="<?php echo htmlspecialchars($dados_associacao['cep']); ?>" placeholder="00000-000">
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="ativo" <?php echo ($dados_associacao['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
            <option value="inativo" <?php echo ($dados_associacao['status'] == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
        </select>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Atualizar Associação</button>
        <a href="<?php echo SITE_URL; ?>/associacoes" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
    const estadoSelect = document.getElementById('estado');
    const cidadeSelect = document.getElementById('cidade');
    const cidadeSelecionada = <?php 
        $cidade_id = isset($dados_associacao['cidade']) && is_numeric($dados_associacao['cidade']) ? intval($dados_associacao['cidade']) : null;
        echo $cidade_id !== null ? $cidade_id : 'null'; 
    ?>;

    function carregarCidades(estadoId, cidadePreSelecionada = '') {
        const cidadeSelect = document.getElementById('cidade');
        cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
        cidadeSelect.disabled = true;

        if (estadoId) {
            fetch(`<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=${estadoId}`)
                .then(response => response.json())
                .then(data => {
                    cidadeSelect.innerHTML = '<option value="">Selecione uma Cidade</option>';
                    data.forEach(cidade => {
                        const option = document.createElement('option');
                        option.value = cidade.id;
                        option.textContent = cidade.nome;
                        if (cidadePreSelecionada && String(cidade.id) === String(cidadePreSelecionada)) {
                            option.selected = true;
                        }
                        cidadeSelect.appendChild(option);
                    });
                    cidadeSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Erro ao buscar cidades:', error);
                    cidadeSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                });
        } else {
            cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
        }
    }

    estadoSelect.addEventListener('change', function () {
        carregarCidades(this.value);
    });

    if (estadoSelect.value) {
        carregarCidades(estadoSelect.value, cidadeSelecionada);
    }
</script> 