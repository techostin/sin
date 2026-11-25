<?php
$page_title = "Lotes de Animais";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$paClass = new ProducaoAnimal();

if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($paClass->softDeleteLote($id)) {
        AuditLog::registrar('PA_LOTE_EXCLUIDO', 'animal_lotes', $id, 'Lote de animais excluído');
    }
    header('Location: ' . SITE_URL . '/producao-animal/lotes');
    exit;
}

$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$lotes = $paClass->getLotes($escopo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-boxes text-success"></i> Lotes de Animais</h1>
        <a href="<?php echo SITE_URL; ?>/producao-animal/lote/novo" class="btn btn-success"><i class="bi bi-plus-circle"></i> Novo Lote</a>
    </div>

    <?php if (isset($_GET['cadastrado'])): ?><div class="alert alert-success alert-dismissible fade show">Lote cadastrado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_GET['atualizado'])): ?><div class="alert alert-success alert-dismissible fade show">Lote atualizado com sucesso.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="lotesAnimaisTable">
                    <thead class="table-light">
                        <tr><th>QR Code</th><th>Lote</th><th>Espécie</th><th>Categoria</th><th class="text-center">Animais</th><th class="text-end">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$lotes): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum lote cadastrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($lotes as $l): ?>
                            <tr>
                                <td class="text-center" data-rastreio-url="<?php echo SITE_URL; ?>/producao-animal/rastreabilidade?lote=<?php echo (int)$l['id']; ?>">
                                    <div class="qrcode" id="qrcode-pa-<?php echo (int)$l['id']; ?>" style="display:inline-block;border:1px solid #ddd;padding:4px;background:#fff;border-radius:4px;width:82px;height:82px;"></div>
                                    <div class="small text-muted mt-1" style="font-family:monospace;">AL-<?php echo str_pad($l['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                </td>
                                <td><strong><?php echo htmlspecialchars($l['nome_lote']); ?></strong></td>
                                <td><?php echo htmlspecialchars($l['especie'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($l['categoria'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo (int)$l['animais_total']; ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Imprimir etiqueta (QR)" onclick="imprimirEtiquetaAnimal(<?php echo (int)$l['id']; ?>)"><i class="bi bi-printer"></i></button>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/lote/<?php echo $l['id']; ?>/editar" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/producao-animal/lotes?excluir=<?php echo $l['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este lote?');"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($lotes as $l): ?>
        new QRCode(document.getElementById('qrcode-pa-<?php echo (int)$l['id']; ?>'), {
            text: '<?php echo SITE_URL; ?>/producao-animal/rastreabilidade?lote=<?php echo (int)$l['id']; ?>',
            width: 74,
            height: 74,
            colorDark: "#000000",
            colorLight: "#FFFFFF",
            correctLevel: QRCode.CorrectLevel.H
        });
    <?php endforeach; ?>
});

function imprimirEtiquetaAnimal(loteId) {
    var tr = document.querySelector('#qrcode-pa-' + loteId).closest('tr');
    var nomeLote = tr.querySelector('td:nth-child(2)').innerText;
    var especie = tr.querySelector('td:nth-child(3)').innerText;
    var categoria = tr.querySelector('td:nth-child(4)').innerText;
    var quantidade = tr.querySelector('td:nth-child(5)').innerText;
    var urlRastreio = decodeURIComponent(tr.querySelector('td:first-child').getAttribute('data-rastreio-url')) || '';

    var printWin = window.open('', '', 'width=700,height=700');
    printWin.document.write('<html><head><title>Etiqueta de Embalagem - SINPORG</title>');
    printWin.document.write('<style>');
    printWin.document.write('@page { size: 60mm 90mm; margin: 4mm; }');
    printWin.document.write('* { box-sizing: border-box; margin: 0; padding: 0; }');
    printWin.document.write('body { font-family: Arial, Helvetica, sans-serif; width: 52mm; color: #111; }');
    printWin.document.write('.brand { font-size: 13px; font-weight: bold; color: #2f9e44; text-align: center; letter-spacing: 1px; }');
    printWin.document.write('.sub { font-size: 8px; color: #555; text-align: center; margin-bottom: 3mm; }');
    printWin.document.write('.produto { font-size: 11px; font-weight: bold; text-align: center; margin: 1mm 0 3mm 0; line-height: 1.2; }');
    printWin.document.write('.lote { font-size: 14px; font-weight: bold; font-family: Consolas, monospace; text-align: center; margin-bottom: 2mm; }');
    printWin.document.write('.qr { text-align: center; margin: 2mm 0; }');
    printWin.document.write('.qr img { width: 34mm; height: 34mm; image-rendering: pixelated; }');
    printWin.document.write('.info { font-size: 8px; color: #333; line-height: 1.5; }');
    printWin.document.write('.info b { display: inline-block; min-width: 24mm; }');
    printWin.document.write('.footer { font-size: 7px; color: #666; text-align: center; margin-top: 2mm; }');
    printWin.document.write('.selo { font-size: 7px; font-weight: bold; color: #fff; background: #2f9e44; display: inline-block; padding: 1px 6px; border-radius: 8px; margin-top: 1mm; }');
    printWin.document.write('</style>');
    printWin.document.write('</head><body>');
    printWin.document.write('<div class="brand">SINPORG</div>');
    printWin.document.write('<div class="sub">Rastreabilidade da Produção Animal Orgânica</div>');
    printWin.document.write('<div class="produto">' + nomeLote + '</div>');
    printWin.document.write('<div class="lote">AL-' + String(loteId).padStart(6, '0') + '</div>');
    printWin.document.write('<div class="qr"><img id="qr-img" src="" alt="QR Code"></div>');
    printWin.document.write('<div class="info">');
    printWin.document.write('<div><b>Espécie:</b> ' + especie + '</div>');
    printWin.document.write('<div><b>Categoria:</b> ' + categoria + '</div>');
    printWin.document.write('<div><b>Animais:</b> ' + quantidade + '</div>');
    printWin.document.write('</div>');
    printWin.document.write('<div style="text-align:center;"><span class="selo">&#10004; ORGÂNICO</span></div>');
    printWin.document.write('<div class="footer">Escaneie o QR Code para consultar a origem completa do lote (Portaria MAPA 52/2021).</div>');
    printWin.document.write('</body></html>');
    printWin.document.close();

    var qrEl = printWin.document.getElementById('qr-img');
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

    printWin.focus();
    setTimeout(function() { printWin.print(); }, 900);
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>