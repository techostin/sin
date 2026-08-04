<?php
$page_title = "Ocorrências de Manejo";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$isGestor = $user->hasPermission(NIVEL_GESTOR);
$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;

// Carregar dados via PHP como fallback
require_once __DIR__ . '/../../../classes/Ocorrencia.php';
$ocorrenciaObj = new Ocorrencia();
if ($isGestor) {
    $ocorrencias_data = $ocorrenciaObj->getAll();
} else {
    $assoc_id_fallback = $user->getAssociadoId();
    $ocorrencias_data = $assoc_id_fallback ? $ocorrenciaObj->getAllByAssociadoId($assoc_id_fallback) : [];
}
$ocorrencias_json = json_encode($ocorrencias_data, JSON_UNESCAPED_UNICODE);
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Ocorrências de Manejo</h1>
            <p class="text-muted mb-0">Registro de pragas, doenças e eventos fitossanitários nas propriedades.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="exportarRelatorio('pdf')"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportarRelatorio('excel')"><i class="bi bi-file-earmark-excel text-success"></i> Excel</a></li>
                </ul>
            </div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nova Ocorrência
            </a>
        </div>
    </div>

    <?php if ($isGestor): ?>
    <!-- Filtros rápidos para Gestor/Admin -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>Filtrar por Status:</span>
                </div>
                <div class="col-auto">
                    <div class="btn-group btn-group-sm" id="statusFilterGroup">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="">Todos</button>
                        <button type="button" class="btn btn-outline-warning" data-filter="Aberta">Aberta</button>
                        <button type="button" class="btn btn-outline-info" data-filter="Em Correção">Em Correção</button>
                        <button type="button" class="btn btn-outline-success" data-filter="Resolvida">Resolvida</button>
                        <button type="button" class="btn btn-outline-dark" data-filter="Fechada">Fechada</button>
                    </div>
                </div>
                <div class="col-auto ms-auto">
                    <span class="small text-muted" id="totalRegistros"></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="ocorrenciasTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <?php if ($isGestor): ?>
                            <th>Produtor</th>
                            <?php endif; ?>
                            <th>Tipo</th>
                            <th>Nome Comum</th>
                            <th>Propriedade</th>
                            <th>Lavoura</th>
                            <th>Data Detecção</th>
                            <th>Severidade</th>
                            <th>Status</th>
                            <th style="width: 180px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Carregado via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Correção (Usuário comum pode informar correções) -->
<?php if (!$isGestor): ?>
<div class="modal fade" id="modalCorrecao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tools text-warning me-2"></i>Informar Correções Realizadas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCorrecao">
                <div class="modal-body">
                    <input type="hidden" id="correcao_id" name="id">
                    <div class="mb-3">
                        <label class="form-label">Descrição das Correções</label>
                        <textarea class="form-control" id="correcao_texto" name="correcoes_realizadas" rows="5" required placeholder="Descreva as ações corretivas realizadas para resolver esta ocorrência..."></textarea>
                        <div class="form-text">Ao informar as correções, o status será alterado para "Em Correção".</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salvar Correções
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal de Status (Gestor/Admin altera status) -->
<?php if ($isGestor): ?>
<div class="modal fade" id="modalStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i>Alterar Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formStatus">
                <div class="modal-body">
                    <input type="hidden" id="status_id" name="id">
                    <div class="mb-3">
                        <label class="form-label small">Novo Status</label>
                        <select class="form-select" id="novo_status" name="status">
                            <option value="Aberta">Aberta</option>
                            <option value="Em Correção">Em Correção</option>
                            <option value="Resolvida">Resolvida</option>
                            <option value="Fechada">Fechada</option>
                        </select>
                    </div>
                    <?php if ($userNivel == NIVEL_ADMIN): ?>
                    <div class="mb-3">
                        <label class="form-label small">Correções Realizadas (opcional)</label>
                        <textarea class="form-control" name="correcoes_realizadas" rows="3" placeholder="Registrar correções realizadas..."></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg"></i> Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const IS_GESTOR = <?php echo $isGestor ? 'true' : 'false'; ?>;
    const SITE_URL = '<?php echo SITE_URL; ?>';

    // Definir colunas dinamicamente
    const columns = [
        { data: 'id', width: '60px' }
    ];

    if (IS_GESTOR) {
        columns.push({
            data: null,
            render: function(row) {
                return (row.associado_nome || '') + ' ' + (row.associado_sobrenome || '');
            }
        });
    }

    columns.push(
        { data: 'tipo' },
        { data: 'nome_comum' },
        { data: 'propriedade' },
        {
            data: 'lavoura_nome',
            render: function(data) {
                return data || '<span class="text-muted">—</span>';
            }
        },
        {
            data: 'data_deteccao',
            render: function(data) {
                return data ? new Date(data).toLocaleDateString('pt-BR') : '-';
            }
        },
        {
            data: 'severidade',
            render: function(data) {
                const badges = { 'Baixa': 'success', 'Média': 'warning', 'Alta': 'danger' };
                return `<span class="badge bg-${badges[data] || 'secondary'}">${data}</span>`;
            }
        },
        {
            data: 'status',
            render: function(data) {
                const badges = {
                    'Aberta': 'warning',
                    'Em Correção': 'info',
                    'Resolvida': 'success',
                    'Fechada': 'dark'
                };
                return `<span class="badge bg-${badges[data] || 'secondary'}">${data || 'Aberta'}</span>`;
            }
        },
        {
            data: null,
            orderable: false,
            render: function(row) {
                let btns = '<div class="btn-group btn-group-sm">';

                // Botão visualizar detalhes
                btns += `<button class="btn btn-outline-info" onclick="verDetalhes(${row.id})" title="Ver detalhes"><i class="bi bi-eye"></i></button>`;

                // Gestor/Admin: pode editar e alterar status
                if (IS_GESTOR) {
                    btns += `<a href="${SITE_URL}/caderno-campo/ocorrencia/${row.id}/editar" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>`;
                    btns += `<button class="btn btn-outline-secondary" onclick="alterarStatus(${row.id}, '${row.status || 'Aberta'}')" title="Alterar Status"><i class="bi bi-arrow-repeat"></i></button>`;
                    btns += `<button class="btn btn-outline-danger" onclick="excluirOcorrencia(${row.id})" title="Excluir"><i class="bi bi-trash"></i></button>`;
                } else {
                    // Usuário comum: pode informar correções se estiver Aberta ou Em Correção
                    const status = (row.status || 'Aberta');
                    if (status === 'Aberta' || status === 'Em Correção') {
                        btns += `<button class="btn btn-outline-warning" onclick="informarCorrecao(${row.id})" title="Informar Correções"><i class="bi bi-tools"></i></button>`;
                    }
                    if (row.correcoes_realizadas) {
                        btns += `<button class="btn btn-outline-success" onclick="verCorrecoes(${row.id})" title="Ver correções realizadas"><i class="bi bi-check-circle"></i></button>`;
                    }
                }

                btns += '</div>';
                return btns;
            }
        }
    );

    // Dados carregados via PHP (fallback mais confiável que API)
    const ocorrenciasData = <?php echo $ocorrencias_json; ?>;

    // Inicializar DataTable
    const table = $('#ocorrenciasTable').DataTable({
        data: ocorrenciasData,
        columns: columns,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        drawCallback: function() {
            const info = this.api().page.info();
            $('#totalRegistros').text('Total: ' + info.recordsTotal + ' registros');
        }
    });

    // Filtro de status (Gestor)
    if (IS_GESTOR) {
        $('#statusFilterGroup .btn').on('click', function() {
            $('#statusFilterGroup .btn').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            if (filter) {
                table.column(8).search('^' + filter + '$', true, false).draw();
            } else {
                table.column(8).search('').draw();
            }
        });
    }

    // Form de Correção (Usuário comum)
    $('#formCorrecao').on('submit', function(e) {
        e.preventDefault();
        const id = $('#correcao_id').val();
        const texto = $('#correcao_texto').val();

        $.ajax({
            url: SITE_URL + '/api/ocorrencias.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify({ id: parseInt(id), correcoes_realizadas: texto }),
            success: function(resp) {
                if (resp.success) {
                    $('#modalCorrecao').modal('hide');
                    alert('Correções registradas com sucesso!');
                    window.location.reload();
                } else {
                    alert('Erro: ' + (resp.error || 'Erro desconhecido'));
                }
            },
            error: function() {
                alert('Erro de comunicação com o servidor.');
            }
        });
    });

    // Form de Status (Gestor/Admin)
    $('#formStatus').on('submit', function(e) {
        e.preventDefault();
        const id = $('#status_id').val();
        const status = $('#novo_status').val();
        const correcoes = $(this).find('textarea[name="correcoes_realizadas"]').val() || '';

        const data = { id: parseInt(id), status: status };
        if (correcoes) {
            data.correcoes_realizadas = correcoes;
        }

        $.ajax({
            url: SITE_URL + '/api/ocorrencias.php',
            method: 'PATCH',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(resp) {
                if (resp.success) {
                    $('#modalStatus').modal('hide');
                    window.location.reload();
                } else {
                    alert('Erro: ' + (resp.error || 'Erro desconhecido'));
                }
            },
            error: function() {
                alert('Erro de comunicação com o servidor.');
            }
        });
    });

    // Exportar funções para escopo global
    window.verDetalhes = function(id) {
        const rowData = table.row(function(idx, data) {
            return data.id === id;
        }).data();

        if (!rowData) return;

        let html = '<div class="row">';
        html += '<div class="col-md-6"><p><strong>Tipo:</strong> ' + rowData.tipo + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Nome Comum:</strong> ' + rowData.nome_comum + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Propriedade:</strong> ' + (rowData.propriedade || '-') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Lavoura:</strong> ' + (rowData.lavoura_nome || '-') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Data Detecção:</strong> ' + (rowData.data_deteccao ? new Date(rowData.data_deteccao).toLocaleDateString('pt-BR') : '-') + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Severidade:</strong> ' + rowData.severidade + '</p></div>';
        html += '<div class="col-md-6"><p><strong>Status:</strong> ' + (rowData.status || 'Aberta') + '</p></div>';

        if (IS_GESTOR) {
            html += '<div class="col-md-6"><p><strong>Produtor:</strong> ' + (rowData.associado_nome || '') + ' ' + (rowData.associado_sobrenome || '') + '</p></div>';
        }

        if (rowData.descricao) {
            html += '<div class="col-12 mt-2"><p><strong>Descrição:</strong><br>' + rowData.descricao + '</p></div>';
        }
        if (rowData.acao_manejo) {
            html += '<div class="col-12 mt-2"><p><strong>Ação de Manejo:</strong><br>' + rowData.acao_manejo + '</p></div>';
        }
        if (rowData.correcoes_realizadas) {
            html += '<div class="col-12 mt-2"><p><strong>Correções Realizadas:</strong><br>' + rowData.correcoes_realizadas + '</p></div>';
        }
        if (rowData.foto_url) {
            html += '<div class="col-12 mt-2"><p><strong>Foto:</strong><br><img src="' + SITE_URL + '/' + rowData.foto_url + '" style="max-width:300px" class="img-thumbnail"></p></div>';
        }

        html += '</div>';

        const modal = new bootstrap.Modal(document.getElementById('modalDetalhesOcorrencia'));
        document.getElementById('modalDetalhesBody').innerHTML = html;
        modal.show();
    };

    window.informarCorrecao = function(id) {
        $('#correcao_id').val(id);
        $('#correcao_texto').val('');
        $('#modalCorrecao').modal('show');
    };

    window.alterarStatus = function(id, statusAtual) {
        $('#status_id').val(id);
        $('#novo_status').val(statusAtual || 'Aberta');
        $('#formStatus textarea[name="correcoes_realizadas"]').val('');
        $('#modalStatus').modal('show');
    };

    window.excluirOcorrencia = function(id) {
        if (!confirm('Tem certeza que deseja excluir esta ocorrência?')) return;
        
        // Create a form with CSRF token and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = SITE_URL + '/modulos/caderno_campo/ocorrencias/excluir.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        // Get CSRF token from existing field or create one
        const csrfField = document.querySelector('input[name="_csrf_token"]');
        if (csrfField) {
            const csrfClone = csrfField.cloneNode();
            form.appendChild(csrfClone);
        }
        
        // Submit via iframe to not leave the page
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.name = 'excluir_iframe_' + id;
        document.body.appendChild(iframe);
        form.target = iframe.name;
        document.body.appendChild(form);
        form.submit();
        
        // Reload table after a short delay
        setTimeout(function() {
            window.location.reload();
            document.body.removeChild(iframe);
            document.body.removeChild(form);
        }, 1000);
    };

    window.verCorrecoes = function(id) {
        const rowData = table.row(function(idx, data) {
            return data.id === id;
        }).data();

        if (rowData && rowData.correcoes_realizadas) {
            alert('Correções Realizadas:\n\n' + rowData.correcoes_realizadas);
        } else {
            alert('Nenhuma correção registrada.');
        }
    };

    window.exportarRelatorio = function(tipo) {
        let url = SITE_URL + '/caderno-campo/relatorios/exportar?relatorio=ocorrencias&tipo=' + tipo;
        
        if (IS_GESTOR) {
            const status = $('#statusFilterGroup .btn.active').data('filter');
            if (status) {
                url += '&status=' + encodeURIComponent(status);
            }
        }
        
        window.open(url, '_blank');
    };
});
</script>

<!-- Modal de Detalhes -->
<div class="modal fade" id="modalDetalhesOcorrencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>Detalhes da Ocorrência</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <!-- Carregado dinamicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

