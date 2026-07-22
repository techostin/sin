<?php
$page_title = "Insumos Orgânicos Aprovados";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Apenas Admin/Gestor podem acessar o catálogo de insumos aprovados
if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso restrito a gestores e administradores.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Insumos Orgânicos Aprovados</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/">Dashboard</a></li>
        <li class="breadcrumb-item active">Insumos Aprovados</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-shield-check me-1"></i>
            Lista de Insumos Permitidos para Uso na Produção Orgânica
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Esta lista contém insumos avaliados e aprovados conforme as normas da Portaria MAPA 52/2021.
            </div>
            
            <table id="insumosTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Nome Comercial</th>
                        <th>Princípio Ativo / Composição</th>
                        <th>Fabricante / Marca</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Carregado via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- DataTables JS e CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#insumosTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/insumos_aprovados.php',
            dataSrc: 'data'
        },
        columns: [
            { data: 'categoria' },
            { data: 'nome_comercial' },
            { data: 'principio_ativo' },
            { data: 'fabricante' },
            { 
                data: 'permitido',
                render: function(data) {
                    return data == 1 
                        ? '<span class="badge bg-success">Permitido</span>' 
                        : '<span class="badge bg-warning">Restrito / sob consulta</span>';
                }
            }
        ],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        }
    });
});
</script>
