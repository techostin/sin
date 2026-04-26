<?php
$page_title = "Planos de Manejo Orgânico";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1>Planos de Manejo Orgânico</h1>
        <a href="<?php echo SITE_URL; ?>/plano-manejo/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Novo Plano
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-header border-bottom-0 bg-light d-flex justify-content-between">
            <div><i class="bi bi-table me-1"></i> Lista de Planos por Safra</div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary active" data-filter="Todos">Todos</button>
                <button type="button" class="btn btn-outline-secondary" data-filter="Aprovado">Aprovados</button>
                <button type="button" class="btn btn-outline-secondary" data-filter="Em Análise">Em Análise</button>
            </div>
        </div>
        <div class="card-body">
            <table id="planosTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>Safra</th>
                        <th>Tipo</th>
                        <th>Propriedade</th>
                        <th>Agricultor</th>
                        <th>Status</th>
                        <th>Versão</th>
                        <th>Data Criação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const table = $('#planosTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/plano_manejo.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'ano_safra' },
            { 
                data: 'tipo_registro',
                render: function(data) {
                    const label = data === 'Plano' ? 'Plano' : 'Certificação';
                    const color = data === 'Plano' ? 'primary' : 'success';
                    return `<span class="badge bg-${color}">${label}</span>`;
                }
            },
            { data: 'propriedade_nome' },
            { 
                data: null,
                render: function(row) {
                    return `${row.associado_nome} ${row.associado_sobrenome}`;
                }
            },
            { 
                data: 'status',
                render: function(data) {
                    const badges = {
                        'Aprovado': 'success',
                        'Rascunho': 'secondary',
                        'Em Análise': 'info',
                        'Reprovado': 'danger',
                        'Revisão Necessária': 'warning',
                        'Ativa': 'success',
                        'Vencida': 'danger',
                        'Suspensa': 'warning',
                        'Em Conversão': 'info',
                        'Cancelada': 'dark'
                    };
                    return `<span class="badge bg-${badges[data] || 'secondary'}">${data}</span>`;
                }
            },
            { data: 'versao' },
            { 
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleDateString('pt-BR');
                }
            },
            {
                data: null,
                orderable: false,
                render: function(row) {
                    const type = (row.tipo_registro || '').toLowerCase();
                    const baseUrl = type === 'plano' ? 'plano-manejo' : 'certificacao';
                    return `
                        <div class="btn-group">
                            <a href="<?php echo SITE_URL; ?>/${baseUrl}/${row.id}/visualizar" class="btn btn-outline-primary btn-sm" title="Visualizar"><i class="bi bi-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/${baseUrl}/${row.id}/editar" class="btn btn-outline-secondary btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-outline-danger btn-sm" onclick="excluirRegistro(${row.id}, '${row.tipo_registro}')" title="Excluir"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                }
            }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        }
    });

    window.excluirRegistro = function(id, tipo) {
        const msg = tipo === 'Plano' ? 'este plano de manejo' : 'esta certificação';
        const url = tipo === 'Plano' ? 'plano-manejo' : 'certificacao/controller';
        
        if (confirm(`Tem certeza que deseja excluir ${msg}?`)) {
            fetch(`<?php echo SITE_URL; ?>/api/${url}.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    table.ajax.reload();
                } else {
                    alert('Erro ao excluir');
                }
            });
        }
    };
    // Filtros de status
    $('.btn-group .btn').on('click', function() {
        const filter = $(this).data('filter');
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        
        if (filter === 'Todos') {
            table.column(4).search('').draw();
        } else {
            table.column(4).search(filter).draw();
        }
    });
});
</script>
