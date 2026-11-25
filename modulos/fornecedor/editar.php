<?php
$page_title = "Editar Fornecedor";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Fornecedor.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Produto.php';

$message = '';
$message_type = '';
$tiposLabels = ['propriedade' => 'Propriedade', 'agroindustria' => 'Agroindústria', 'animal' => 'Criação Animal'];

if (!$user->isLoggedIn()) {
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
$fornecedorClass = new Fornecedor();

// Buscar dados atuais
$fornecedor = $fornecedorClass->getById($id);
if (!$fornecedor) {
    echo "<div class='alert alert-danger'>Fornecedor não encontrado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// IDOR Check: gestor/admin OK; usuário comum precisa ter o fornecedor no escopo
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $associado_id = $user->getAssociadoId() ?? -1;
    $visiveis = $fornecedorClass->getVisiveisParaAssociado($associado_id);
    $podeEditar = false;
    foreach ($visiveis as $v) {
        if ((int)$v['id'] === $id) { $podeEditar = true; break; }
    }
    if (!$podeEditar) {
        echo "<div class='alert alert-danger'>Acesso Negado: Este fornecedor não está vinculado às suas unidades.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
}

// Unidades acessíveis ao usuário para o bloco "Atende às unidades"
$produtoClass = new Produto();
if ($user->hasPermission(NIVEL_GESTOR)) {
    // Gestor/Admin: lista todas as unidades para reatribuir o fornecedor
    $unidadesDisponiveis = $fornecedorClass->getTodasUnidades();
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

$unidadesVinculadas = $fornecedorClass->getUnidadesPorFornecedor($id);

// Para usuário comum: separa as unidades que ele acessa (pode alterar) das de
// outros associados (apenas leitura — preservadas no salvamento).
$vinculadasGerenciaveis = [];
$vinculadasAlheias = [];
if (!$user->hasPermission(NIVEL_GESTOR)) {
    $chavesPermitidas = [];
    foreach ($unidadesDisponiveis as $ud) {
        $chavesPermitidas[($ud['tipo'] ?? '') . ':' . (int)($ud['id'] ?? 0)] = true;
    }
    foreach ($unidadesVinculadas as $uv) {
        $chave = ($uv['unidade_tipo'] ?? '') . ':' . (int)($uv['unidade_id'] ?? 0);
        if (isset($chavesPermitidas[$chave])) {
            $vinculadasGerenciaveis[] = $uv;
        } else {
            $vinculadasAlheias[] = $uv;
        }
    }
} else {
    $vinculadasGerenciaveis = $unidadesVinculadas;
}

$associado_helper = new Associado();
$estados = $associado_helper->getEstados();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
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
                // Gravar vínculos de unidades (multi-tipo).
                // VALIDAÇÃO: só aceita unidades que o usuário realmente acessa.
                $permitidas = [];
                foreach ($unidadesDisponiveis as $ud) {
                    $permitidas[($ud['tipo'] ?? '') . ':' . (int)($ud['id'] ?? 0)] = true;
                }
                $vincular = [];
                $vt = $_POST['unidade_tipo'] ?? [];
                $vi = $_POST['unidade_id'] ?? [];
                foreach ((array)$vt as $i => $tipo) {
                    $tipo = strtolower(trim((string)$tipo));
                    $uid = (int)($vi[$i] ?? 0);
                    if ($tipo === '' || $uid <= 0) continue;
                    if (isset($permitidas[$tipo . ':' . $uid])) {
                        $vincular[] = ['tipo' => $tipo, 'id' => $uid];
                    }
                }
if (!$user->hasPermission(NIVEL_GESTOR)) {
                    // Preserva os vínculos de unidades de OUTROS associados,
                    // que não aparecem nos selects do usuário atual.
                    foreach ($vinculadasAlheias as $uvAlheia) {
                        $vincular[] = ['tipo' => $uvAlheia['unidade_tipo'], 'id' => $uvAlheia['unidade_id']];
                    }
                }
                $fornecedorClass->substituirUnidades($id, $vincular, $user->getAssociadoId() ?? null);

                $message = "Fornecedor atualizado com sucesso!";
            $message_type = "success";
            // Atualizar dados exibidos
            $fornecedor = array_merge($fornecedor, $_POST);
            $unidadesVinculadas = $fornecedorClass->getUnidadesPorFornecedor($id);
} else {
            $message = "Erro ao atualizar fornecedor.";
            $message_type = "danger";
        }
    } catch (PDOException $e) {
        error_log("Erro ao atualizar fornecedor: " . $e->getMessage());
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
        <h5 class="fw-bold">Unidades que este fornecedor atende</h5>
        <p class="text-muted">Selecione em qual(is) unidade(s) produtiva(s) este fornecedor fornece produtos/serviços. Somente os associados das unidades selecionadas verão este fornecedor nas telas.</p>
        <?php if (!empty($vinculadasAlheias)): ?>
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            Este fornecedor também atende <strong><?php echo count($vinculadasAlheias); ?> unidade(s)</strong> de outros associados, que não podem ser alteradas aqui.
            <?php foreach ($vinculadasAlheias as $una): echo ' <span class="badge bg-secondary">' . htmlspecialchars($una['nome_unidade']) . '</span>'; endforeach; ?>
        </div>
        <?php endif; ?>
        <div id="unidade-lista">
            <?php if (!empty($vinculadasGerenciaveis)): ?>
                <?php foreach ($vinculadasGerenciaveis as $uv): ?>
                <div class="input-group mb-2 unidade-linha">
                    <select name="unidade_tipo[]" class="form-select unidade-tipo" aria-label="Tipo de unidade">
                        <?php foreach ($todosTipos as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo ($t === ($uv['unidade_tipo'] ?? '')) ? 'selected' : ''; ?>><?php echo $tiposLabels[$t] ?? $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="unidade_id[]" class="form-select unidade-select" aria-label="Unidade">
                        <option value="">Selecione a unidade...</option>
                        <?php foreach ($todosTipos as $t): ?>
                            <optgroup label="<?php echo $tiposLabels[$t] ?? $t; ?>" data-tipo="<?php echo $t; ?>">
                                <?php foreach ($unidadesDisponiveis as $ud): ?>
                                    <?php if (($ud['tipo'] ?? '') === $t): ?>
                                        <option value="<?php echo $ud['id']; ?>" data-tipo="<?php echo $ud['tipo']; ?>"
                                            <?php echo (($uv['unidade_tipo'] ?? '') === $t && $uv['unidade_id'] == $ud['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ud['nome']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline-danger remover-unidade" aria-label="Remover linha">Remover</button>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-unidade">+ Adicionar unidade</button>
        <p class="text-muted small">Deixe vazio para que este fornecedor fique visível apenas a administradores/gestores.</p>
    </div>
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="<?php echo SITE_URL; ?>/fornecedor" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

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
        // Se a opção selecionada não pertence mais ao tipo visível, limpa
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
        let optsTipo = '';
        todosTipos.forEach(function(t) {
            optsTipo += '<option value="' + t + '"' + (t === (todosTipos[0] || '') ? ' selected' : '') + '>' + (todosTiposLabels[t] || t) + '</option>';
        });
        linha.innerHTML = '<select name="unidade_tipo[]" class="form-select unidade-tipo" aria-label="Tipo de unidade">' + optsTipo + '</select>' +
            '<select name="unidade_id[]" class="form-select unidade-select" aria-label="Unidade"><option value="">Selecione a unidade...</option></select>' +
            '<button type="button" class="btn btn-outline-danger remover-unidade" aria-label="Remover linha">Remover</button>';
        unidadeLista.appendChild(linha);
        const selTipo = linha.querySelector('.unidade-tipo');
        const selUnid = linha.querySelector('.unidade-select');
        todosTipos.forEach(function(t) {
            const g = document.createElement('optgroup');
            g.label = todosTiposLabels[t] || t;
            g.dataset.tipo = t;
            unidadesDisponiveis.forEach(function(u) {
                if (u.tipo === t) {
                    const o = document.createElement('option');
                    o.value = u.id;
                    o.dataset.tipo = u.tipo;
                    o.textContent = u.nome;
                    g.appendChild(o);
                }
            });
            selUnid.appendChild(g);
        });
        mostraGrupos(selTipo, selUnid);
    });

    // Aplica o filtro inicial (a partir do tipo pré-selecionado de cada linha)
    document.querySelectorAll('.unidade-linha').forEach(function(linha) {
        const selTipo = linha.querySelector('.unidade-tipo');
        const selUnid = linha.querySelector('.unidade-select');
        if (selTipo && selUnid) mostraGrupos(selTipo, selUnid);
    });
});
</script>

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