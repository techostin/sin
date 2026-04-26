<?php
$page_title = "Certificações Participativas";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Permitir acesso a todos os usuários autenticados
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<h1 class="mt-4">Certificações Participativas</h1>
<p>Gestão de certificações orgânicas SPG/OCS conforme Portaria MAPA 52/2021</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Certificações</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/certificacao/novo" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle"></i> Nova Certificação
            </a>
            <a href="<?php echo SITE_URL; ?>/" class="btn btn-secondary btn-sm ms-2">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-md-3">
                <label>Tipo</label>
                <select id="filtroTipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="SPG">SPG</option>
                    <option value="OCS">OCS</option>
                    <option value="Rede Ecovida">Rede Ecovida</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Status</label>
                <select id="filtroStatus" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="Ativa">Ativa</option>
                    <option value="Em Conversão">Em Conversão</option>
                    <option value="Vencida">Vencida</option>
                    <option value="Suspensa">Suspensa</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>Validade</label>
                <select id="filtroValidade" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="proximas">Vence em 30 dias</option>
                    <option value="vencidas">Vencidas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <button id="btnAplicarFiltros" class="btn btn-primary btn-sm d-block">
                    <i class="bi bi-filter"></i> Aplicar Filtros
                </button>
            </div>
        </div>

        <!-- Tabela -->
        <table id="certificacoesTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Nº Certificado</th>
                    <th>Associado</th>
                    <th>Status</th>
                    <th>Emissão</th>
                    <th>Validade</th>
                    <th>Organismo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Carregado via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Visualização -->
<div class="modal fade" id="modalVisualizarCertificacao" tabindex="-1"
    aria-labelledby="modalVisualizarCertificacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarCertificacaoLabel">Detalhes da Certificação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalVisualizarCertificacaoBody">
                <!-- Carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<!-- DataTables CSS e JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css"
    href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script type="text/javascript"
    src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script type="text/javascript"
    src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

<script>
    let table;

    $(document).ready(function () {
        // Inicializar DataTable
        table = $('#certificacoesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '<?php echo SITE_URL; ?>/api/certificacao/controller.php',
                type: 'GET',
                dataSrc: function (json) {
                    return json.data || [];
                },
                error: function (xhr, error, thrown) {
                    console.error('Erro ao carregar certificações:', error);
                    alert('Erro ao carregar certificações. Verifique o console.');
                }
            },
            columns: [
                { data: 'id', width: '50px', className: 'text-center' },
                {
                    data: 'tipo_certificacao',
                    width: '80px',
                    render: function (data) {
                        const badges = {
                            'SPG': 'primary',
                            'OCS': 'success',
                            'Rede Ecovida': 'info',
                            'Outro': 'secondary'
                        };
                        return `<span class="badge bg-${badges[data] || 'secondary'}">${data}</span>`;
                    }
                },
                {
                    data: 'numero_certificado',
                    render: function (data) {
                        return data || '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: null,
                    render: function (row) {
                        return `${row.associado_nome || ''} ${row.associado_sobrenome || ''}`;
                    }
                },
                {
                    data: 'status',
                    width: '100px',
                    render: function (data) {
                        const badges = {
                            'Ativa': 'success',
                            'Em Conversão': 'warning',
                            'Vencida': 'danger',
                            'Suspensa': 'secondary',
                            'Cancelada': 'dark'
                        };
                        return `<span class="badge bg-${badges[data] || 'secondary'}">${data}</span>`;
                    }
                },
                {
                    data: 'data_emissao',
                    width: '100px',
                    render: function (data) {
                        return data ? new Date(data).toLocaleDateString('pt-BR') : '-';
                    }
                },
                {
                    data: 'data_validade',
                    width: '100px',
                    render: function (data) {
                        if (!data) return '-';

                        const validade = new Date(data);
                        const hoje = new Date();
                        const diff = Math.ceil((validade - hoje) / (1000 * 60 * 60 * 24));

                        let classe = 'text-success';
                        if (diff < 0) classe = 'text-danger';
                        else if (diff <= 30) classe = 'text-warning';

                        return `<span class="${classe}">${validade.toLocaleDateString('pt-BR')}</span>`;
                    }
                },
                {
                    data: 'organismo_certificador',
                    render: function (data) {
                        return data || '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: null,
                    width: '120px',
                    className: 'text-center',
                    orderable: false,
                    render: function (row) {
                        return `
                        <div class="btn-group" role="group">
                            <a href="<?php echo SITE_URL; ?>/certificacao/${row.id}/editar" 
                               class="btn btn-outline-primary btn-sm" 
                               title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/certificacao/${row.id}/renovar" 
                               class="btn btn-outline-success btn-sm" 
                               title="Renovar">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>
                            <a href="<?php echo SITE_URL; ?>/certificacao/historico/${row.associado_id}" 
                               class="btn btn-outline-secondary btn-sm" 
                               title="Ver Histórico">
                                <i class="bi bi-clock-history"></i>
                            </a>
                            <button type="button" 
                                    class="btn btn-outline-info btn-sm" 
                                    onclick="visualizarCertificacao(${row.id})"
                                    title="Ver Detalhes">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" 
                                    class="btn btn-outline-danger btn-sm" 
                                    onclick="excluirCertificacao(${row.id})"
                                    title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    }
                }
            ],
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
            },
            order: [[6, 'desc']], // Ordenar por validade
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
        });

        // Aplicar filtros
        $('#btnAplicarFiltros').on('click', function () {
            const tipo = $('#filtroTipo').val();
            const status = $('#filtroStatus').val();
            const validade = $('#filtroValidade').val();

            let url = '<?php echo SITE_URL; ?>/api/certificacao/controller.php?';

            if (tipo) url += `tipo_certificacao=${tipo}&`;
            if (status) url += `status=${status}&`;
            if (validade) url += `validade=${validade}&`;

            table.ajax.url(url).load();
        });
    });

    function visualizarCertificacao(id) {
        // Loading
        $('#modalVisualizarCertificacaoBody').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2">Carregando detalhes...</p>
        </div>
    `);

        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('modalVisualizarCertificacao'));
        modal.show();

        // Buscar dados
        fetch(`<?php echo SITE_URL; ?>/api/certificacao/controller.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tipo:</strong> <span class="badge bg-primary">${data.tipo_certificacao}</span></p>
                        <p><strong>Nº Certificado:</strong> ${data.numero_certificado || '-'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-success">${data.status}</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Data Emissão:</strong> ${data.data_emissao ? new Date(data.data_emissao).toLocaleDateString('pt-BR') : '-'}</p>
                        <p><strong>Data Validade:</strong> ${data.data_validade ? new Date(data.data_validade).toLocaleDateString('pt-BR') : '-'}</p>
                        <p><strong>Organismo:</strong> ${data.organismo_certificador || '-'}</p>
                    </div>
                </div>
                <hr>
                <p><strong>Associado:</strong> ${data.associado_nome} ${data.associado_sobrenome}</p>
                ${data.nome_grupo ? `<p><strong>Grupo:</strong> ${data.nome_grupo}</p>` : ''}
                ${data.observacoes ? `<hr><p><strong>Observações:</strong><br>${data.observacoes}</p>` : ''}
            `;

                $('#modalVisualizarCertificacaoBody').html(html);
            })
            .catch(error => {
                console.error('Erro:', error);
                $('#modalVisualizarCertificacaoBody').html(`
                <div class="alert alert-danger">
                    Erro ao carregar dados. Tente novamente.
                </div>
            `);
            });
    }

    function excluirCertificacao(id) {
        if (!confirm('Tem certeza que deseja excluir esta certificação?')) {
            return;
        }

        fetch(`<?php echo SITE_URL; ?>/api/certificacao/controller.php?id=${id}`, {
            method: 'DELETE'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Certificação excluída com sucesso!');
                    table.ajax.reload();
                } else {
                    alert('Erro ao excluir: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir certificação');
            });
    }
</script>