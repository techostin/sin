<?php
$page_title = "Listar Lotes de Produção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/LoteProducao.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Verificar se o usuário está logado
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lote_producao = new LoteProducao();

if ($user->hasPermission(NIVEL_GESTOR)) {
    $lotes = $lote_producao->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $lotes = $lote_producao->getByAssociadoId($associado_id);
}

?>

<h1 class="mt-4">Lotes de Produção Cadastrados</h1>
<p>Abaixo está a lista de todos os lotes de produção registrados no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Lotes de Produção</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/lote/novo" class="btn btn-primary btn-sm">Adicionar Novo Lote</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive table-technical">
            <table id="lotesTable" class="table table-striped table-bordered text-center align-middle" style="width:100%">
                <thead>
                    <tr class="text-center align-middle">
                        <th style="display:none;">ID</th>
                        <th>QR Code</th>
                        <th>Nome do Lote</th>
                        <th>Produto</th>
                        <th>Data Produção</th>
                        <th>Quantidade/Unidade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lotes) > 0): ?>
                        <?php foreach ($lotes as $lote): ?>
                            <tr class="text-center align-middle">
                                <td style="display:none;"><?php echo isset($lote['id']) ? htmlspecialchars($lote['id']) : '-'; ?></td>
                                <td data-rastreio-url="<?php echo SITE_URL; ?>/rastreabilidade?uuid=<?php echo isset($lote['uuid']) ? urlencode($lote['uuid']) : ''; ?>">
                                    <div class="qrcode-container">
                                        <div class="qrcode" id="qrcode-<?php echo isset($lote['id']) ? $lote['id'] : '0'; ?>"></div>
                                        <div class="qrcode-number"><?php echo isset($lote['id']) ? 'LOTE-' . str_pad($lote['id'], 6, '0', STR_PAD_LEFT) : '-'; ?></div>
                                    </div>
                                </td>
                                <td><?php echo isset($lote['nome_lote']) ? htmlspecialchars($lote['nome_lote']) : '-'; ?></td>
                                <td><?php echo isset($lote['produto_nome']) ? htmlspecialchars($lote['produto_nome']) : '-'; ?></td>
                                <td><?php echo isset($lote['data_producao']) ? date('d/m/Y', strtotime($lote['data_producao'])) : '-'; ?></td>
                                <td><?php echo (isset($lote['quantidade']) ? htmlspecialchars($lote['quantidade']) : '-') . ' ' . (isset($lote['unidade_medida']) ? htmlspecialchars($lote['unidade_medida']) : '-'); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo SITE_URL; ?>/lote/<?php echo isset($lote['uuid']) ? $lote['uuid'] : ($lote['id'] ?? 0); ?>" class="btn btn-info btn-sm" title="Rastrear"><i class="bi bi-search"></i> Rastrear</a>
                                        <a href="<?php echo SITE_URL; ?>/lote/<?php echo isset($lote['uuid']) ? $lote['uuid'] : ($lote['id'] ?? 0); ?>/editar" class="btn btn-warning btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                        <button type="button" class="btn btn-secondary btn-sm" title="Imprimir QR Code" onclick="imprimirQRCode(<?php echo isset($lote['id']) ? $lote['id'] : 0; ?>)"><i class="bi bi-printer"></i></button>
                                        <?php if (!isset($_SESSION['user_tipo']) || $_SESSION['user_tipo'] !== 'usuario'): ?>
                                            <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/lotes_producao/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este lote?');">
                                                <input type="hidden" name="id" value="<?php echo isset($lote['id']) ? (int) $lote['id'] : 0; ?>">
                                                <?php echo CSRFProtection::getTokenField(); ?>
                                                <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum lote de produção encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
<style>
    .qrcode {
        max-width: 100%;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: white;
        min-height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .qrcode img {
        max-width: 100%;
        height: auto;
        border-radius: 2px;
    }
    .qrcode-container {
        text-align: center;
        padding: 2px;
    }
    .qrcode-number {
        font-family: monospace;
        font-size: 11px;
        color: #666;
        margin-top: 2px;
    }
</style>
<!-- Biblioteca para gerar QR Codes -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<!-- Bibliotecas para Impressão e Exportação do DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    $(document).ready(function() {
        $('#lotesTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-4"l><"col-sm-12 col-md-4 text-center"B><"col-sm-12 col-md-4"f>>rtip',
            buttons: [
                {
                    extend: 'print',
                    text: '<i class="bi bi-printer"></i> Imprimir',
                    className: 'btn btn-secondary',
                    title: 'Relatório de Lotes de Produção',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    },
                    customize: function (win) {
                        var css = '@page { size: landscape; }',
                            head = win.document.head || win.document.getElementsByTagName('head')[0],
                            style = win.document.createElement('style');
                        style.type = 'text/css';
                        style.media = 'print';
                        style.appendChild(win.document.createTextNode(css));
                        head.appendChild(style);
                    }
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                    className: 'btn btn-success',
                    title: 'Relatório de Lotes de Produção',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                    className: 'btn btn-danger',
                    title: 'Relatório de Lotes de Produção',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':not(:last-child)'
                    },
                    customize: function(doc) {
                        doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                        doc.defaultStyle.fontSize = 10;
                        doc.styles.tableHeader.fontSize = 11;
                    }
                }
            ],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            },
            "columnDefs": [
                {
                    "targets": 0, // ID
                    "visible": false,
                    "searchable": false
                },
                {
                    "targets": 1, // coluna do QR Code
                    "orderable": false,
                    "searchable": false,
                    "width": "150px"
                },
                {
                    "targets": -1, // última coluna (ações)
                    "orderable": false,
                    "searchable": false
                }
            ]
        });

        // Gerar QR Codes para cada lote
        <?php if (count($lotes) > 0): ?>
            <?php foreach ($lotes as $lote): ?>
                <?php if (isset($lote['id']) && $lote['id']): ?>
                    new QRCode(document.getElementById('qrcode-<?php echo $lote['id']; ?>'), {
                        text: '<?php echo SITE_URL; ?>/rastreabilidade?uuid=<?php echo $lote['uuid']; ?>',
                        width: 100,
                        height: 100,
                        colorDark: "#000000",
                        colorLight: "#FFFFFF",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    });
    
    // Função para imprimir QR Code individual (etiqueta de embalagem em tamanho físico adequado)
    function imprimirQRCode(loteId) {
        var qrcodeDiv = document.getElementById('qrcode-' + loteId);
        if (!qrcodeDiv) return;

        var loteTr = qrcodeDiv.closest('tr');
        var loteNome = loteTr ? loteTr.querySelector('td:nth-child(3)').innerText : 'Lote ' + loteId;
        var produtoNome = loteTr ? loteTr.querySelector('td:nth-child(4)').innerText : '';
        var dataProducao = loteTr ? loteTr.querySelector('td:nth-child(5)').innerText : '';
        var quantidade = loteTr ? loteTr.querySelector('td:nth-child(6)').innerText : '';

        // URL pública de rastreabilidade (regenerada em alta resolução para impressão)
        var urlRastreabilidade = decodeURIComponent(qrcodeDiv.closest('tr').querySelector('td:nth-child(2)').getAttribute('data-rastreio-url')) || '';

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
        printWin.document.write('<div class="sub">Rastreabilidade da Agricultura Familiar Orgânica</div>');
        printWin.document.write('<div class="produto">' + produtoNome + '</div>');
        printWin.document.write('<div class="lote">' + loteNome.replace(/^LOTE-/i, 'LOTE-') + '</div>');
        printWin.document.write('<div class="qr"><img id="qr-img" src="" alt="QR Code"></div>');
        printWin.document.write('<div class="info">');
        printWin.document.write('<div><b>Produção:</b> ' + dataProducao + '</div>');
        printWin.document.write('<div><b>Quantidade:</b> ' + quantidade + '</div>');
        printWin.document.write('</div>');
        printWin.document.write('<div style="text-align:center;"><span class="selo">&#10004; ORGÂNICO</span></div>');
        printWin.document.write('<div class="footer">Escaneie o QR Code para consultar a origem completa do produto (Portaria MAPA 52/2021).</div>');
        printWin.document.write('</body></html>');
        printWin.document.close();

        // Regenerar QR em alta resolução (400px) para impressão nítida
        var qrEl = printWin.document.getElementById('qr-img');
        var qrCanvas = document.createElement('div');
        new QRCode(qrCanvas, {
            text: urlRastreabilidade || window.location.origin + '/rastreabilidade?uuid=' + loteId,
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
