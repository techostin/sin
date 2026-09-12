<?php
$page_title = "Novo Roteiro de Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$isAdmin = $userNivel <= NIVEL_GESTOR;

if (!$isAdmin) {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas gestores podem cadastrar roteiros.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

require_once __DIR__ . '/../../../classes/RoteiroInspecao.php';
$roteiroId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$roteiro = null;
if ($roteiroId) {
    $roteiro = (new RoteiroInspecao())->getById($roteiroId);
    if (!$roteiro) {
        echo "<div class='alert alert-danger'>Roteiro não encontrado.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-file-earmark-plus me-2" style="color:var(--gm-primary);"></i><?= $roteiro ? 'Editar' : 'Novo'; ?> Roteiro de Inspeção</h1>
                <p class="mb-0">Crie modelos de checklist personalizados para vistorias técnicas.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($roteiro): ?>
                <a href="<?php echo SITE_URL; ?>/roteiro-inspecao/ficha/<?php echo $roteiro['id']; ?>"
                   class="btn btn-assoc-success btn-sm" target="_blank" title="Gerar a Ficha de Inspeção em PDF para impressão">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Ficha de Inspeção (PDF)
                </a>
                <?php endif; ?>
                <a href="<?php echo SITE_URL; ?>/roteiros-inspecao" class="btn btn-assoc-ghost btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar para Listagem
                </a>
            </div>
        </div>
    </div>

    <form id="formTemplate" class="needs-validation" novalidate>
        <div class="row g-4">
            <!-- Coluna de Configuração Geral -->
            <div class="col-lg-4">
                <div class="gm-card mb-4">
                    <div class="card-head">
                        <i class="bi bi-gear-fill me-2" style="color:var(--gm-primary);"></i>Identificação do Roteiro
                    </div>
                    <div class="p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nome do Roteiro *</label>
                            <input type="text" class="form-control" name="nome" required placeholder="Ex: Roteiro SPG Anual 2026" value="<?= htmlspecialchars($roteiro['nome'] ?? ''); ?>">
                            <div class="invalid-feedback">O nome do roteiro é obrigatório.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Código do Roteiro</label>
                            <input type="text" class="form-control" name="codigo" placeholder="Ex: ROT-SPG-01" value="<?= htmlspecialchars($roteiro['codigo'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tipo de Visita *</label>
                            <select class="form-select" name="tipo" required>
                                <option value="Visita Regular" <?= ($roteiro['tipo'] ?? '') === 'Visita Regular' ? 'selected' : ''; ?>>Visita Regular</option>
                                <option value="Visita Inicial" <?= ($roteiro['tipo'] ?? '') === 'Visita Inicial' ? 'selected' : ''; ?>>Visita Inicial</option>
                                <option value="Visita de Renovação" <?= ($roteiro['tipo'] ?? '') === 'Visita de Renovação' ? 'selected' : ''; ?>>Visita de Renovação</option>
                                <option value="Visita Surpresa" <?= ($roteiro['tipo'] ?? '') === 'Visita Surpresa' ? 'selected' : ''; ?>>Visita Surpresa</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pontuação Mínima para Aprovação (%) *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="pontuacao_minima" value="<?= htmlspecialchars($roteiro['pontuacao_minima'] ?? '70'); ?>" min="0" max="100" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Percentual mínimo de conformidade necessário.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Descrição</label>
                            <textarea class="form-control" name="descricao" rows="2" placeholder="Breve resumo da finalidade deste roteiro..."><?= htmlspecialchars($roteiro['descricao'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Objetivo Técnico</label>
                            <textarea class="form-control" name="objetivo" rows="2" placeholder="Qual o principal objetivo a atingir nesta inspeção..."><?= htmlspecialchars($roteiro['objetivo'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna do Construtor de Checklist -->
            <div class="col-lg-8">
                <div class="gm-card mb-4">
                    <div class="card-head d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-2" style="color:var(--gm-primary);"></i>Itens do Checklist</span>
                        <span class="badge bg-secondary p-2" id="kpi-total-itens">Total de Itens: 0</span>
                    </div>
                    <div class="p-4">
                        <div class="alert alert-info py-2 small d-flex align-items-center">
                            <i class="bi bi-info-circle-fill me-2 fs-5 text-primary"></i>
                            <div>Adicione os itens de vistoria. Itens marcados como <strong>críticos/obrigatórios</strong> geram reprovação automática caso não sejam atendidos.</div>
                        </div>

                        <div id="checklist-builder" class="mb-3">
                            <!-- Items inserted dynamically -->
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top pt-3">
                            <button type="button" class="btn btn-outline-success btn-sm shadow-sm" id="btn-add-item">
                                <i class="bi bi-plus-circle-fill me-1"></i> Adicionar Item
                            </button>
                            <div class="small text-muted fw-bold" id="total-peso-label">Peso Total Acumulado: 0</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <a href="<?php echo SITE_URL; ?>/roteiros-inspecao" class="btn btn-assoc-ghost px-4">Cancelar</a>
                    <button type="submit" class="btn btn-assoc-success px-5 fw-bold" id="btn-salvar">
                        <i class="bi bi-save me-1"></i> Salvar Roteiro
                    </button>
                </div>
            </div>
        </div>
    </form>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<style>
/* Estilização para o construtor dinâmico */
#checklist-builder {
    max-height: 62vh;
    overflow-y: auto;
    padding-right: 6px;
    scrollbar-width: thin;
    scrollbar-color: #4f5bd5 #eef1f8;
}
#checklist-builder::-webkit-scrollbar { width: 8px; }
#checklist-builder::-webkit-scrollbar-track { background: #eef1f8; border-radius: 8px; }
#checklist-builder::-webkit-scrollbar-thumb {
    background: #4f5bd5; border-radius: 8px;
}
#checklist-builder::-webkit-scrollbar-thumb:hover { background: #3c46c0; }
.checklist-item-row {
    position: relative;
    border-left: 4px solid #198754;
    transition: all 0.2s ease-in-out;
}
.checklist-item-row:hover {
    box-shadow: 0 .125rem .25rem rgba(0,0,0,0.075)!important;
}
.btn-remove-item {
    font-size: 0.8rem;
    padding: 0.1rem 0.3rem;
}
@media (max-height: 700px) {
    #checklist-builder { max-height: 52vh; }
}
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    let itemCount = 0;

    // Função para renderizar um novo item no checklist
    function renderItemRow(index, values = {}) {
        const itemText = values.item || '';
        const categoria = values.categoria || 'Geral';
        const obrigatorio = values.obrigatorio === true ? 'checked' : '';
        const peso = values.peso || 10;

        const html = `
            <div class="card mb-3 shadow-sm border-0 checklist-item-row" data-index="${index}" id="item-row-${index}">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-light text-dark fw-bold border">Item #${index + 1}</span>
                        <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-remove-item" data-index="${index}" title="Remover Item">
                            <i class="bi bi-trash-fill"></i> Excluir
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 col-12">
                            <label class="form-label small fw-bold mb-1">Requisito / Pergunta *</label>
                            <input type="text" class="form-control form-control-sm" name="checklist[${index}][item]" value="${itemText}" required placeholder="Ex: O produtor mantém o caderno de campo atualizado?">
                            <div class="invalid-feedback">O texto da pergunta é obrigatório.</div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold mb-1">Categoria *</label>
                            <select class="form-select form-select-sm" name="checklist[${index}][categoria]" required>
                                <option value="Geral" ${categoria === 'Geral' ? 'selected' : ''}>Geral</option>
                                <option value="Solo" ${categoria === 'Solo' ? 'selected' : ''}>Solo e Adubação</option>
                                <option value="Água" ${categoria === 'Água' ? 'selected' : ''}>Manejo de Água</option>
                                <option value="Insumos" ${categoria === 'Insumos' ? 'selected' : ''}>Insumos Utilizados</option>
                                <option value="Sementes" ${categoria === 'Sementes' ? 'selected' : ''}>Sementes e Mudas</option>
                                <option value="Manejo" ${categoria === 'Manejo' ? 'selected' : ''}>Controle de Pragas</option>
                                <option value="Armazenamento" ${categoria === 'Armazenamento' ? 'selected' : ''}>Armazenamento</option>
                                <option value="Documentação" ${categoria === 'Documentação' ? 'selected' : ''}>Documentação</option>
                                <option value="Rastreabilidade" ${categoria === 'Rastreabilidade' ? 'selected' : ''}>Rastreabilidade</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-4">
                            <label class="form-label small fw-bold mb-1">Peso *</label>
                            <input type="number" class="form-control form-control-sm input-peso" name="checklist[${index}][peso]" value="${peso}" min="1" required>
                        </div>
                        <div class="col-md-1 col-2 d-flex flex-column align-items-center justify-content-center pt-3">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input input-critico" type="checkbox" name="checklist[${index}][obrigatorio]" value="1" ${obrigatorio} id="critico-${index}">
                                <label class="form-check-label small text-danger fw-bold" for="critico-${index}" title="Se marcado, a desconformidade deste item gera reprovação automática.">Crítico</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#checklist-builder').append(html);
        itemCount++;
        updateTotals();
    }

    // Atualiza KPIs e Peso Acumulado
    function updateTotals() {
        const rows = $('.checklist-item-row');
        $('#kpi-total-itens').text(`Total de Itens: ${rows.length}`);
        
        let totalPeso = 0;
        $('.input-peso').each(function() {
            totalPeso += parseInt($(this).val()) || 0;
        });
        $('#total-peso-label').text(`Peso Total Acumulado: ${totalPeso}`);
    }

    // Botão Adicionar Item
    $('#btn-add-item').on('click', function() {
        renderItemRow(itemCount);
    });

    // Botão Remover Item
    $(document).on('click', '.btn-remove-item', function() {
        const idx = $(this).data('index');
        $(`#item-row-${idx}`).remove();
        
        // Reordenar os números exibidos
        $('.checklist-item-row').each(function(newIdx) {
            $(this).find('.badge').text(`Item #${newIdx + 1}`);
        });
        updateTotals();
    });

    // Monitorar alterações nos pesos para atualizar o acumulado em tempo real
    $(document).on('change keyup', '.input-peso', function() {
        updateTotals();
    });

    // Inserir primeiro item por padrão
    renderItemRow(0, { item: 'O produtor mantém o plano de manejo devidamente assinado?', categoria: 'Documentação', obrigatorio: true, peso: 10 });
    renderItemRow(1, { item: 'Há histórico ou suspeita de uso de defensivos químicos ou adubação mineral proibida?', categoria: 'Insumos', obrigatorio: true, peso: 20 });
    renderItemRow(2, { item: 'Há barreiras vegetais adequadas para evitar contaminação de propriedades vizinhas?', categoria: 'Manejo', obrigatorio: false, peso: 10 });

    <?php if ($roteiro): ?>
    // Modo edição: limpar itens padrão e carregar os salvos
    $('#checklist-builder').empty();
    itemCount = 0;
    const itensCriticosEdit = <?= json_encode($roteiro['itens_criticos'] ?? []); ?>;
    (<?= json_encode($roteiro['checklist'] ?? []); ?>).forEach(function(it, idx) {
        renderItemRow(idx, {
            item: it.item,
            categoria: it.categoria,
            obrigatorio: it.obrigatorio === true || it.obrigatorio === 1 || it.obrigatorio === '1' || itensCriticosEdit.indexOf(it.id) !== -1,
            peso: it.peso
        });
    });
    <?php endif; ?>

    // Submissão do Formulário
    $('#formTemplate').on('submit', function(e) {
        e.preventDefault();
        
        // Validação HTML5 Bootstrap
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            alert('❌ Por favor, preencha todos os campos obrigatórios.');
            return;
        }

        const itemsRows = $('.checklist-item-row');
        if (itemsRows.length === 0) {
            alert('❌ O roteiro precisa conter no mínimo 1 item de checklist.');
            return;
        }

        $('#btn-salvar').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Salvando...');

        // Montar payload JSON
        const checklistArray = [];
        const itensCriticosArray = [];

        itemsRows.each(function(index) {
            const idx = $(this).data('index');
            const itemText = $(this).find(`input[name="checklist[${idx}][item]"]`).val().trim();
            const cat = $(this).find(`select[name="checklist[${idx}][categoria]"]`).val();
            const peso = parseInt($(this).find(`input[name="checklist[${idx}][peso]"]`).val()) || 10;
            const critico = $(this).find(`input[name="checklist[${idx}][obrigatorio]"]`).is(':checked');

            const itemId = index + 1;
            checklistArray.push({
                id: itemId,
                item: itemText,
                categoria: cat,
                peso: peso,
                obrigatorio: critico
            });

            if (critico) {
                itensCriticosArray.push(itemId);
            }
        });

        const payload = {
            id: <?= $roteiroId ? $roteiroId : 'null'; ?>,
            nome: $('input[name="nome"]').val().trim(),
            codigo: $('input[name="codigo"]').val().trim() || null,
            tipo: $('select[name="tipo"]').val(),
            pontuacao_minima: parseInt($('input[name="pontuacao_minima"]').val()) || 70,
            descricao: $('textarea[name="descricao"]').val().trim() || null,
            objetivo: $('textarea[name="objetivo"]').val().trim() || null,
            checklist: checklistArray,
            itens_criticos: itensCriticosArray
        };

        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (!response.ok) throw new Error('Falha na resposta do servidor.');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('✅ Roteiro de Inspeção salvo com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/roteiros-inspecao';
            } else {
                alert('❌ Erro ao salvar roteiro: ' + (data.error || 'Erro desconhecido.'));
                $('#btn-salvar').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Salvar Roteiro');
            }
        })
        .catch(error => {
            console.error('Submit Error:', error);
            alert('❌ Erro de conexão com o servidor. Tente novamente.');
            $('#btn-salvar').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Salvar Roteiro');
        });
    });
});
</script>
