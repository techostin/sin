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

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-card-checklist me-2" style="color:var(--gm-primary);"></i>Templates de Roteiros</h1>
                <p class="mb-0">Modelos de checklist utilizados nas inspeções e visitas técnicas.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/roteiro-inspecao/novo" class="btn btn-assoc-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Novo Roteiro
            </a>
        </div>
    </div>

    <div class="gm-card mb-4">
        <div class="card-head"><i class="bi bi-collection me-1" style="color:var(--gm-primary);"></i> Roteiros disponíveis</div>
        <div class="p-3">
            <div class="table-responsive">
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

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

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
                    const id = row.id;
                    return `
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm btn-edit" data-id="${id}" data-nome="${(row.nome||'').replace(/"/g, '&quot;')}" title="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger btn-sm btn-delete" data-id="${id}" data-nome="${(row.nome||'').replace(/"/g, '&quot;')}" title="Excluir"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' }
    });

    // Event delegation: botões de editar e excluir
    $(document).on('click', '.btn-edit', function() {
        const id = $(this).data('id');
        window.location.href = '<?php echo SITE_URL; ?>/roteiro-inspecao/editar/' + id;
    });

    $(document).on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        if (!confirm('Excluir o roteiro "' + nome + '" (#id ' + id + ')? Esta ação não pode ser desfeita.')) {
            return;
        }
        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates&id=' + id, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Roteiro excluído com sucesso!');
                table.ajax.reload();
            } else {
                alert('Erro ao excluir roteiro: ' + (data.error || 'Erro desconhecido.'));
            }
        })
        .catch(err => {
            console.error('Erro ao excluir:', err);
            alert('Erro de conexão com o servidor.');
        });
    });

    $('#formNovoTemplate').submit(function(e) {
        e.preventDefault();
        const data = {
            nome: $('input[name="nome"]').val(),
            tipo: $('select[name="tipo"]').val(),
            checklist: JSON.parse($('textarea[name="checklist"]').val())
        };

        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates', {
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
