<?php
$page_title = isset($_GET['id']) ? "Editar Certificação" : "Nova Certificação";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Apenas Admin/Gestor podem cadastrar ou editar certificações
if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso restrito a gestores e administradores.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$certificacaoId = $isEdit ? $_GET['id'] : null;

// Se for edição, buscar dados existentes
$certificacao = null;
if ($isEdit) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM certificacao_participativa WHERE id = ?");
    $stmt->execute([$certificacaoId]);
    $certificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificacao) {
        echo "<div class='alert alert-danger'>Certificação não encontrada.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
}

// Buscar associados para o select
$db = Database::getInstance()->getConnection();
$stmtAssociados = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome");
$associados = $stmtAssociados->fetchAll(PDO::FETCH_ASSOC);

// Produtos e escopo salvos (edição)
$produtosSelecionados = [];
$escopoSalvo = '';
if ($certificacao && !empty($certificacao['dados_adicionais'])) {
    $adicionais = json_decode($certificacao['dados_adicionais'], true) ?? [];
    $escopoSalvo = $adicionais['escopo'] ?? '';
    if (!empty($adicionais['produtos'])) {
        $produtosSelecionados = array_column($adicionais['produtos'], 'id');
    }
}
?>

<h1 class="mt-4"><?php echo $page_title; ?></h1>
<p>Registro de certificação orgânica - Portaria MAPA 52/2021</p>

<div class="card">
    <div class="card-header">
        <span><?php echo $isEdit ? 'Editar Certificação' : 'Cadastrar Nova Certificação'; ?></span>
    </div>
    <div class="card-body">
        <form id="formCertificacao">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $certificacaoId; ?>">
            <?php endif; ?>
            
            <!-- Informações Básicas -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="associado_id" class="form-label">Associado <span class="text-danger">*</span></label>
                    <select class="form-select" id="associado_id" name="associado_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $assoc): ?>
                            <option value="<?php echo $assoc['id']; ?>" 
                                    <?php echo ($certificacao && $certificacao['associado_id'] == $assoc['id']) ? 'selected' : ''; ?>>
                                <?php echo $assoc['nome'] . ' ' . $assoc['sobrenome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Agricultor titular da certificação</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="tipo_certificacao" class="form-label">Tipo de Certificação <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipo_certificacao" name="tipo_certificacao" required>
                        <option value="SPG" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'SPG') ? 'selected' : ''; ?>>SPG (Sistema Participativo de Garantia)</option>
                        <option value="OCS" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'OCS') ? 'selected' : ''; ?>>OCS (Organização de Controle Social)</option>
                        <option value="Rede Ecovida" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'Rede Ecovida') ? 'selected' : ''; ?>>Rede Ecovida</option>
                        <option value="Outro" <?php echo ($certificacao && $certificacao['tipo_certificacao'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                    </select>
                </div>
            </div>

            <!-- Dados da Certificação -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="numero_certificado" class="form-label">Nº do Certificado</label>
                    <input type="text" class="form-control" id="numero_certificado" name="numero_certificado" 
                           value="<?php echo $certificacao['numero_certificado'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="data_emissao" class="form-label">Data de Emissão</label>
                    <input type="date" class="form-control" id="data_emissao" name="data_emissao" 
                           value="<?php echo $certificacao['data_emissao'] ?? ''; ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="data_validade" class="form-label">Data de Validade</label>
                    <input type="date" class="form-control" id="data_validade" name="data_validade" 
                           value="<?php echo $certificacao['data_validade'] ?? ''; ?>">
                    <small class="text-muted">Certificações SPG geralmente têm validade de 1 ano</small>
                </div>
            </div>

            <!-- Status e Organismo -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Em Conversão" <?php echo ($certificacao && $certificacao['status'] == 'Em Conversão') ? 'selected' : ''; ?>>Em Conversão</option>
                        <option value="Ativa" <?php echo ($certificacao && $certificacao['status'] == 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Vencida" <?php echo ($certificacao && $certificacao['status'] == 'Vencida') ? 'selected' : ''; ?>>Vencida</option>
                        <option value="Suspensa" <?php echo ($certificacao && $certificacao['status'] == 'Suspensa') ? 'selected' : ''; ?>>Suspensa</option>
                        <option value="Cancelada" <?php echo ($certificacao && $certificacao['status'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                    <small class="text-muted">Período de conversão: mínimo 12 meses (Portaria 52/2021)</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="organismo_certificador" class="form-label">Organismo Certificador/SPG</label>
                    <input type="text" class="form-control" id="organismo_certificador" name="organismo_certificador" 
                           value="<?php echo $certificacao['organismo_certificador'] ?? ''; ?>" 
                           placeholder="Ex: SPG Orgânicos do Vale">
                </div>
            </div>

            <!-- Grupo (para SPG) -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="nome_grupo" class="form-label">Nome do Grupo (SPG)</label>
                    <input type="text" class="form-control" id="nome_grupo" name="nome_grupo" 
                           value="<?php echo $certificacao['nome_grupo'] ?? ''; ?>" 
                           placeholder="Nome do grupo de agricultores do SPG">
                    <small class="text-muted">Aplicável para certificações SPG</small>
                </div>
            </div>

            <!-- Escopo e Produtos -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="escopo" class="form-label">Escopo da Certificação</label>
                    <textarea class="form-control" id="escopo" name="escopo" rows="2"
                              placeholder="Ex: Produção primária vegetal — hortaliças, frutas e grãos"><?php echo htmlspecialchars($escopoSalvo); ?></textarea>
                    <small class="text-muted">Descreve a atividade e unidade produtiva certificada</small>
                </div>
            </div>

            <div class="row" id="secaoProdutos" style="display:none;">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Produtos / Culturas Certificados</label>
                    <div id="listaProdutos" class="border rounded p-3 bg-light">
                        <p class="text-muted mb-0 small">Selecione um associado para carregar os produtos.</p>
                    </div>
                    <small class="text-muted">Produtos cadastrados no caderno de campo do associado. Deixe em branco para incluir todos.</small>
                </div>
            </div>

            <!-- URLs de Documentos -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="documento_url" class="form-label">URL do Certificado</label>
                    <input type="url" class="form-control" id="documento_url" name="documento_url" 
                           value="<?php echo $certificacao['documento_url'] ?? ''; ?>" 
                           placeholder="https://exemplo.com/certificado.pdf">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="declaracao_compromisso_url" class="form-label">URL da Declaração de Compromisso</label>
                    <input type="url" class="form-control" id="declaracao_compromisso_url" name="declaracao_compromisso_url" 
                           value="<?php echo $certificacao['declaracao_compromisso_url'] ?? ''; ?>" 
                           placeholder="https://exemplo.com/declaracao.pdf">
                </div>
            </div>

            <!-- Observações -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="observacoes" class="form-label">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4"><?php echo $certificacao['observacoes'] ?? ''; ?></textarea>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> <?php echo $isEdit ? 'Atualizar' : 'Cadastrar'; ?>
                    </button>
                    <button type="button" id="btnPreviewCertificado" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> Visualizar Certificado
                    </button>
                    <button type="button" id="btnGerarDocumentos" class="btn btn-warning text-dark">
                        <i class="bi bi-file-earmark-pdf"></i> Gerar Certificado e Declaração
                    </button>
                    <?php if ($isEdit): ?>
                    <a href="<?php echo SITE_URL; ?>/certificacao/<?php echo $certificacaoId; ?>/certificado?format=html"
                       class="btn btn-outline-danger" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Certificado PDF
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
const PRODUTOS_SELECIONADOS = <?php echo json_encode(array_map('intval', $produtosSelecionados)); ?>;

$(document).ready(function() {
    // Carregar produtos ao selecionar associado
    $('#associado_id').on('change', function() {
        carregarProdutos($(this).val());
    });

    if ($('#associado_id').val()) {
        carregarProdutos($('#associado_id').val());
    }

    function carregarProdutos(associadoId) {
        if (!associadoId) {
            $('#secaoProdutos').hide();
            return;
        }

        $('#secaoProdutos').show();
        $('#listaProdutos').html('<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div> Carregando produtos...</div>');

        fetch('<?php echo SITE_URL; ?>/api/certificacao/controller.php?action=produtos&associado_id=' + associadoId)
            .then(r => r.json())
            .then(data => {
                const produtos = data.data || [];
                if (produtos.length === 0) {
                    $('#listaProdutos').html('<p class="text-muted mb-0 small">Nenhum produto cadastrado para este associado.</p>');
                    return;
                }

                let html = '<div class="row">';
                produtos.forEach(p => {
                    const checked = PRODUTOS_SELECIONADOS.includes(parseInt(p.id)) ? 'checked' : '';
                    html += `
                        <div class="col-md-6 col-lg-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input produto-check" type="checkbox"
                                       name="produtos[]" value="${p.id}" id="prod_${p.id}" ${checked}>
                                <label class="form-check-label" for="prod_${p.id}">
                                    <strong>${p.nome}</strong>
                                    <small class="text-muted d-block">${p.tipo || 'In natura'} — ${p.unidade_medida || ''}</small>
                                </label>
                            </div>
                        </div>`;
                });
                html += '</div>';
                html += '<div class="mt-2"><button type="button" class="btn btn-link btn-sm p-0" id="btnSelecionarTodos">Selecionar todos</button></div>';
                $('#listaProdutos').html(html);
            })
            .catch(() => {
                $('#listaProdutos').html('<p class="text-danger mb-0 small">Erro ao carregar produtos.</p>');
            });
    }

    $(document).on('click', '#btnSelecionarTodos', function() {
        $('.produto-check').prop('checked', true);
    });

    // Pré-visualizar certificado
    $('#btnPreviewCertificado').on('click', function() {
        const associadoId = $('#associado_id').val();
        if (!associadoId) {
            alert('Selecione um associado antes de visualizar o certificado.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo SITE_URL; ?>/certificacao/certificado-preview?format=html';
        form.target = '_blank';

        const campos = ['associado_id', 'tipo_certificacao', 'numero_certificado', 'data_emissao',
                        'data_validade', 'status', 'organismo_certificador', 'nome_grupo', 'escopo', 'observacoes'];

        campos.forEach(name => {
            const el = document.getElementById(name);
            if (el && el.value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = el.value;
                form.appendChild(input);
            }
        });

        document.querySelectorAll('.produto-check:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'produtos[]';
            input.value = cb.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // Gerar Documentos PDF
    $('#btnGerarDocumentos').on('click', function() {
        const associadoId = $('#associado_id').val();
        if (!associadoId) {
            alert('Selecione um associado antes de gerar os documentos.');
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Gerando PDFs...');
        $btn.prop('disabled', true);
        
        const formData = {};
        $('#formCertificacao').serializeArray().forEach(item => {
            if (item.name === 'produtos[]') {
                if (!formData.produtos) formData.produtos = [];
                formData.produtos.push(parseInt(item.value));
            } else {
                formData[item.name] = item.value;
            }
        });
        
        fetch('<?php echo SITE_URL; ?>/api/certificacao/gerar_pdf.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#documento_url').val(data.documento_url);
                $('#declaracao_compromisso_url').val(data.declaracao_compromisso_url);
                alert('Documentos gerados com sucesso! Não se esqueça de salvar o formulário.');
            } else {
                alert('Erro ao gerar documentos: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao comunicar com o servidor.');
        })
        .finally(() => {
            $btn.html(originalText);
            $btn.prop('disabled', false);
        });
    });

    $('#formCertificacao').on('submit', function(e) {
        e.preventDefault();
        
        const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
        const formData = {};
        
        // Coletar dados do formulário
        $(this).serializeArray().forEach(item => {
            if (item.name === 'produtos[]') {
                if (!formData.produtos) formData.produtos = [];
                formData.produtos.push(parseInt(item.value));
            } else {
                formData[item.name] = item.value;
            }
        });

        // Montar dados_adicionais
        formData.dados_adicionais = {
            escopo: formData.escopo || '',
            produtos: formData.produtos || []
        };
        delete formData.escopo;
        delete formData.produtos;
        
        // Configurar requisição
        const url = '<?php echo SITE_URL; ?>/api/certificacao/controller.php';
        const method = isEdit ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(isEdit ? 'Certificação atualizada com sucesso!' : 'Certificação cadastrada com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/certificacoes';
            } else {
                alert('Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar requisição');
        });
    });
});
</script>
