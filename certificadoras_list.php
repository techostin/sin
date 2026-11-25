<?php
$page_title = "Certificadoras";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/menu.php';

if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) { echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>"; require_once __DIR__ . '/includes/footer.php'; exit; }

?>

<style>
    .certificadoras-header {
        gap: 1rem;
    }
    .certificadoras-header .header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }
    .dataTables_wrapper .dt-buttons {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        float: none !important;
        margin-top: 0.5rem;
    }
    .dataTables_wrapper .dataTables_length {
        text-align: left;
    }
    .dataTables_wrapper .dataTables_filter {
        text-align: center;
    }
    .dataTables_wrapper .dt-buttons {
        text-align: right;
    }
    @media (max-width: 767px) {
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            text-align: left;
        }
        .dataTables_wrapper .dt-buttons {
            text-align: left;
        }
    }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 certificadoras-header">
        <div>
            <h1 class="h3 mb-0">Certificadoras</h1>
            <p class="mb-0 text-muted">Lista de certificadoras vinculadas ao sistema. As logos são armazenadas em <code>uploads/certificadoras/</code>.</p>
        </div>
        <div class="header-actions">
            <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
                <a href="/certificadoras_cadastrar.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> Nova Certificadora</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="certificadorasTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cidade/UF</th>
                            <th>Contato</th>
                            <th>Logo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(document).ready(function(){
    // Se já existir uma instância, destrói antes de recriar
    if ($.fn.DataTable.isDataTable('#certificadorasTable')) {
        $('#certificadorasTable').DataTable().destroy();
    }

    $('#certificadorasTable').DataTable({
        "ajax": {
            "url": window.SITE_URL + '/api/certificadoras.php',
            "dataSrc": "data",
            "error": function(xhr, error, thrown) {
                console.error('Erro ao carregar certificadoras:', error);
            }
        },
        "columns": [
            { "data": "nome" },
            { "data": null, "render": function(data) {
                return (data.cidade || '') + ' / ' + (data.uf || '');
            }},
            { "data": null, "render": function(data) {
                return data.contato || data.email || '';
            }},
            { "data": null, "orderable": false, "searchable": false, "render": function(data) {
                if (!data.logo_filename) return '-';
                var url = data.logo_url || (window.SITE_URL + '/uploads/certificadoras/' + data.logo_filename);
                return '<img src="' + url + '" style="max-height:48px" alt="Logo de ' + data.nome + '">';
            }},
            { "data": null, "orderable": false, "searchable": false, "render": function(data) {
                return '<a class="btn btn-sm btn-primary" href="' + window.SITE_URL + '/certificadoras_editar.php?id=' + data.id + '">Editar</a>';
            }}
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "responsive": true,
        "autoWidth": false,
        "dom": '<"row mb-3 align-items-center"<"col-sm-12 col-md-4"l><"col-sm-12 col-md-4 text-center"f><"col-sm-12 col-md-4 text-end"B>>rtip',
        "buttons": [
            {
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                className: 'btn btn-success btn-sm me-1 text-white',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                customize: function(xlsx) {
                    var sheet = xlsx.xl.worksheets['sheet1.xml'];
                    var sheetData = $('sheetData', sheet);
                    // Inserir configuração de página paisagem (landscape)
                    var pageSetup = '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
                    var sheetPr = $('sheetPr', sheet);
                    if (sheetPr.length) {
                        sheetPr.after(pageSetup);
                    } else {
                        sheetData.before('<sheetPr/>').before(pageSetup);
                    }
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm me-1 text-white',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer"></i> Imprimir',
                className: 'btn btn-secondary btn-sm text-white',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            }
        ],
        "columnDefs": [
            { "targets": -1, "orderable": false, "searchable": false }
        ]
    });
});
</script>

