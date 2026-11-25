<?php
$page_title = "Cadastrar Insumo";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Insumo.php';
require_once __DIR__ . '/../../../classes/Associado.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/InsumoValidador.php';
require_once __DIR__ . '/../../../classes/AuditLog.php';

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

// Buscar fornecedores direto do banco (escopo por perfil: usuário comum vê apenas os vinculados às suas unidades)
$fornecedores = [];
try {
    require_once __DIR__ . '/../../../classes/Fornecedor.php';
    $forn_obj = new Fornecedor();
    if ($user->hasPermission(NIVEL_GESTOR)) {
        $fornecedores = $forn_obj->getAll();
    } else {
        $fornecedores = $forn_obj->getVisiveisParaAssociado($user->getAssociadoId() ?? -1);
    }
} catch (PDOException $e) {
    $message = "Erro ao carregar a lista de fornecedores. Por favor, tente novamente.";
    $message_type = "danger";
    error_log("Erro ao buscar fornecedores: " . $e->getMessage());
}

// Classificação dos insumos aprovados por linha de produção (Portaria 52/2021)
$linhas_producao = [
    'Vegetal' => ['Fertilizante', 'Defensivo', 'Adjuvante', 'Sementes', 'Mudas', 'Outros'],
    'Animal'  => ['Alimentação Animal', 'Suplemento Mineral', 'Aditivo Tecnológico', 'Sanidade Animal', 'Higienização', 'Apicultura'],
    'Processamento' => ['Aditivo Alimentar', 'Coadjuvante de Tecnologia'],
];
$categoria_por_linha = [];
foreach ($linhas_producao as $linha => $cats) {
    foreach ($cats as $c) {
        $categoria_por_linha[$c] = $linha;
    }
}

// Insumos aprovados para exibição no modal de seleção
$insumos_aprovados = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, nome, categoria, tipo, restricoes FROM insumos_organicos_aprovados WHERE permitido = 1 ORDER BY categoria ASC, nome ASC");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ins) {
        $insumos_aprovados[] = [
            'id'         => (int)$ins['id'],
            'nome'       => $ins['nome'],
            'categoria'  => $ins['categoria'],
            'tipo'       => $ins['tipo'] ?? '',
            'linha'      => $categoria_por_linha[$ins['categoria']] ?? 'Vegetal',
            'restricoes' => $ins['restricoes'] ?? '',
        ];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar insumos aprovados: " . $e->getMessage());
}
$insumos_aprovados_json = json_encode($insumos_aprovados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

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

    // RF06: bloqueio impeditivo de insumos não permitidos + classificação automática
    $validador = new InsumoValidador();
    $resultado = $validador->avaliar($dados['nome'] ?? '');
    if (!$resultado['permitido']) {
        $message = "Insumo \"" . htmlspecialchars($dados['nome']) . "\" NÃO está na lista de permitidos (Portaria 52/2021). O cadastro foi bloqueado por conformidade.";
        $message_type = "danger";
    } else {
        $dados['aprovado_organico'] = 1;
        $dados['tipo_insumo'] = 'Orgánico';
        $dados['insumo_aprovado_id'] = $resultado['insumo']['id'] ?? null;
        $dados['restricoes'] = $resultado['insumo']['restricoes'] ?? null;

        if ($insumo->create($dados)) {
            try {
                $db_log = Database::getInstance()->getConnection();
                AuditLog::registrar('INSUMO_CADASTRADO', 'insumos', (int)$db_log->lastInsertId(), 'Insumo: ' . $dados['nome']);
            } catch (PDOException $e) {
                // Auditoria é best-effort
            }
            $message = "Insumo cadastrado com sucesso!";
            $message_type = "success";
            // Limpar os dados do formulário após o cadastro
            $_POST = [];
        } else {
            $message = "Erro ao cadastrar insumo. Por favor, verifique os dados e tente novamente.";
            $message_type = "danger";
        }
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

<form action="" method="POST" id="form-insumo" class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome do Insumo <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="text" class="form-control" id="nome" name="nome" 
                   value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required autocomplete="off">
            <button type="button" class="btn btn-outline-success" id="btn-abrir-seletor" title="Escolher da lista oficial de insumos aprovados (Portaria 52/2021)">
                <i class="bi bi-list-check"></i> Escolher da lista
            </button>
        </div>
        <div id="insumo-validation-feedback" class="mt-2"></div>
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

<!-- Modal: Seletor de Insumos Aprovados (Portaria 52/2021) -->
<div class="modal fade" id="modalSeletorInsumo" tabindex="-1" aria-labelledby="modalSeletorInsumoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSeletorInsumoLabel">
                    <i class="bi bi-list-check text-success me-1"></i> Selecione o Insumo Aprovado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Escolha um insumo da lista oficial (Portaria MAPA 52/2021 e IN Conjunta 18/2009) de acordo com a sua linha de produção.
                    O insumo selecionado será preenchido no campo <strong>Nome do Insumo</strong>.
                </p>

                <!-- Filtro por linha de produção -->
                <div class="btn-group w-100 mb-3" role="group" aria-label="Filtrar por linha de produção">
                    <input type="radio" class="btn-check" name="filtro-linha" id="linha-todas" value="todas" checked>
                    <label class="btn btn-outline-secondary" for="linha-todas">Todas</label>
                    <input type="radio" class="btn-check" name="filtro-linha" id="linha-vegetal" value="Vegetal">
                    <label class="btn btn-outline-success" for="linha-vegetal"><i class="bi bi-tree-fill me-1"></i>Produção Vegetal</label>
                    <input type="radio" class="btn-check" name="filtro-linha" id="linha-animal" value="Animal">
                    <label class="btn btn-outline-warning" for="linha-animal"><i class="bi bi-egg-fill me-1"></i>Produção Animal</label>
                    <input type="radio" class="btn-check" name="filtro-linha" id="linha-processamento" value="Processamento">
                    <label class="btn btn-outline-info" for="linha-processamento"><i class="bi bi-cup-hot-fill me-1"></i>Processamento (Agroindústria)</label>
                </div>

                <!-- Filtro por categoria -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="filtro-categoria">Categoria</label>
                    <select class="form-select" id="filtro-categoria">
                        <option value="todas">Todas as categorias</option>
                    </select>
                </div>

                <!-- Busca -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="filtro-busca">Buscar</label>
                    <input type="text" class="form-control" id="filtro-busca" placeholder="Digite para filtrar o insumo..." autocomplete="off">
                </div>

                <!-- Lista -->
                <div id="lista-insumos" class="list-group" style="max-height: 45vh; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary disabled" id="btn-usar-selecionado">
                    <i class="bi bi-check-lg"></i> Usar este Insumo
                </button>
            </div>
        </div>
    </div>
</div>

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

    // Validação em tempo real do insumo
    const nomeInput = document.getElementById('nome');
    const feedbackDiv = document.getElementById('insumo-validation-feedback');
    const formInsumo = document.getElementById('form-insumo');
    let timeout = null;
    let estadoInsumo = { nome: '', valido: false };

    nomeInput.addEventListener('input', function() {
        clearTimeout(timeout);
        const nomeVal = nomeInput.value.trim();

        if (nomeVal.length < 3) {
            estadoInsumo = { nome: '', valido: false };
            feedbackDiv.innerHTML = '';
            return;
        }

        timeout = setTimeout(() => {
            feedbackDiv.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Validando insumo...</span>';
            
            fetch(`${window.SITE_URL}/modulos/caderno_campo/validar_insumo.php?nome=${encodeURIComponent(nomeVal)}`)
                .then(response => response.json())
                .then(data => {
                    estadoInsumo = { nome: nomeVal, valido: data.status === 'success' };
                    if (data.status === 'success') {
                        feedbackDiv.innerHTML = `
                            <div class="alert alert-success py-2 px-3 mb-0 mt-1 d-flex align-items-center">
                                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                                <div>
                                    <strong>Permitido:</strong> ${data.message}
                                    ${data.detalhes ? `<br><small class="text-muted">Categoria: ${data.detalhes.categoria}</small>` : ''}
                                </div>
                            </div>`;
                    } else if (data.status === 'warning') {
                        feedbackDiv.innerHTML = `
                            <div class="alert alert-danger py-2 px-3 mb-0 mt-1">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-x-circle-fill me-2 fs-5"></i>
                                    <strong>BLOQUEADO:</strong> ${data.message}
                                </div>
                                <small class="d-block">${data.restricoes ? `Restrições: ${data.restricoes}` : ''}</small>
                            </div>`;
                    } else {
                        feedbackDiv.innerHTML = `
                            <div class="alert alert-danger py-2 px-3 mb-0 mt-1 d-flex align-items-center">
                                <i class="bi bi-x-circle-fill me-2 fs-5"></i>
                                <div>
                                    <strong>BLOQUEADO:</strong> ${data.message}
                                </div>
                            </div>`;
                    }
                })
                .catch(err => {
                    console.error('Erro na validação do insumo:', err);
                    feedbackDiv.innerHTML = '';
                });
        }, 500);
    });

    if (formInsumo) {
        formInsumo.addEventListener('submit', function(e) {
            const nomeVal = nomeInput.value.trim();
            if (nomeVal.length < 3) {
                feedbackDiv.innerHTML = '<div class="alert alert-danger py-2 px-3 mb-0 mt-1"><strong>BLOQUEADO:</strong> Informe o nome do insumo para validação.</div>';
                e.preventDefault();
                return;
            }
            if (estadoInsumo.nome !== nomeVal) {
                e.preventDefault();
                feedbackDiv.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Validando insumo...</span>';
                fetch(`${window.SITE_URL}/modulos/caderno_campo/validar_insumo.php?nome=${encodeURIComponent(nomeVal)}`)
                    .then(response => response.json())
                    .then(data => {
                        estadoInsumo = { nome: nomeVal, valido: data.status === 'success' };
                        if (estadoInsumo.valido) {
                            formInsumo.submit();
                        } else {
                            feedbackDiv.innerHTML = '<div class="alert alert-danger py-2 px-3 mb-0 mt-1"><i class="bi bi-x-circle-fill me-2"></i><strong>BLOQUEADO:</strong> ' + data.message + '</div>';
                        }
                    })
                    .catch(() => {
                        feedbackDiv.innerHTML = '<div class="alert alert-warning py-2 px-3 mb-0 mt-1">Não foi possível validar o insumo. Tente novamente.</div>';
                    });
            } else if (!estadoInsumo.valido) {
                e.preventDefault();
                feedbackDiv.innerHTML = '<div class="alert alert-danger py-2 px-3 mb-0 mt-1"><i class="bi bi-x-circle-fill me-2"></i><strong>BLOQUEADO:</strong> Insumo não permitido pela Portaria 52/2021.</div>';
            }
        });
    }
});
</script>

<script>
(function() {
    'use strict';

    // Dados injetados pelo servidor (insumos aprovados da Portaria 52/2021)
    const INSUMOS_APROVADOS = <?php echo $insumos_aprovados_json; ?>;
    // Abre automaticamente no primeiro acesso e após salvar com sucesso
    const ABRIR_AUTO = <?php echo ($_SERVER['REQUEST_METHOD'] !== 'POST' || $message_type === 'success') ? 'true' : 'false'; ?>;

    const modal = document.getElementById('modalSeletorInsumo');
    const btnAbrir = document.getElementById('btn-abrir-seletor');
    const btnUsar = document.getElementById('btn-usar-selecionado');
    const inputNome = document.getElementById('nome');
    const lista = document.getElementById('lista-insumos');
    const selectCategoria = document.getElementById('filtro-categoria');
    const inputBusca = document.getElementById('filtro-busca');

    if (!modal || !lista) return;

    let insumoSelecionado = null;

    // Popular o select de categorias a partir dos dados
    (function popularCategorias() {
        const cats = {};
        INSUMOS_APROVADOS.forEach(i => { cats[i['categoria']] = true; });
        Object.keys(cats).sort().forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            selectCategoria.appendChild(opt);
        });
    })();

    function linhaAtiva() {
        const r = document.querySelector('input[name="filtro-linha"]:checked');
        return r ? r.value : 'todas';
    }

    function renderizarLista() {
        const linha = linhaAtiva();
        const cat = selectCategoria.value;
        const busca = inputBusca.value.trim().toLowerCase();

        const itens = INSUMOS_APROVADOS.filter(i => {
            if (linha !== 'todas' && i['linha'] !== linha) return false;
            if (cat !== 'todas' && i['categoria'] !== cat) return false;
            if (busca && i['nome'].toLowerCase().indexOf(busca) === -1) return false;
            return true;
        });

        lista.innerHTML = '';

        if (itens.length === 0) {
            lista.innerHTML = '<div class="list-group-item text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Nenhum insumo encontrado.</div>';
            return;
        }

        itens.forEach(i => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            btn.innerHTML = '<div><div class="fw-semibold">' + i['nome'] + '</div>' +
                '<small class="text-muted">' + i['categoria'] + (i['tipo'] ? ' &middot; ' + i['tipo'] : '') + '</small>' +
                (i['restricoes'] ? '<br><small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>' + i['restricoes'] + '</small>' : '') +
                '</div>' +
                '<span class="text-success"><i class="bi bi-plus-circle"></i></span>';

            btn.addEventListener('click', function() {
                insumoSelecionado = i;
                lista.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
                btn.classList.add('active');
                btnUsar.classList.remove('disabled');
            });

            lista.appendChild(btn);
        });
    }

    function reRender() {
        insumoSelecionado = null;
        btnUsar.classList.add('disabled');
        renderizarLista();
    }

    document.querySelectorAll('input[name="filtro-linha"]').forEach(r => {
        r.addEventListener('change', reRender);
    });
    selectCategoria.addEventListener('change', reRender);
    inputBusca.addEventListener('input', function() {
        clearTimeout(window.__buscaTimer);
        window.__buscaTimer = setTimeout(reRender, 200);
    });

    // Limpar estado ao abrir o modal
    function aoAbrirModal() {
        insumoSelecionado = null;
        btnUsar.classList.add('disabled');
        renderizarLista();
    }

    if (btnAbrir) {
        btnAbrir.addEventListener('click', function() {
            aoAbrirModal();
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    }

    modal.addEventListener('shown.bs.modal', aoAbrirModal);

    // Usar selecionado: preenche o campo nome e dispara validação em tempo real
    btnUsar.addEventListener('click', function() {
        if (!insumoSelecionado) return;
        inputNome.value = insumoSelecionado['nome'];
        inputNome.dispatchEvent(new Event('input'));
        inputNome.focus();
        bootstrap.Modal.getInstance(modal).hide();
    });

    // Toque/duplo clique seleciona diretamente
    lista.addEventListener('dblclick', function(e) {
        const btn = e.target.closest('.list-group-item');
        if (!btn) return;
        const idx = Array.prototype.indexOf.call(lista.children, btn);
        const linha = linhaAtiva();
        const cat = selectCategoria.value;
        const busca = inputBusca.value.trim().toLowerCase();
        const visiveis = INSUMOS_APROVADOS.filter(i => {
            if (linha !== 'todas' && i['linha'] !== linha) return false;
            if (cat !== 'todas' && i['categoria'] !== cat) return false;
            if (busca && i['nome'].toLowerCase().indexOf(busca) === -1) return false;
            return true;
        });
        const alvo = visiveis[idx];
        if (alvo) {
            inputNome.value = alvo['nome'];
            inputNome.dispatchEvent(new Event('input'));
            inputNome.focus();
            bootstrap.Modal.getInstance(modal).hide();
        }
    });

    // Abrir automaticamente ao acessar a URL e após salvar com sucesso
    if (ABRIR_AUTO && typeof bootstrap !== 'undefined') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                aoAbrirModal();
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }, 300);
        });
    }
})();
</script>
