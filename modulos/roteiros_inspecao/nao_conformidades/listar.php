<?php
$page_title = "Não Conformidades";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$isAdmin   = $userNivel <= NIVEL_GESTOR;
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <!-- Header / Hero -->
    <div class="gestor-hero">
        <div>
            <h1 class="mb-1"><i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--gm-coral);"></i>Não Conformidades</h1>
            <p class="mb-0">Acompanhe e resolva pendências identificadas nas inspeções técnicas.</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4" id="kpi-cards">
        <div class="col-6 col-xl-3">
            <div class="gm-kpi-card gm-kpi card h-100" style="background:linear-gradient(135deg,#ef7a67,#e4634f);">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center" style="background:rgba(255,255,255,.22); width:52px; height:52px; font-size:1.4rem; flex-shrink:0;">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                    <div>
                        <div class="small opacity-75">ABERTAS</div>
                        <div class="fs-3 fw-bold" id="kpi-abertas">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="gm-kpi-card gm-kpi card h-100" style="background:linear-gradient(135deg,#f0b95c,#eda24a);">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center" style="background:rgba(255,255,255,.22); width:52px; height:52px; font-size:1.4rem; flex-shrink:0;">
                        <i class="bi bi-arrow-clockwise"></i>
                    </div>
                    <div>
                        <div class="small opacity-75 text-dark">EM CORREÇÃO</div>
                        <div class="fs-3 fw-bold" id="kpi-em-correcao">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">RESOLVIDAS</div>
                        <div class="fs-3 fw-bold text-success" id="kpi-resolvidas">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-dark bg-opacity-10 p-3">
                        <i class="bi bi-clock-history text-dark fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">VENCIDAS</div>
                        <div class="fs-3 fw-bold" id="kpi-vencidas">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="gm-card mb-3">
        <div class="p-3">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 small fw-bold">Status:</label>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="filtro-status">
                        <option value="">Todos</option>
                        <option value="Aberta">Abertas</option>
                        <option value="Em Correção">Em Correção</option>
                        <option value="Resolvida">Resolvidas</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0 small fw-bold">Gravidade:</label>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" id="filtro-gravidade">
                        <option value="">Todas</option>
                        <option value="Baixa">Baixa</option>
                        <option value="Alta">Alta</option>
                        <option value="Critica">Crítica</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary" id="btn-limpar-filtros">
                        <i class="bi bi-x-circle"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="gm-card mb-5">
        <div class="card-head fw-bold">
            <i class="bi bi-table me-1" style="color:var(--gm-primary);"></i> Lista de Ocorrências
        </div>
        <div class="p-0">
            <div class="table-responsive">
            <table id="ncTable" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data Visita</th>
                        <?php if ($isAdmin): ?>
                        <th>Produtor</th>
                        <?php endif; ?>
                        <th>Propriedade</th>
                        <th>Descrição</th>
                        <th>Gravidade</th>
                        <th>Prazo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            </div>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<!-- Modal: Atualizar NC -->
<div class="modal fade" id="modalNC" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Atualizar Não Conformidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal-nc-body">
                <div class="text-center py-3"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<style>
.kpi-vencida { color: #dc3545 !important; }
</style>

<script>
const IS_ADMIN   = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const API_NC     = '<?php echo SITE_URL; ?>/api/nao_conformidades.php';
const SITE_URL_  = '<?php echo SITE_URL; ?>';

const STATUS_BADGE = {
    'Aberta':      '<span class="badge bg-danger">Aberta</span>',
    'Em Correção': '<span class="badge bg-warning text-dark">Em Correção</span>',
    'Resolvida':   '<span class="badge bg-success">Resolvida</span>',
};
const GRAVIDADE_BADGE = {
    'Baixa':   '<span class="badge bg-secondary">Baixa</span>',
    'Alta':    '<span class="badge bg-warning text-dark">Alta</span>',
    'Critica': '<span class="badge bg-danger">Crítica</span>',
};

let ncTable;

$(document).ready(function() {

    // Definir colunas dinamicamente
    const columns = [
        {
            data: 'data_visita',
            render: d => d ? new Date(d).toLocaleDateString('pt-BR') : '—'
        }
    ];
    if (IS_ADMIN) {
        columns.push({
            data: null,
            render: r => `${r.associado_nome} ${r.associado_sobrenome}`
        });
    }
    columns.push(
        { data: 'propriedade_nome' },
        {
            data: 'descricao',
            render: d => d && d.length > 60 ? d.substring(0, 60) + '…' : (d || '—')
        },
        {
            data: 'gravidade',
            render: d => GRAVIDADE_BADGE[d] || `<span class="badge bg-secondary">${d || '—'}</span>`
        },
        {
            data: 'prazo_correcao',
            render: (d, _, row) => {
                if (!d) return '<span class="text-muted">Sem prazo</span>';
                const dt     = new Date(d);
                const venceu = dt < new Date() && row.status !== 'Resolvida';
                const label  = dt.toLocaleDateString('pt-BR');
                return venceu ? `<span class="text-danger fw-bold"><i class="bi bi-clock"></i> ${label}</span>` : label;
            }
        },
        {
            data: 'status',
            render: d => STATUS_BADGE[d] || `<span class="badge bg-secondary">${d}</span>`
        },
        {
            data: null,
            render: row => {
                let btns = `<div class="btn-group">`;
                btns += `<a href="${SITE_URL_}/nao-conformidade/acompanhar?id=${row.id}"
                            class="btn btn-outline-primary btn-sm" title="Acompanhar Resolução">
                             <i class="bi bi-clock-history"></i> Acompanhar
                          </a>`;
                btns += `<a href="${SITE_URL_}/visita-tecnica/${row.visita_tecnica_id}/relatorio"
                            class="btn btn-outline-secondary btn-sm" title="Ver Visita">
                            <i class="bi bi-eye"></i> Visita
                         </a>`;
                btns += `</div>`;
                return btns;
            }
        }
    );

    // Inicializar DataTable
    ncTable = $('#ncTable').DataTable({
        ajax: {
            url: API_NC,
            dataSrc: json => {
                // Atualizar KPI cards
                const k = json.kpis || {};
                $('#kpi-abertas').text(k.abertas ?? 0);
                $('#kpi-em-correcao').text(k.em_correcao ?? 0);
                $('#kpi-resolvidas').text(k.resolvidas ?? 0);
                const vencidas = k.vencidas ?? 0;
                $('#kpi-vencidas').text(vencidas).toggleClass('kpi-vencida', vencidas > 0);
                return json.data || [];
            }
        },
        columns,
        order: [[0, 'desc']],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json' }
    });

    // Filtros
    $('#filtro-status, #filtro-gravidade').on('change', function() {
        ncTable.ajax.url(buildUrl()).load();
    });
    $('#btn-limpar-filtros').on('click', function() {
        $('#filtro-status, #filtro-gravidade').val('');
        ncTable.ajax.url(API_NC).load();
    });

    // Abrir modal de detalhes/edição
    $('#ncTable').on('click', '.btn-detalhe', function() {
        const id = $(this).data('id');
        $('#modal-nc-body').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#modalNC').modal('show');
        fetch(`${API_NC}?action=detail&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error);
                renderModal(data.data);
            })
            .catch(err => {
                $('#modal-nc-body').html(`<div class="alert alert-danger">${err.message}</div>`);
            });
    });

    // Salvar atualização
    $('#modal-nc-body').on('submit', '#formNC', function(e) {
        e.preventDefault();
        const payload = {};
        $(this).serializeArray().forEach(i => payload[i.name] = i.value);
        fetch(API_NC, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                $('#modalNC').modal('hide');
                ncTable.ajax.url(buildUrl()).load();
            } else {
                alert('Erro: ' + data.error);
            }
        });
    });
});

function buildUrl() {
    const st = $('#filtro-status').val();
    const gr = $('#filtro-gravidade').val();
    let url  = API_NC;
    const parts = [];
    if (st) parts.push(`status=${encodeURIComponent(st)}`);
    if (gr) parts.push(`gravidade=${encodeURIComponent(gr)}`);
    return parts.length ? url + '?' + parts.join('&') : url;
}

function renderModal(nc) {
    const readOnly = nc.status === 'Resolvida';
    const disabledAttr = readOnly ? 'disabled' : '';

    $('#modal-nc-body').html(`
        <div class="row mb-3">
            <div class="col-md-6">
                <p class="mb-1 text-muted small">VISITA</p>
                <p class="fw-bold">#${nc.visita_tecnica_id} — ${new Date(nc.data_visita).toLocaleDateString('pt-BR')}</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1 text-muted small">PROPRIEDADE / PRODUTOR</p>
                <p class="fw-bold">${nc.propriedade_nome} — ${nc.associado_nome} ${nc.associado_sobrenome}</p>
            </div>
        </div>
        <div class="card bg-light border-0 mb-3">
            <div class="card-body py-2">
                <div class="text-muted small mb-1">REQUISITO / NÃO CONFORMIDADE IDENTIFICADA:</div>
                <div class="fw-bold text-dark fs-6">${nc.descricao || nc.item_texto || 'Item #' + nc.item_checklist_id}</div>
            </div>
        </div>

        <form id="formNC">
            <input type="hidden" name="id" value="${nc.id}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Status</label>
                    <select class="form-select" name="status" ${disabledAttr}>
                        <option value="Aberta"      ${nc.status === 'Aberta'       ? 'selected' : ''}>Aberta</option>
                        <option value="Em Correção" ${nc.status === 'Em Correção'  ? 'selected' : ''}>Em Correção</option>
                        <option value="Resolvida"   ${nc.status === 'Resolvida'    ? 'selected' : ''}>Resolvida</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Prazo de Correção</label>
                    <input type="date" class="form-control" name="prazo_correcao"
                           value="${nc.prazo_correcao ? nc.prazo_correcao.split('T')[0] : ''}" ${disabledAttr}>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Responsável pela Correção</label>
                    <input type="text" class="form-control" name="responsavel_correcao"
                           value="${nc.responsavel_correcao || ''}" ${disabledAttr}
                           placeholder="Ex: Nome do Associado / Técnico">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small">Gravidade</label>
                    <select class="form-select" name="gravidade" ${disabledAttr}>
                        <option value="Baixa"   ${nc.gravidade === 'Baixa'   ? 'selected' : ''}>Baixa</option>
                        <option value="Alta"    ${nc.gravidade === 'Alta'    ? 'selected' : ''}>Alta</option>
                        <option value="Critica" ${nc.gravidade === 'Critica' ? 'selected' : ''}>Crítica</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small">Plano de Ação (Ação Corretiva)</label>
                    <textarea class="form-control" name="acao_corretiva" rows="2"
                              ${disabledAttr} placeholder="Descreva o que será feito para corrigir...">${nc.acao_corretiva || ''}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small">Observações Adicionais</label>
                    <textarea class="form-control" name="observacoes" rows="2"
                              ${disabledAttr} placeholder="Outras notas pertinentes...">${nc.observacoes || ''}</textarea>
                </div>
            </div>
            ${!readOnly ? `
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-save me-1"></i> SALVAR ALTERAÇÕES
                </button>
            </div>` : `
            <div class="alert alert-success mt-3 mb-0 d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                <div>
                    <strong>Não conformidade resolvida!</strong><br>
                    Verificado em ${nc.data_resolucao ? new Date(nc.data_resolucao).toLocaleDateString('pt-BR') : '—'}.
                </div>
            </div>`}
        </form>
    `);
}
</script>
