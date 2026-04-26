<?php
$page_title = "Não Conformidades";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Gestão de Não Conformidades</h1>
    <p class="text-muted">Acompanhamento de pendências identificadas nas inspeções técnicas.</p>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">Abertas: <span id="total-abertas">0</span></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark mb-4">
                <div class="card-body">Em Prazo: <span id="total-prazo">0</span></div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-table me-1"></i>
            Lista de Ocorrências
        </div>
        <div class="card-body">
            <table class="table table-hover" id="ncTable">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produtor</th>
                        <th>Propriedade</th>
                        <th>Descrição</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-nc">
                    <!-- Preenchido via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    const table = $('#ncTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/roteiros-inspecao?action=nao_conformidades',
            dataSrc: 'data'
        },
        columns: [
            { 
                data: 'data_visita',
                render: function(data) { return data ? new Date(data).toLocaleDateString('pt-BR') : '-'; }
            },
            { data: 'propriedade_nome' },
            { 
                data: 'descricao',
                render: function(data) { return mb_strimwidth_js(data, 60); }
            },
            { 
                data: 'status',
                render: function(data) {
                    const classes = { 'Aberta': 'danger', 'Resolvida': 'success', 'Em Correção': 'warning' };
                    return `<span class="badge bg-${classes[data] || 'secondary'}">${data}</span>`;
                }
            },
            { 
                data: 'prazo_correcao',
                render: function(data) { return data ? new Date(data).toLocaleDateString('pt-BR') : 'Pendente'; }
            },
            {
                data: null,
                render: function(row) {
                    return `
                        <div class="btn-group">
                            <button class="btn btn-outline-success btn-sm" title="Resolver"><i class="bi bi-check-circle"></i></button>
                            <button class="btn btn-outline-primary btn-sm" title="Detalhes"><i class="bi bi-eye"></i></button>
                        </div>
                    `;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' },
        drawCallback: function(settings) {
            const data = settings.json ? settings.json.data : [];
            $('#count-abertas').text(data.filter(i => i.status === 'Aberta').length);
            $('#count-correcao').text(data.filter(i => i.status === 'Em Correção').length);
        }
    });

    function mb_strimwidth_js(str, length) {
        if (!str) return '-';
        return str.length > length ? str.substring(0, length) + '...' : str;
    }
});
</script>
