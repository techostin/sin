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

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-journal-text me-2" style="color:var(--gm-primary);"></i>Planos de Manejo Orgânico</h1>
                <p class="mb-0">Acompanhamento e análise dos planos de manejo por safra.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/plano-manejo/novo" class="btn btn-assoc-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Novo Plano
            </a>
        </div>
    </div>

    <div class="gm-card mb-4">
        <div class="card-head border-bottom-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-table me-1" style="color:var(--gm-primary);"></i> Lista de Planos por Safra</span>
            <div class="btn-group btn-group-sm" id="wizard-status-filtro">
                <button type="button" class="btn btn-outline-secondary active" data-filter="Todos">
                    Todos <span class="badge bg-secondary ms-1" id="count-todos">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-filter="Aprovado">
                    Aprovados <span class="badge bg-success ms-1" id="count-aprovados">0</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-filter="Em Análise">
                    Em Análise <span class="badge bg-info ms-1" id="count-analise">0</span>
                </button>
            </div>
        </div>
        <div class="p-3">
            <div class="table-responsive">
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

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<!-- Modal de Alteração Rápida de Status (somente para Gestor/Admin) -->
<?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
<div class="modal fade" id="modalAlterarStatus" tabindex="-1" aria-labelledby="modalStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title" id="modalStatusLabel"><i class="bi bi-arrow-repeat me-1"></i>Alterar Status</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="status-plano-id">
                <p class="small text-muted mb-2">Status atual: <span id="status-atual-label" class="fw-bold"></span></p>
                <label class="form-label small">Novo Status</label>
                <select class="form-select" id="novo-status-select">
                    <option value="Rascunho">Rascunho</option>
                    <option value="Em Análise">Em Análise</option>
                    <option value="Aprovado">Aprovado</option>
                    <option value="Reprovado">Reprovado</option>
                    <option value="Revisão Necessária">Revisão Necessária</option>
                </select>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmarStatus">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    function normalizeStatusLabel(value) {
        const raw = (value || '').toString().trim();
        if (!raw) return '';
        const map = {
            'em análise': 'Em Análise',
            'em analise': 'Em Análise',
            'em-analise': 'Em Análise',
            'em_analise': 'Em Análise',
            'emanalise': 'Em Análise',
            'aprovado': 'Aprovado',
            'reprovado': 'Reprovado',
            'rascunho': 'Rascunho',
            'revisao necessaria': 'Revisão Necessária',
            'revisão necessária': 'Revisão Necessária',
            'revisao-necessaria': 'Revisão Necessária',
            'revisao_necessaria': 'Revisão Necessária'
        };
        const key = raw.toLowerCase().replace(/[_-]+/g, ' ').trim();
        return map[key] || raw;
    }

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
                    const statusLabel = normalizeStatusLabel(data);
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
                    return `<span class="badge bg-${badges[statusLabel] || 'secondary'}">${statusLabel}</span>`;
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
                    const statusNorm = normalizeStatusLabel(row.status);
                    const btnStatus = <?php echo $user->hasPermission(NIVEL_GESTOR) ? 'true' : 'false'; ?>
                        ? `<button class="btn btn-outline-info btn-sm btn-alterar-status" 
                               data-id="${row.id}" 
                               data-status="${statusNorm}" 
                               title="Alterar Status">
                              <i class="bi bi-arrow-repeat"></i>
                           </button>`
                        : '';
                    return `
                        <div class="btn-group">
                            <a href="<?php echo SITE_URL; ?>/${baseUrl}/${row.id}/visualizar" class="btn btn-outline-primary btn-sm" title="Visualizar"><i class="bi bi-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/${baseUrl}/${row.id}/editar" class="btn btn-outline-secondary btn-sm" title="Editar"><i class="bi bi-pencil"></i></a>
                            ${btnStatus}
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
    function atualizarContadores() {
        const dados = table.rows({ search: 'none' }).data().toArray();
        const total = dados.length;
        const aprovados = dados.filter(r => normalizeStatusLabel(r.status) === 'Aprovado').length;
        const emAnalise = dados.filter(r => normalizeStatusLabel(r.status) === 'Em Análise').length;

        $('#count-todos').text(total);
        $('#count-aprovados').text(aprovados);
        $('#count-analise').text(emAnalise);
    }

    table.on('xhr', function() {
        atualizarContadores();
    });

    // Filtros de status (wizard)
    $('#wizard-status-filtro .btn').on('click', function() {
        const filter = $(this).data('filter');
        $('#wizard-status-filtro .btn').removeClass('active');
        $(this).addClass('active');
        
        if (filter === 'Todos') {
            table.column(4).search('').draw();
        } else if (filter === 'Aprovado') {
            table.column(4).search('^Aprovado$', true, false).draw();
        } else if (filter === 'Em Análise') {
            table.column(4).search('^Em Análise$', true, false).draw();
        } else {
            table.column(4).search(filter).draw();
        }
    });

    // === Alteração rápida de status (somente Gestor/Admin) ===
    $(document).on('click', '.btn-alterar-status', function() {
        const id     = $(this).data('id');
        const status = $(this).data('status');
        $('#status-plano-id').val(id);
        $('#status-atual-label').text(status);
        $('#novo-status-select').val(status);
        $('#modalAlterarStatus').modal('show');
    });

    $('#btnConfirmarStatus').on('click', function() {
        const id         = $('#status-plano-id').val();
        const novoStatus = $('#novo-status-select').val();

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        fetch('<?php echo SITE_URL; ?>/api/plano_manejo.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id), status: novoStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                $('#modalAlterarStatus').modal('hide');
                table.ajax.reload(null, false); // false = mantém paginação atual
            } else {
                alert(data.error || 'Erro ao atualizar status');
            }
        })
        .catch(() => alert('Erro de comunicação com a API'))
        .finally(() => {
            $('#btnConfirmarStatus').prop('disabled', false).html('<i class="bi bi-check-lg"></i> Confirmar');
        });
    });
});
</script>
