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
                                <td>
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
                        text: '<?php echo SITE_URL; ?>/lote/<?php echo $lote['uuid']; ?>',
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
    
    // Função para imprimir QR Code individual
    function imprimirQRCode(loteId) {
        var qrcodeDiv = document.getElementById('qrcode-' + loteId);
        if (!qrcodeDiv) return;
        
        // Obter a imagem do canvas gerado pelo QRCode.js
        var canvas = qrcodeDiv.querySelector('canvas');
        var imgSrc = canvas ? canvas.toDataURL("image/png") : qrcodeDiv.querySelector('img').src;
        
        var loteTr = qrcodeDiv.closest('tr');
        var loteNome = loteTr ? loteTr.querySelector('td:nth-child(3)').innerText : 'Lote ' + loteId;
        
        var printWin = window.open('', '', 'width=600,height=600');
        printWin.document.write('<html><head><title>Imprimir QR Code</title>');
        printWin.document.write('<style>body { font-family: Arial, sans-serif; text-align: center; padding: 40px; } </style>');
        printWin.document.write('</head><body>');
        printWin.document.write('<h2>SINPORG - Etiqueta de Lote</h2>');
        printWin.document.write('<h3>' + loteNome + '</h3>');
        printWin.document.write('<h4>LOTE-' + String(loteId).padStart(6, '0') + '</h4>');
        printWin.document.write('<img src="' + imgSrc + '" style="margin-top:20px; width:250px; height:250px;" />');
        printWin.document.write('<p style="margin-top: 20px; color:#555;">Escaneie o QR Code para rastrear os detalhes da produção.</p>');
        printWin.document.write('</body></html>');
        printWin.document.close();
        printWin.focus();
        setTimeout(function() {
            printWin.print();
            printWin.close();
        }, 500);
    }
</script>
