<?php
$page_title = "Visitas Técnicas / Inspeções";
$fab_action = SITE_URL . '/visita-tecnica/agendar';
$fab_label  = 'Agendar Visita';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Controle de acesso no PHP para adaptar a view
$userNivel      = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$userTable      = $_SESSION['user_table'] ?? 'associados';
$isAdmin        = $userNivel <= NIVEL_GESTOR;        // admin (1) ou gestor (2)
$isUsuarioComum = !$isAdmin && $userTable === 'associados';
$associadoIdLogado = $isUsuarioComum ? ($_SESSION['user_id'] ?? null) : null;
$podeReagendar  = $isAdmin || $userTable === 'associados';  // admin/gestor ou técnico (associado)
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-clipboard2-pulse me-2" style="color:var(--gm-primary);"></i>Visitas Técnicas e Inspeções</h1>
                <p class="mb-0"><?php echo $isAdmin ? 'Cronograma de inspeções de todos os associados.' : 'Suas visitas técnicas realizadas e agendadas.'; ?></p>
            </div>
            <?php if ($isAdmin): ?>
            <a href="<?php echo SITE_URL; ?>/visita-tecnica/agendar" class="btn btn-assoc-primary btn-sm">
                <i class="bi bi-calendar-plus me-1"></i> Agendar Visita
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="gm-card mb-4">
        <div class="card-head">
            <i class="bi bi-list-check me-1" style="color:var(--gm-primary);"></i>
            <?php echo $isAdmin ? 'Cronograma de Inspeções (Todos os Associados)' : 'Minhas Visitas Técnicas'; ?>
        </div>
        <div class="p-3">
            <div class="table-responsive">
            <table id="visitasTable" class="table table-striped table-responsive-stack">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Propriedade</th>
                        <?php if ($isAdmin): ?>
                        <th>Agricultor</th>
                        <?php endif; ?>
                        <th>Roteiro</th>
                        <?php if ($isAdmin): ?>
                        <th>Ciente</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Conformidade</th>
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
</div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<!-- Modal Agendar Visita — Apenas Admin/Gestor -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="modalAgendarVisita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agendar Nova Inspeção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAgendarVisita">
                    <div class="mb-3">
                        <label class="form-label">Agricultor</label>
                        <select class="form-select" name="associado_id" id="select_associado" required>
                            <option value="">Selecione o Agricultor...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Propriedade</label>
                        <select class="form-select" name="propriedade_id" id="select_propriedade" required>
                            <option value="">Selecione primeiro o Agricultor</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Roteiro / Template</label>
                        <select class="form-select" name="roteiro_id" id="select_roteiro" required>
                            <option value="">Carregando roteiros...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data da Visita</label>
                        <input type="date" class="form-control" name="data_visita" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Agendar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($podeReagendar): ?>
<!-- Modal Reagendar Visita — Admin/Gestor/Técnico -->
<div class="modal fade" id="modalReagendarVisita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f9a825,#f57c00);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Reagendar Visita / Inspeção</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reagendarResumo" class="alert alert-light border mb-3 small"></div>
                <form id="formReagendarVisita">
                    <input type="hidden" name="visita_id" id="reagendar_visita_id">
                    <div class="mb-3">
                        <label class="form-label">Nova Data da Visita *</label>
                        <input type="date" class="form-control" name="data_visita" id="reagendar_data" required>
                        <div class="form-text">A data deve ser hoje ou futura. O agricultor será notificado novamente.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova Hora (opcional)</label>
                        <input type="time" class="form-control" name="hora_inicio" id="reagendar_hora">
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold" id="btn-reagendar">
                        <i class="bi bi-calendar-check me-1"></i> Confirmar Reagendamento
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
const IS_ADMIN       = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const ASSOCIADO_SELF = <?php echo $associadoIdLogado ? (int)$associadoIdLogado : 'null'; ?>;
const PODE_REAGENDAR = <?php echo $podeReagendar ? 'true' : 'false'; ?>;

$(document).ready(function() {

    // ----- Definir colunas dinamicamente conforme perfil -----
    const columns = [
        {
            data: 'data_visita',
            render: d => new Date(d).toLocaleDateString('pt-BR')
        },
        { data: 'propriedade_nome' }
    ];

    // Coluna Agricultor: apenas para admin/gestor
    if (IS_ADMIN) {
        columns.push({
            data: null,
            render: row => `${row.associado_nome} ${row.associado_sobrenome}`
        });
    }

    // Coluna "Ciente" (confirmação do agricultor sobre o agendamento — SPG)
    if (IS_ADMIN) {
        columns.push({
            data: null,
            render: row => {
                if (row.status !== 'Agendada') {
                    return '<span class="text-muted">—</span>';
                }
                if (row.ciente) {
                    return '<span class="badge bg-success" title="Confirmado em ' + (row.ciente_em || '') + '"><i class="bi bi-check-circle me-1"></i>Confirmado</span>';
                }
                if (row.ciente_recusada) {
                    return '<span class="badge bg-danger" title="Recusou em ' + (row.ciente_recusada_em || '') + ' — Motivo: ' + (row.ciente_recusada_motivo || 'não informado') + '"><i class="bi bi-calendar-x me-1"></i>Recusou / Reagendar</span>';
                }
                return '<span class="badge bg-warning text-dark" title="Agricultor ainda não confirmou ciência do agendamento"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>';
            }
        });
    }

    columns.push(
        { data: 'roteiro_nome' },
        {
            data: 'status',
            render: data => {
                const cls = { 'Agendada': 'info', 'Realizada': 'success', 'Cancelada': 'danger' };
                return `<span class="badge bg-${cls[data] || 'secondary'}">${data}</span>`;
            }
        },
        {
            data: 'conformidade',
            render: data => data || '<span class="text-muted">—</span>'
        },
        {
            data: null,
            render: row => {
                let btns = '<div class="btn-group">';
                if (row.status === 'Agendada' && IS_ADMIN) {
                    const hid = row.hashid || row.id;
                    btns += `<a href="${SITE_URL}/visita-tecnica/${hid}/realizar"
                                class="btn btn-outline-primary btn-sm" title="Realizar Inspeção">
                                <i class="bi bi-clipboard-check"></i> Realizar</a>`;
                }
                const podeReagendarRow =
                    IS_ADMIN ||
                    (PODE_REAGENDAR && ASSOCIADO_SELF && row.tecnico_responsavel_id == ASSOCIADO_SELF);
                if (row.status === 'Agendada' && podeReagendarRow) {
                    btns += `<button type="button" class="btn btn-outline-warning btn-sm btn-reagendar"
                                data-id="${row.id}" data-data="${row.data_visita}" data-hora="${row.hora_inicio || ''}"
                                data-associado="${row.associado_nome} ${row.associado_sobrenome || ''}"
                                data-propriedade="${row.propriedade_nome}" title="Reagendar visita">
                                <i class="bi bi-calendar-event"></i> Reagendar</button>`;
                }
                if (row.status === 'Realizada') {
                    const hid = row.hashid || row.id;
                    btns += `<a href="${SITE_URL}/visita-tecnica/${hid}/relatorio"
                                class="btn btn-outline-success btn-sm" title="Ver Detalhes">
                                <i class="bi bi-eye"></i> Ver</a>`;
                    btns += `<a href="${SITE_URL}/api/roteiros_inspecao.php?action=relatorio&id=${row.id}"
                                target="_blank" class="btn btn-outline-danger btn-sm" title="Download PDF">
                                <i class="bi bi-file-earmark-pdf"></i></a>`;
                }
                btns += '</div>';
                return btns;
            }
        }
    );

    // ----- DataTable -----
    const columnDefs = [
        { responsivePriority: 1, targets: -1 }, // Ações — nunca ocultar
        { responsivePriority: 2, targets: 0 },  // Data
        { responsivePriority: 3, targets: IS_ADMIN ? 5 : 3 },  // Status
        { responsivePriority: 10, targets: IS_ADMIN ? 4 : 2 }, // Roteiro — ocultar primeiro
        { responsivePriority: 11, targets: IS_ADMIN ? 6 : 4 }  // Conformidade
    ];
    if (IS_ADMIN) {
        columnDefs.push({ responsivePriority: 12, targets: 3 }); // Ciente — ocultar antes
    }

    $('#visitasTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=visitas',
            dataSrc: 'data'
        },
        columns: columns,
        responsive: true,
        columnDefs: columnDefs,
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' }
    });

    // ----- Modal (apenas admin/gestor acessa o HTML, então os selects existem) -----
    if (IS_ADMIN) {

        // Carregar Roteiros
        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates')
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(data => {
                const sel = $('#select_roteiro');
                sel.empty().append('<option value="">Selecione o Roteiro...</option>');
                (data.data || []).forEach(t => sel.append(`<option value="${t.id}">${t.nome}</option>`));
            })
            .catch(err => console.error('Roteiros:', err));

        // Carregar Associados
        fetch('<?php echo SITE_URL; ?>/api/associados/listar.php')
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(data => {
                const sel = $('#select_associado');
                sel.empty().append('<option value="">Selecione o Agricultor...</option>');
                (data.data || []).forEach(a => sel.append(`<option value="${a.id}">${a.nome}</option>`));
            })
            .catch(err => console.error('Associados:', err));

        // Carregar Propriedades ao mudar agricultor
        $('#select_associado').change(function() {
            const aid = $(this).val();
            const selP = $('#select_propriedade');
            if (!aid) { selP.empty().append('<option value="">Selecione primeiro o Agricultor</option>'); return; }
            selP.empty().append('<option value="">Carregando...</option>');
            fetch(`<?php echo SITE_URL; ?>/api/get_propriedades_associado.php?associado_id=${aid}`)
                .then(r => r.json())
                .then(data => {
                    selP.empty().append('<option value="">Selecione a Propriedade</option>');
                    const list = Array.isArray(data) ? data : [];
                    list.length
                        ? list.forEach(p => selP.append(`<option value="${p.id}">${p.nome}</option>`))
                        : selP.append('<option disabled>Nenhuma propriedade encontrada</option>');
                })
                .catch(() => selP.empty().append('<option disabled>Erro ao carregar</option>'));
        });

        // Submeter agendamento
        $('#formAgendarVisita').submit(function(e) {
            e.preventDefault();
            const payload = {};
            $(this).serializeArray().forEach(i => payload[i.name] = i.value);
            fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=visitas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    $('#modalAgendarVisita').modal('hide');
                    location.reload();
                } else {
                    alert('Erro ao agendar: ' + (data.error || ''));
                }
            });
        });
    }

    // ----- Reagendar visita (admin/gestor/técnico) -----
    if (PODE_REAGENDAR) {
        $('#visitasTable').on('click', '.btn-reagendar', function() {
            const id         = $(this).data('id');
            const dataVisita = $(this).data('data');
            const hora       = $(this).data('hora') || '';
            const associado  = $(this).data('associado') || '';
            const propriedade= $(this).data('propriedade') || '';

            $('#reagendar_visita_id').val(id);
            $('#reagendar_data').val(dataVisita).attr('min', new Date().toISOString().slice(0,10));
            $('#reagendar_hora').val(hora ? String(hora).slice(0,5) : '');
            $('#reagendarResumo').html(
                '<strong>Agricultor:</strong> ' + $('<span>').text(associado).html() + '<br>' +
                '<strong>Propriedade:</strong> ' + $('<span>').text(propriedade).html()
            );
            $('#modalReagendarVisita').modal('show');
        });

        // Submeter reagendamento
        $('#formReagendarVisita').submit(function(e) {
            e.preventDefault();
            const payload = {};
            $(this).serializeArray().forEach(i => payload[i.name] = i.value);
            const btn = $('#btn-reagendar');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Reagendando...');

            fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=reagendar_visita', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    $('#modalReagendarVisita').modal('hide');
                    location.reload();
                } else {
                    btn.prop('disabled', false).html('<i class="bi bi-calendar-check me-1"></i> Confirmar Reagendamento');
                    alert('Erro ao reagendar: ' + (data.error || 'Erro interno.'));
                }
            })
            .catch(() => {
                btn.prop('disabled', false).html('<i class="bi bi-calendar-check me-1"></i> Confirmar Reagendamento');
                alert('Erro de comunicação com o servidor.');
            });
        });
    }
});
</script>
