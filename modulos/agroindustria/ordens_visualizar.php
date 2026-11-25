<?php
$page_title = "Ordem de Produção";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/OrdemProducao.php';
require_once __DIR__ . '/../../classes/ReceitaAgroindustria.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

if (!$user->isLoggedIn()) {
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$opClass = new OrdemProducao();
$op = $opClass->getById($id);
if (!$op) {
    echo '<div class="alert alert-warning m-3">Ordem de produção não encontrada.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Anti-IDOR: a ordem de produção é dado do associado (ordens_producao.associado_id).
// Admin/Gestor visualizam qualquer ordem; nível 3 só as do próprio associado.
$donoOp = (int) ($op['associado_id'] ?? 0);
if (!$user->isGestorOuAdmin()
    && ($donoOp <= 0 || $donoOp !== (int) $user->getAssociadoIdResolvido())) {
    echo '<div class="alert alert-danger m-3">Acesso negado: esta ordem de produção pertence a outro produtor.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$itens = $opClass->getItensRastreabilidade($id);
$balanco = $opClass->calcularBalanco($itens, $op['quantidade_final_obtida']);

$seloLabels = [
    'PRODUTO_ORGANICO_BRASIL' => 'Produto Orgânico Brasil (≥95%)',
    'INGREDIENTES_ORGANICOS' => 'Ingredientes Orgânicos (70–94%)',
    'SEM_SELO' => 'Sem selo (<70%)',
];
$statusBadge = [
    'APROVADO' => ['bg-success', 'Aprovado'],
    'ALERTA_FRAUDE' => ['bg-danger', 'Alerta de Fraude'],
    'EM_ANALISE' => ['bg-warning text-dark', 'Em análise'],
];
[$badge, $label] = $statusBadge[$op['status_auditoria']] ?? ['bg-secondary', $op['status_auditoria']];

$urlRastreabilidade = SITE_URL . '/agroindustria/rastreabilidade?lote=' . urlencode($op['lote_processado_final']);
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-clipboard-check text-success"></i> Ordem de Produção <?php echo htmlspecialchars($op['lote_processado_final']); ?></h1>
        <div>
            <a href="<?php echo SITE_URL; ?>/agroindustria/ordens/nova" class="btn btn-success"><i class="bi bi-plus-circle"></i> Nova</a>
            <a href="<?php echo SITE_URL; ?>/agroindustria/ordens" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (isset($_GET['criada'])): ?>
        <div class="alert alert-success">Ordem de produção registrada com sucesso. Status de auditoria: <strong><?php echo $label; ?></strong>.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong><i class="bi bi-info-circle"></i> Dados da Ordem</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Produto Final</label><strong><?php echo htmlspecialchars($op['nome_produto_final']); ?></strong></div>
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Processamento</label><strong><?php echo date('d/m/Y H:i', strtotime($op['data_processamento'])); ?></strong></div>
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Quantidade Final</label><strong><?php echo number_format((float)$op['quantidade_final_obtida'], 2, ',', '.') . ' ' . htmlspecialchars($op['unidade_medida']); ?></strong></div>
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Percentual Orgânico</label><strong><?php echo $op['percentual_organico'] !== null ? number_format((float)$op['percentual_organico'], 1, ',', '.') . '%' : '—'; ?></strong></div>
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Selo Autorizado</label><span class="badge bg-success"><?php echo htmlspecialchars($seloLabels[$op['selo_autorizado']] ?? $op['selo_autorizado'] ?? '—'); ?></span></div>
                        <div class="col-sm-6 mb-2"><label class="text-muted small d-block">Status Auditoria</label><span class="badge <?php echo $badge; ?>"><?php echo $label; ?></span></div>
                        <?php if (!empty($op['observacoes'])): ?>
                            <div class="col-12"><label class="text-muted small d-block">Observações</label><?php echo nl2br(htmlspecialchars($op['observacoes'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong><i class="bi bi-shield-check"></i> RF11 — Balanço de Massas</strong></div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <label class="text-muted small d-block">Entrada (lotes)</label>
                            <h4 class="mb-0"><?php echo number_format($balanco['total_entrada'], 2, ',', '.'); ?> kg</h4>
                        </div>
                        <div class="col-4">
                            <label class="text-muted small d-block">Saída (produto final)</label>
                            <h4 class="mb-0"><?php echo number_format($balanco['quantidade_final'], 2, ',', '.'); ?> kg</h4>
                        </div>
                        <div class="col-4">
                            <label class="text-muted small d-block">Divergência</label>
                            <h4 class="mb-0"><?php echo number_format($balanco['divergencia_pct'], 2, ',', '.') . '%'; ?></h4>
                        </div>
                    </div>
                    <div class="alert mt-3 mb-0 <?php echo $balanco['status'] === 'ALERTA_FRAUDE' ? 'alert-danger' : 'alert-success'; ?> py-2">
                        <i class="bi <?php echo $balanco['status'] === 'ALERTA_FRAUDE' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'; ?>"></i>
                        <?php echo htmlspecialchars($balanco['mensagem']); ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><i class="bi bi-box-seam"></i> Lotes Primários Processados (Campo)</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Lote (Campo)</th><th>Produto</th><th>Quantidade</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nome_lote'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($item['nome_produto'] ?? '—'); ?></td>
                                        <td><?php echo number_format((float)$item['quantidade_utilizada'], 2, ',', '.') . ' ' . htmlspecialchars($item['unidade_medida']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$itens): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Nenhum lote vinculado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm mb-3 text-center">
                <div class="card-header bg-white"><strong><i class="bi bi-qr-code"></i> QR Code do Produto Final</strong></div>
                <div class="card-body">
                    <div class="qrcode" id="qrcode-op" style="display:inline-block;"></div>
                    <p class="mt-3 mb-0"><code><?php echo htmlspecialchars($op['lote_processado_final']); ?></code></p>
                    <a href="<?php echo htmlspecialchars($urlRastreabilidade); ?>" target="_blank" class="btn btn-sm btn-outline-success mt-2"><i class="bi bi-box-arrow-up-right"></i> Ver página pública</a>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="imprimirQR()"><i class="bi bi-printer"></i> Imprimir</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new QRCode(document.getElementById('qrcode-op'), {
        text: <?php echo json_encode($urlRastreabilidade); ?>,
        width: 160,
        height: 160,
        correctLevel: QRCode.CorrectLevel.H
    });
});

function imprimirQR() {
    var urlRastreio = <?php echo json_encode($urlRastreabilidade); ?>;
    var w = window.open('', '_blank', 'width=700,height=700');
    w.document.write('<html><head><title>Etiqueta de Embalagem - SINPORG</title>');
    w.document.write('<style>');
    w.document.write('@page { size: 60mm 90mm; margin: 4mm; }');
    w.document.write('* { box-sizing: border-box; margin: 0; padding: 0; }');
    w.document.write('body { font-family: Arial, Helvetica, sans-serif; width: 52mm; color: #111; }');
    w.document.write('.brand { font-size: 13px; font-weight: bold; color: #2f9e44; text-align: center; letter-spacing: 1px; }');
    w.document.write('.sub { font-size: 8px; color: #555; text-align: center; margin-bottom: 3mm; }');
    w.document.write('.produto { font-size: 11px; font-weight: bold; text-align: center; margin: 1mm 0 3mm 0; line-height: 1.2; }');
    w.document.write('.lote { font-size: 14px; font-weight: bold; font-family: Consolas, monospace; text-align: center; margin-bottom: 2mm; }');
    w.document.write('.qr { text-align: center; margin: 2mm 0; }');
    w.document.write('.qr img { width: 34mm; height: 34mm; image-rendering: pixelated; }');
    w.document.write('.info { font-size: 8px; color: #333; line-height: 1.5; }');
    w.document.write('.info b { display: inline-block; min-width: 24mm; }');
    w.document.write('.footer { font-size: 7px; color: #666; text-align: center; margin-top: 2mm; }');
    w.document.write('.selo { font-size: 7px; font-weight: bold; color: #fff; background: #2f9e44; display: inline-block; padding: 1px 6px; border-radius: 8px; margin-top: 1mm; }');
    w.document.write('</style>');
    w.document.write('</head><body>');
    w.document.write('<div class="brand">SINPORG</div>');
    w.document.write('<div class="sub">Rastreabilidade do Produto Final Agroindustrial</div>');
    w.document.write('<div class="produto"><?php echo htmlspecialchars($op['nome_produto_final']); ?></div>');
    w.document.write('<div class="lote"><?php echo htmlspecialchars($op['lote_processado_final']); ?></div>');
    w.document.write('<div class="qr"><img id="qr-img" src="" alt="QR Code"></div>');
    w.document.write('<div class="info">');
    w.document.write('<div><b>Processamento:</b> <?php echo date('d/m/Y', strtotime($op['data_processamento'])); ?></div>');
    w.document.write('<div><b>Quantidade:</b> <?php echo number_format((float)$op['quantidade_final_obtida'], 2, ',', '.') . ' ' . htmlspecialchars($op['unidade_medida']); ?></div>');
    w.document.write('<div><b>Orgânico:</b> <?php echo $op['percentual_organico'] !== null ? number_format((float)$op['percentual_organico'], 1, ',', '.') . '%' : '—'; ?></div>');
    w.document.write('</div>');
    w.document.write('<div style="text-align:center;"><span class="selo">&#10004; <?php echo htmlspecialchars($seloLabels[$op['selo_autorizado']] ?? $op['selo_autorizado'] ?? 'ORGÂNICO'); ?></span></div>');
    w.document.write('<div class="footer">Escaneie o QR Code para consultar a origem completa (matéria-prima, nota fiscal, fornecedor e cadeia produtiva).</div>');
    w.document.write('</body></html>');
    w.document.close();

    var qrEl = w.document.getElementById('qr-img');
    var qrCanvas = document.createElement('div');
    new QRCode(qrCanvas, {
        text: urlRastreio,
        width: 400,
        height: 400,
        colorDark: "#000000",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.H
    });
    setTimeout(function() {
        var canvas = qrCanvas.querySelector('canvas') || qrCanvas.querySelector('img');
        qrEl.src = canvas ? canvas.toDataURL('image/png') : '';
    }, 300);

    w.focus();
    setTimeout(function() { w.print(); }, 900);
}
</script>
