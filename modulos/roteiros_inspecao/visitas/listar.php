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
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
        <h1>Visitas Técnicas e Inspeções</h1>
        <?php if ($isAdmin): ?>
        <a href="<?php echo SITE_URL; ?>/visita-tecnica/agendar" class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i> Agendar Visita
        </a>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <i class="bi bi-list-check me-1"></i>
            <?php echo $isAdmin ? 'Cronograma de Inspeções (Todos os Associados)' : 'Minhas Visitas Técnicas'; ?>
        </div>
        <div class="card-body">
            <table id="visitasTable" class="table table-striped table-responsive-stack">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Propriedade</th>
                        <?php if ($isAdmin): ?>
                        <th>Agricultor</th>
                        <?php endif; ?>
                        <th>Roteiro</th>
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

<?php if ($isAdmin): ?>
<!-- Modal Agendar Visita — Apenas Admin/Gestor -->
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

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
const IS_ADMIN       = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const ASSOCIADO_SELF = <?php echo $associadoIdLogado ? (int)$associadoIdLogado : 'null'; ?>;

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
    $('#visitasTable').DataTable({
        ajax: {
            url: '<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=visitas',
            dataSrc: 'data'
        },
        columns: columns,
        responsive: true,
        columnDefs: [
            { responsivePriority: 1, targets: -1 }, // Ações — nunca ocultar
            { responsivePriority: 2, targets: 0 },  // Data
            { responsivePriority: 3, targets: 4 },  // Status
            { responsivePriority: 10, targets: 3 }, // Roteiro — ocultar primeiro
            { responsivePriority: 11, targets: 5 }  // Conformidade
        ],
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
});
</script>
