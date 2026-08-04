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

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de visita inválido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar a visita e o template
$stmt = $db->prepare("SELECT v.*, r.nome as roteiro_nome, r.checklist 
                      FROM visitas_tecnicas v
                      JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                      WHERE v.id = ?");
$stmt->execute([$id]);
$visita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visita) {
    echo "<div class='alert alert-danger'>Visita não encontrada.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$checklist = json_decode($visita['checklist'], true);

// Normalizar para garantir que todo item tenha 'id', 'item', and 'categoria'
$checklist = array_map(function($item, $index) {
    return [
        'id'        => $item['id']   ?? ($index + 1),
        'item'      => $item['item'] ?? $item['pergunta'] ?? 'Item sem descrição',
        'categoria' => $item['categoria'] ?? 'Geral'
    ];
}, $checklist, array_keys($checklist));

// Group items by category
$groupedChecklist = [];
foreach ($checklist as $item) {
    $groupedChecklist[$item['categoria']][] = $item;
}
?>

<div class="container-fluid px-4 pb-5">
    <h1 class="mt-4 h3"> Checklist de Inspeção</h1>
    <p class="text-muted"><?php echo $visita['roteiro_nome']; ?> - Visita #<?php echo $id; ?></p>

    <form id="formChecklist">
        <input type="hidden" name="visita_id" value="<?php echo $id; ?>">
        <!-- Hidden input to hold uploaded evidencias_fotos array as JSON -->
        <input type="hidden" id="evidencias_fotos_json" name="evidencias_fotos_json" value='[]'>
        
        <?php 
        $itemNumber = 1;
        foreach ($groupedChecklist as $categoria => $items): ?>
            <!-- Category header -->
            <div class="mb-4">
                <h4 class="text-primary fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-folder-fill"></i> <?php echo htmlspecialchars($categoria); ?>
                </h4>
                <?php foreach ($items as $item): ?>
                    <div class="card mb-3 shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="card-title fw-bold"><?php echo ($itemNumber++) . '. ' . htmlspecialchars($item['item']); ?></h6>
                    
                    <div class="d-flex gap-3 my-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_sim" value="Conforme" required>
                            <label class="form-check-label text-success fw-bold" for="item_<?php echo $item['id']; ?>_sim">CONFORME</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_nao" value="Não Conforme">
                            <label class="form-check-label text-danger fw-bold" for="item_<?php echo $item['id']; ?>_nao">NÃO CONFORME</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="item[<?php echo $item['id']; ?>][status]" id="item_<?php echo $item['id']; ?>_na" value="N/A">
                            <label class="form-check-label text-muted" for="item_<?php echo $item['id']; ?>_na">N/A</label>
                        </div>
                    </div>

                    <div class="mt-2">
                        <label class="form-label small text-muted">Observações / Evidências</label>
                        <textarea class="form-control form-control-sm" name="item[<?php echo $item['id']; ?>][obs]" rows="2" placeholder="Descreva observações ou não conformidades..."></textarea>
                    </div>
                </div>
            </div>
                <?php endforeach; ?> <!-- end items loop -->
            </div> <!-- end category div -->
        <?php endforeach; ?> <!-- end categories loop -->

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
</div>

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

    $('#formChecklist').submit(function(e) {
        e.preventDefault();
        
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
            assinatura_produtor_url: signaturePadProdutor.isEmpty() ? null : signaturePadProdutor.toDataURL(),
            assinatura_tecnico_url: signaturePadTecnico.isEmpty() ? null : signaturePadTecnico.toDataURL(),
            evidencias_fotos: evidenciasFotos.filter(url => url !== null) // Filter out nulls
        };

        // Formatar resultados por item (usando IDs do checklist dinâmico)
        $('.card-body').each(function() {
            const itemId = $(this).find('input[type="radio"]').attr('name');
            if (itemId) {
                const idMatch = itemId.match(/\[(.*?)\]/);
                if (idMatch) {
                    const id = idMatch[1];
                    const conforme = $(this).find(`input[value="Conforme"]`).is(':checked');
                    const na = $(this).find(`input[value="N/A"]`).is(':checked');
                    const obs = $(this).find('textarea').val();
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
