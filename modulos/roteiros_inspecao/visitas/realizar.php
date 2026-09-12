<?php
$page_title = "Realizar Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

require_once __DIR__ . '/../../../classes/ChecklistPadrao.php';

$id = $_GET['id'] ?? null;
$hashid = $_GET['hashid'] ?? null;

// Se não tem id numérico mas tem hashid, buscar o id
if ((!$id || !is_numeric($id)) && $hashid) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM visitas_tecnicas WHERE hashid = ?");
    $stmt->execute([$hashid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = $row['id'];
    }
}

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de visita inválido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar a visita, o template, a propriedade e o produtor
$stmt = $db->prepare("SELECT v.*, r.nome as roteiro_nome, r.checklist, r.itens_criticos, 
                             p.propriedade as propriedade_nome, p.cidade, p.estado as uf,
                             a.nome as associado_nome, a.sobrenome as associado_sobrenome
                      FROM visitas_tecnicas v
                      JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                      JOIN propriedades p ON v.propriedade_id = p.id
                      JOIN associados a ON v.associado_id = a.id
                      WHERE v.id = ?");
$stmt->execute([$id]);
$visita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visita) {
    echo "<div class='alert alert-danger'>Visita não encontrada.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Status: se já Realizada, não deixar reabrir inspeção
if ($visita['status'] !== 'Agendada') {
    echo "<div class='alert alert-warning mt-4'>Esta visita já foi <strong>" . htmlspecialchars($visita['status']) . "</strong>. Inspeções só podem ser realizadas em visitas agendadas. <a href='" . SITE_URL . "/visitas-tecnicas' class='alert-link'>Voltar para listagem</a>.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$roteiroChecklist = json_decode($visita['checklist'], true);
$itensCriticos = json_decode($visita['itens_criticos'] ?: '[]', true);
$itensCriticos = array_map('strval', is_array($itensCriticos) ? $itensCriticos : []);

// Mesclar com os itens padrão de produção e processamento orgânico
$checklist = ChecklistPadrao::mesclar($roteiroChecklist);

// Normalizar para garantir que todo item tenha 'id', 'item', 'categoria' e 'obrigatorio'
$checklist = array_map(function($item, $index) use ($itensCriticos) {
    $obrigatorio = !empty($item['obrigatorio']) || in_array(strval($item['id'] ?? ''), $itensCriticos, true);
    return [
        'id'         => $item['id']   ?? ($index + 1),
        'item'       => $item['item'] ?? $item['pergunta'] ?? 'Item sem descrição',
        'categoria'  => $item['categoria'] ?? 'Geral',
        'obrigatorio'=> $obrigatorio
    ];
}, $checklist, array_keys($checklist));

// Group items by category
$groupedChecklist = [];
foreach ($checklist as $item) {
    $groupedChecklist[$item['categoria']][] = $item;
}

// Contagem para validação e progresso
$totalItens     = count($checklist);
$totalCriticos  = count(array_filter($checklist, function($i) { return $i['obrigatorio']; }));
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <h1 class="mb-1"><i class="bi bi-clipboard2-check me-2" style="color:var(--gm-primary);"></i>Checklist de Inspeção</h1>
        <p class="mb-0"><?php echo htmlspecialchars($visita['roteiro_nome']); ?> — Visita #<?php echo $id; ?></p>
    </div>

    <!-- Barra de progresso e resumo -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
            <span><strong id="progress-agrupado">0</strong>/<?php echo $totalItens; ?> itens respondidos</span>
            <span class="badge bg-danger" id="progress-criticos">0/<?php echo $totalCriticos; ?> itens críticos</span>
        </div>
        <div class="progress" style="height: 10px;">
            <div id="progress-bar" class="progress-bar progress-bar-striped bg-success" role="progressbar" style="width:0%"></div>
        </div>
    </div>

    <!-- Dados da visita / identificação -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-3"><span class="text-muted">Produtor:</span> <strong><?php echo htmlspecialchars(trim($visita['associado_nome'] . ' ' . $visita['associado_sobrenome'])); ?></strong></div>
                <div class="col-md-3"><span class="text-muted">Propriedade:</span> <strong><?php echo htmlspecialchars($visita['propriedade_nome']); ?></strong></div>
                <div class="col-md-3"><span class="text-muted">Local:</span> <strong><?php echo htmlspecialchars(trim(($visita['cidade'] ?? '') . ($visita['uf'] ? '/' . $visita['uf'] : ''))); ?></strong></div>
                <div class="col-md-3"><span class="text-muted">Roteiro:</span> <strong><?php echo htmlspecialchars($visita['roteiro_nome']); ?></strong></div>
            </div>
            <hr class="my-3">
            <div class="row g-3">
                <div class="col-sm-4 col-lg-3">
                    <label class="form-label small text-muted mb-1">Data da Visita</label>
                    <input type="text" class="form-control form-control-sm" value="<?php echo date('d/m/Y', strtotime($visita['data_visita'])); ?>" readonly>
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small text-muted mb-1">Hora Início <span class="text-danger">*</span></label>
                    <input type="time" class="form-control form-control-sm" name="hora_inicio" id="hora_inicio" required>
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="form-label small text-muted mb-1">Hora Fim <span class="text-danger">*</span></label>
                    <input type="time" class="form-control form-control-sm" name="hora_fim" id="hora_fim" required>
                </div>
                <div class="col-sm-12 col-lg-5">
                    <label class="form-label small text-muted mb-1">Condições do Tempo <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" name="condicoes_clima" id="condicoes_clima" required>
                        <option value="">Selecione...</option>
                        <option value="Ensolarado">Ensolarado</option>
                        <option value="Parcialmente Nublado">Parcialmente Nublado</option>
                        <option value="Nublado">Nublado</option>
                        <option value="Chuvoso">Chuvoso</option>
                        <option value="Chuva Fraca">Chuva Fraca</option>
                        <option value="Chuva Forte">Chuva Forte</option>
                        <option value="Ventoso">Ventoso</option>
                        <option value="Neve/Granizo">Neve/Granizo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <form id="formChecklist" novalidate>
        <input type="hidden" name="visita_id" value="<?php echo $id; ?>">
        <!-- Hidden input to hold uploaded evidencias_fotos array as JSON -->
        <input type="hidden" id="evidencias_fotos_json" name="evidencias_fotos_json" value='[]'>
        
        <?php 
        $itemNumber = 1;
        $catIndex = 0;
        $categoriaNames = array_keys($groupedChecklist);
        ?>
        <?php if (count($categoriaNames) > 1): ?>
        <!-- Abas de categorias -->
        <ul class="nav nav-tabs flex-nowrap overflow-auto mb-4" id="checklist-tabs" role="tablist" style="white-space:nowrap; -webkit-overflow-scrolling:touch;">
            <?php foreach ($groupedChecklist as $categoria => $items): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $catIndex === 0 ? 'active' : ''; ?>"
                            id="tab-<?php echo $catIndex; ?>-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#tab-<?php echo $catIndex; ?>"
                            type="button" role="tab"
                            aria-controls="tab-<?php echo $catIndex; ?>"
                            aria-selected="<?php echo $catIndex === 0 ? 'true' : 'false'; ?>">
                        <i class="bi bi-folder-fill me-1" style="color:var(--gm-primary);"></i>
                        <?php echo htmlspecialchars($categoria); ?>
                        <span class="badge text-bg-light border ms-1" id="tab-count-<?php echo $catIndex; ?>"><?php echo count($items); ?></span>
                    </button>
                </li>
            <?php $catIndex++; endforeach; ?>
        </ul>

        <div class="tab-content" id="checklist-tab-content">
            <?php $catIndex = 0; foreach ($groupedChecklist as $categoria => $items): ?>
                <div class="tab-pane fade <?php echo $catIndex === 0 ? 'show active' : ''; ?>"
                     id="tab-<?php echo $catIndex; ?>"
                     role="tabpanel"
                     aria-labelledby="tab-<?php echo $catIndex; ?>-tab">
                    <h4 class="text-primary fw-bold border-bottom pb-2 mb-3">
                        <i class="bi bi-folder-fill"></i> <?php echo htmlspecialchars($categoria); ?>
                    </h4>
                    <?php foreach ($items as $item): ?>
                        <div class="card mb-3 shadow-sm border-0 checklist-card" data-item-id="<?php echo htmlspecialchars($item['id']); ?>" data-obrigatorio="<?php echo $item['obrigatorio'] ? '1' : '0'; ?>">
                            <div class="card-body">
                                <h6 class="card-title fw-bold">
                                    <?php echo ($itemNumber++) . '. ' . htmlspecialchars($item['item']); ?>
                                    <?php if ($item['obrigatorio']): ?>
                                        <span class="badge bg-danger ms-1" title="Item crítico: reprova a aprovação se não conforme">Crítico</span>
                                    <?php endif; ?>
                                </h6>
                        
                                <div class="d-flex flex-wrap gap-3 my-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_sim" value="Conforme" required>
                                        <label class="form-check-label text-success fw-bold" for="item_<?php echo htmlspecialchars($item['id']); ?>_sim">CONFORME</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_nao" value="Não Conforme">
                                        <label class="form-check-label text-danger fw-bold" for="item_<?php echo htmlspecialchars($item['id']); ?>_nao">NÃO CONFORME</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_na" value="N/A">
                                        <label class="form-check-label text-muted" for="item_<?php echo htmlspecialchars($item['id']); ?>_na">N/A</label>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <label class="form-label small text-muted">Observações / Evidências</label>
                                    <textarea class="form-control form-control-sm" name="item[<?php echo htmlspecialchars($item['id']); ?>][obs]" rows="2" placeholder="Descreva observações ou não conformidades..."></textarea>
                                    <div class="invalid-feedback obs-feedback" style="display:none;">Preencha a observação quando o item for Não Conforme.</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?> <!-- end items loop -->
                </div> <!-- end tab-pane -->
            <?php $catIndex++; endforeach; ?>
        </div>
        <?php else: ?>
        <?php foreach ($groupedChecklist as $categoria => $items): ?>
            <h4 class="text-primary fw-bold border-bottom pb-2 mb-3">
                <i class="bi bi-folder-fill"></i> <?php echo htmlspecialchars($categoria); ?>
            </h4>
            <?php foreach ($items as $item): ?>
                <div class="card mb-3 shadow-sm border-0 checklist-card" data-item-id="<?php echo htmlspecialchars($item['id']); ?>" data-obrigatorio="<?php echo $item['obrigatorio'] ? '1' : '0'; ?>">
                    <div class="card-body">
                        <h6 class="card-title fw-bold">
                            <?php echo ($itemNumber++) . '. ' . htmlspecialchars($item['item']); ?>
                            <?php if ($item['obrigatorio']): ?>
                                <span class="badge bg-danger ms-1" title="Item crítico: reprova a aprovação se não conforme">Crítico</span>
                            <?php endif; ?>
                        </h6>
                
                        <div class="d-flex flex-wrap gap-3 my-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_sim" value="Conforme" required>
                                <label class="form-check-label text-success fw-bold" for="item_<?php echo htmlspecialchars($item['id']); ?>_sim">CONFORME</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_nao" value="Não Conforme">
                                <label class="form-check-label text-danger fw-bold" for="item_<?php echo htmlspecialchars($item['id']); ?>_nao">NÃO CONFORME</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item[<?php echo htmlspecialchars($item['id']); ?>][status]" id="item_<?php echo htmlspecialchars($item['id']); ?>_na" value="N/A">
                                <label class="form-check-label text-muted" for="item_<?php echo htmlspecialchars($item['id']); ?>_na">N/A</label>
                            </div>
                        </div>

                        <div class="mt-2">
                            <label class="form-label small text-muted">Observações / Evidências</label>
                            <textarea class="form-control form-control-sm" name="item[<?php echo htmlspecialchars($item['id']); ?>][obs]" rows="2" placeholder="Descreva observações ou não conformidades..."></textarea>
                            <div class="invalid-feedback obs-feedback" style="display:none;">Preencha a observação quando o item for Não Conforme.</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?> <!-- end items loop -->
        <?php endforeach; ?> <!-- end categories loop -->
        <?php endif; ?>

<div class="card mb-4 shadow-sm border-0 bg-light">
            <div class="card-body">
                <h6 class="fw-bold">Fotos de Evidência (máximo 2)</h6>
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="evidencia1" class="form-label">Foto 1</label>
                            <input type="file" id="evidencia1" class="form-control" accept="image/*">
                            <div id="preview1" class="mt-2"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="evidencia2" class="form-label">Foto 2</label>
                            <input type="file" id="evidencia2" class="form-control" accept="image/*">
                            <div id="preview2" class="mt-2"></div>
                        </div>
                    </div>
                    <div id="upload-status" class="mt-2"></div>
                </div>

                <h6 class="fw-bold mt-4">Assinaturas</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Assinatura do Produtor</label>
                        <div class="signature-container border bg-white rounded">
                            <canvas id="signature-produtor" class="signature-pad" width="400" height="150"></canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearSignature('produtor')">Limpar</button>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Assinatura do Técnico</label>
                        <div class="signature-container border bg-white rounded">
                            <canvas id="signature-tecnico" class="signature-pad" width="400" height="150"></canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearSignature('tecnico')">Limpar</button>
                    </div>
                </div>

                <h6 class="fw-bold mt-4">Parecer Final</h6>
                <div class="mb-0">
                    <textarea class="form-control" name="observacoes" rows="3" placeholder="Insira o resumo técnico da visita..."></textarea>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success btn-lg shadow">
                <i class="bi bi-check-circle me-2"></i> Finalizar Inspeção
            </button>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle me-2"></i> Cancelar / Voltar
            </a>
        </div>
    </form>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<!-- Signature Pad JS -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<style>
/* Estilos para Mobile */
.card-body { padding: 1rem; }
.form-check-input { width: 1.5em; height: 1.5em; cursor: pointer; }
.form-check-label { padding-top: 0.2rem; margin-left: 0.3rem; cursor: pointer; }
.signature-container { position: relative; touch-action: none; width: 100%; max-width: 400px; }
.signature-pad { width: 100%; height: 150px; }
.card { transition: transform 0.2s; }
.card:active { transform: scale(0.98); }
</style>

<script>
let signaturePadProdutor;
let signaturePadTecnico;
let evidenciasFotos = [];
let uploadedUrls = [null, null]; // Tracks uploaded URLs for each of the 2 upload fields

$(document).ready(function() {
    // Inicializar Signature Pads
    const canvasP = document.getElementById('signature-produtor');
    const canvasT = document.getElementById('signature-tecnico');
    
    signaturePadProdutor = new SignaturePad(canvasP);
    signaturePadTecnico = new SignaturePad(canvasT);

    // Ajustar tamanho dos canvas para responsividade
    function resizeCanvas() {
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        [canvasP, canvasT].forEach(canvas => {
            // Se necessário adicionar lógica de resize aqui
        });
    }
    window.onresize = resizeCanvas;
    resizeCanvas();

    // File input listeners
    $('#evidencia1').change(function() { handleFileSelect(0, this.files[0]); });
    $('#evidencia2').change(function() { handleFileSelect(1, this.files[0]); });

    // ── Barra de progresso: contar itens respondidos e críticos ──
    function updateProgress() {
        const cards = $('.checklist-card').toArray();
        let answered = 0, criticalAnswered = 0;
        let criticalBad = 0;
        cards.forEach(card => {
            const $card = $(card);
            const checked = $card.find('input[type="radio"]:checked');
            const isCritical = $card.attr('data-obrigatorio') === '1';
            if (checked.length) {
                answered++;
                if (isCritical) criticalAnswered++;
                if (isCritical && checked.val() === 'Não Conforme') criticalBad++;
            }
        });
        const total = cards.length;
        const pct = total ? Math.round((answered / total) * 100) : 0;
        $('#progress-agrupado').text(answered);
        $('#progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
        $('#progress-criticos').text(criticalAnswered + '/' + cards.filter(c => $(c).attr('data-obrigatorio') === '1').length + ' itens críticos'
            + (criticalBad ? ' ⚠ ' + criticalBad + ' NC' : ''));
        $('#progress-criticos').toggleClass('bg-danger', criticalAnswered > 0 ? !criticalBad : false)
            .toggleClass('bg-warning', criticalBad > 0);

        // Progresso por aba
        $('.tab-pane').each(function(tabIdx) {
            const $pane = $(this);
            const paneCards = $pane.find('.checklist-card').toArray();
            let paneAnswered = 0, paneBad = 0;
            paneCards.forEach(card => {
                const $card = $(card);
                const checked = $card.find('input[type="radio"]:checked');
                if (checked.length) {
                    paneAnswered++;
                    if (checked.val() === 'Não Conforme') paneBad++;
                }
            });
            const pending = paneCards.length - paneAnswered;
            const $badge = $('#tab-count-' + tabIdx);
            if ($badge) {
                $badge.text(pending > 0 ? paneAnswered + '/' + paneCards.length : paneCards.length)
                       .toggleClass('text-bg-light', pending === 0 && paneBad === 0)
                       .toggleClass('text-bg-success', pending === 0 && paneBad === 0)
                       .toggleClass('text-bg-warning', pending === 0 && paneBad > 0)
                       .toggleClass('text-bg-danger', pending > 0);
            }
        });
    }

    // Atualizar progresso ao marcar qualquer radio
    $(document).on('change', '.checklist-card input[type="radio"]', updateProgress);

    // Marcar como inválido um item crítico sem resposta, ao tentar enviar
    function validateCards() {
        let ok = true;
        let firstInvalid = null;
        $('.checklist-card').each(function() {
            const $card = $(this);
            const answered = $card.find('input[type="radio"]:checked').length > 0;
            if (!answered) {
                if (!$card.hasClass('border-danger')) $card.addClass('border-danger');
                if (!firstInvalid) firstInvalid = this;
                updateProgress();
                ok = false;
            } else {
                $card.removeClass('border-danger'); // Pending answer resolved
                const isNaoConforme = $card.find('input[type="radio"]:checked').val() === 'Não Conforme';
                const $obs = $card.find('textarea');
                if (isNaoConforme && !$obs.val().trim()) {
                    $card.addClass('border-danger');
                    $card.find('.obs-feedback').show();
                    if (!firstInvalid) firstInvalid = this;
                    ok = false;
                } else {
                    $card.find('.obs-feedback').hide();
                }
            }
        });
        return { ok: ok, firstInvalid: firstInvalid };
    }

    $('#formChecklist').submit(function(e) {
        e.preventDefault();

        // 1) Validação do cabeçalho da visita
        const horaInicio = $('#hora_inicio').val();
        const horaFim    = $('#hora_fim').val();
        const clima      = $('#condicoes_clima').val();
        if (!horaInicio || !horaFim || !clima) {
            alert('Preencha hora de início, hora de fim e condições do tempo.');
            (horaInicio ? (horaFim ? $('#condicoes_clima') : $('#hora_fim')) : $('#hora_inicio')).focus();
            return;
        }
        if (horaFim <= horaInicio) {
            alert('A hora de fim deve ser posterior à hora de início.');
            $('#hora_fim').focus();
            return;
        }

        // 2) Validação do checklist (todos os itens e observações de NC)
        const res = validateCards();
        if (!res.ok) {
            alert('Itens em destaque vermelho estão pendentes. Todos os itens precisam de resposta e todo item \'Não Conforme\' exige observação.');
            if (res.firstInvalid) {
                // Ativar a aba que contém o primeiro item inválido
                const pane = $(res.firstInvalid).closest('.tab-pane');
                if (pane.length) {
                    const tabId = pane.attr('id');
                    const btn = $('button[data-bs-target="#' + tabId + '"]');
                    if (btn.length) {
                        bootstrap.Tab.getOrCreateInstance(btn.get(0)).show();
                    }
                }
                res.firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        if (signaturePadProdutor.isEmpty() || signaturePadTecnico.isEmpty()) {
            if (!confirm('Uma ou ambas as assinaturas estão vazias. Deseja prosseguir mesmo assim?')) {
                return;
            }
        }
        
        const loader = $('<div class="text-center my-3"><div class="spinner-border text-success"></div><p>Salvando e gerando conformidades...</p></div>');
        $('#formChecklist button[type="submit"]').prop('disabled', true).hide();
        $('#formChecklist').append(loader);

        // Coleta de dados
        const formData = {
            visita_id: $('input[name="visita_id"]').val(),
            resultado: {},
            observacoes: $('textarea[name="observacoes"]').val(),
            hora_inicio: horaInicio,
            hora_fim: horaFim,
            condicoes_clima: clima,
            assinatura_produtor_url: signaturePadProdutor.isEmpty() ? null : signaturePadProdutor.toDataURL(),
            assinatura_tecnico_url: signaturePadTecnico.isEmpty() ? null : signaturePadTecnico.toDataURL(),
            evidencias_fotos: evidenciasFotos.filter(url => url !== null) // Filter out nulls
        };

        // Formatar resultados por item (usando IDs do checklist dinâmico)
        $('.checklist-card').each(function() {
            const $card = $(this);
            const itemId = $card.find('input[type="radio"]').attr('name');
            if (itemId) {
                const idMatch = itemId.match(/\[(.*?)\]/);
                if (idMatch) {
                    const id = idMatch[1];
                    const conforme = $card.find(`input[value="Conforme"]`).is(':checked');
                    const na = $card.find(`input[value="N/A"]`).is(':checked');
                    const obs = $card.find('textarea').val();
                    formData.resultado[id] = { conforme: conforme, na: na, observacao: obs };
                }
            }
        });

        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=visitas', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Inspeção finalizada com sucesso! Pontuação calculada e Não-Conformidades registradas.');
                window.location.href = '<?php echo SITE_URL; ?>/visitas-tecnicas';
            } else {
                alert('Erro ao salvar inspeção: ' + (data.error || 'Erro desconhecido'));
                $('#formChecklist button[type="submit"]').prop('disabled', false).show();
                loader.remove();
            }
        })
        .catch(err => {
            alert('Falha na comunicação com o servidor.');
            $('#formChecklist button[type="submit"]').prop('disabled', false).show();
            loader.remove();
        });
    });

    updateProgress();
});

function handleFileSelect(index, file) {
    if (!file) return;
    
    const previewDiv = $('#preview' + (index + 1));
    const statusDiv = $('#upload-status');
    previewDiv.html('<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Carregando...');

    const formData = new FormData();
    formData.append('evidencia', file);

    fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=upload_evidencia', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            uploadedUrls[index] = data.url;
            evidenciasFotos = uploadedUrls.filter(url => url !== null);
            $('#evidencias_fotos_json').val(JSON.stringify(evidenciasFotos));
            
            // Show preview
            previewDiv.html(`
                <img src="${data.url}" alt="Evidência" class="img-thumbnail" style="max-height:150px">
                <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="removeEvidencia(${index})">Remover</button>
            `);
            statusDiv.html('<div class="alert alert-success">Arquivo enviado com sucesso!</div>');
        } else {
            previewDiv.html('');
            statusDiv.html(`<div class="alert alert-danger">Erro no upload: ${data.error}</div>`);
        }
    })
    .catch(err => {
        previewDiv.html('');
        statusDiv.html(`<div class="alert alert-danger">Erro na comunicação: ${err.message}</div>`);
    });
}

function removeEvidencia(index) {
    uploadedUrls[index] = null;
    evidenciasFotos = uploadedUrls.filter(url => url !== null);
    $('#evidencias_fotos_json').val(JSON.stringify(evidenciasFotos));
    $('#preview' + (index + 1)).html('');
    $('#evidencia' + (index + 1)).val(''); // Clear file input
    $('#upload-status').html('<div class="alert alert-info">Arquivo removido!</div>');
}

function clearSignature(type) {
    if (type === 'produtor') signaturePadProdutor.clear();
    else signaturePadTecnico.clear();
}
</script>
