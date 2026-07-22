<?php
$page_title = "Templates de Roteiros de Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Apenas Admin/Gestor podem listar templates de roteiros
if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso restrito a gestores e administradores.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1>Templates de Roteiros</h1>
        <a href="<?php echo SITE_URL; ?>/roteiro-inspecao/novo" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Novo Roteiro
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <table id="templatesTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Versão</th>
                        <th>Itens</th>
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

<!-- Modal Novo Template (Simplificado para o exemplo) -->
<div class="modal fade" id="modalNovoTemplate" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Template de Roteiro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoTemplate">
                    <div class="mb-3">
                        <label class="form-label">Nome do Roteiro</label>
                        <input type="text" class="form-control" name="nome" required placeholder="Ex: Roteiro para SPG - Anual">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Visita</label>
                        <select class="form-select" name="tipo">
                            <option value="Visita Inicial">Visita Inicial</option>
                            <option value="Visita Regular">Visita Regular</option>
                            <option value="Visita de Renovação">Visita de Renovação</option>
                            <option value="Visita Surpresa">Visita Surpresa</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Checklist (JSON format - simplificado no modal)</label>
                        <textarea class="form-control" name="checklist" rows="5">[{"id":1, "item": "Verificar limites da propriedade", "obrigatorio": true}]</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Criar Template</button>
                </form>
            </div>
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
    const table = $('#templatesTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates',
            dataSrc: 'data'
        },
        columns: [
            { data: 'codigo' },
            { data: 'nome' },
            { data: 'tipo' },
            { data: 'versao' },
            { 
                data: 'checklist',
                render: function(data) {
                    return data ? data.length + ' itens' : '0 itens';
                }
            },
            {
                data: null,
                render: function(row) {
                    return `
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' }
    });

    $('#formNovoTemplate').submit(function(e) {
        e.preventDefault();
        const data = {
            nome: $('input[name="nome"]').val(),
            tipo: $('select[name="tipo"]').val(),
            checklist: JSON.parse($('textarea[name="checklist"]').val())
        };

        fetch('<?php echo SITE_URL; ?>/api/roteiros-inspecao?action=templates', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Template criado!');
                location.reload();
            } else {
                alert('Erro ao criar template');
            }
        });
    });
});
</script>
