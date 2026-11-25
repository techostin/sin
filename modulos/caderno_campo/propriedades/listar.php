<?php
$page_title = "Listar Propriedades";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';

// Permitir acesso a todos os usuários autenticados
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$propriedade = new Propriedade();

// Verificação de Nível para Filtragem
if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
    // Se for usuário comum, buscar apenas as suas propriedades
    $currentUser = $user->getCurrentUser();
    $associado_id = null;
    if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'associados') {
        $associado_id = $currentUser['id'];
    } elseif (isset($currentUser['associado_id'])) {
        $associado_id = $currentUser['associado_id'];
    }
    
    if ($associado_id) {
        $propriedades = $propriedade->getByAssociadoId($associado_id);
    } else {
        $propriedades = []; // Sem ID de associado vinculado, não vê nada
    }
} else {
    // Admin ou Gestor vê todas
    $propriedades = $propriedade->getAll();
}

?>

<h1 class="mt-4">Propriedades Cadastradas</h1>
<p>Abaixo está a lista de todas as propriedades registradas no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Propriedades</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/novo" class="btn btn-primary btn-sm">Adicionar Nova Propriedade</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="propriedadesTable" class="table table-striped table-bordered table-responsive-stack" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Área (ha)</th>
                        <th>Endereço</th>
                        <th>Cidade/Estado</th>
                        <th>Imagens</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($propriedades) > 0): ?>
                        <?php foreach ($propriedades as $prop): ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($prop['id']); ?></td>
                                <td data-label="Nome"><?php echo htmlspecialchars($prop['propriedade']); ?></td>
                                <td data-label="Área (ha)">
                                    <?php if (!empty($prop['area_total'])): ?>
                                        <?php echo number_format($prop['area_total'], 2); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Endereço">
                                    <?php if (!empty($prop['endereco'])): ?>
                                        <?php echo htmlspecialchars($prop['endereco']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Cidade/Estado">
                                    <?php 
                                    if (!empty($prop['cidade_nome']) && !empty($prop['estado_sigla'])) {
                                        echo htmlspecialchars($prop['cidade_nome']) . ' - ' . htmlspecialchars($prop['estado_sigla']);
                                    } elseif (!empty($prop['cidade_nome'])) {
                                        echo htmlspecialchars($prop['cidade_nome']);
                                    } elseif (!empty($prop['estado_sigla'])) {
                                        echo htmlspecialchars($prop['estado_sigla']);
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td data-label="Imagens">
                                    <?php 
                                    $tem_imagens = !empty($prop['imagem_principal']) || !empty($prop['imagem_secundaria']);
                                    if ($tem_imagens): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-image"></i> 
                                            <?php 
                                            $count = 0;
                                            if (!empty($prop['imagem_principal'])) $count++;
                                            if (!empty($prop['imagem_secundaria'])) $count++;
                                            echo $count . ' imagem' . ($count > 1 ? 'ns' : '');
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-image"></i> Sem imagens
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Ações">
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/<?php echo $prop['id']; ?>/editar" 
                                           class="btn btn-outline-primary btn-sm" 
                                           title="Editar Propriedade">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        
                                        <?php if ($tem_imagens): ?>
                                            <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/<?php echo $prop['id']; ?>/imagens" 
                                               class="btn btn-outline-info btn-sm" 
                                               title="Visualizar Imagens">
                                                <i class="bi bi-images"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?php echo SITE_URL; ?>/propriedade/<?php echo $prop['id']; ?>/historico-conversao"
                                           class="btn btn-outline-warning btn-sm"
                                           title="Histórico de Conversão (RF03)">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-sm" 
                                                onclick="visualizarPropriedade(<?php echo $prop['id']; ?>)"
                                                title="Ver Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para visualizar propriedade -->
<div class="modal fade" id="modalVisualizarPropriedade" tabindex="-1" aria-labelledby="modalVisualizarPropriedadeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVisualizarPropriedadeLabel">Detalhes da Propriedade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalVisualizarPropriedadeBody">
                <!-- Conteúdo será carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>

<!-- DataTables CSS e JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#propriedadesTable').DataTable({
        responsive: true,
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
        },
        columnDefs: [
            {
                targets: [0], // ID
                width: '60px',
                className: 'text-center'
            },
            {
                targets: [1], // Nome
                width: '140px', // Reduzido em 30%
                className: 'text-start'
            },
            {
                targets: [2], // Área
                width: '50px', // Reduzido em 50%
                className: 'text-center'
            },
            {
                targets: [5], // Imagens
                width: '120px',
                className: 'text-center'
            },
            {
                targets: [6], // Ações
                width: '150px',
                className: 'text-center',
                orderable: false
            }
        ],
        order: [[1, 'asc']], // Ordenar por nome
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });
});

function visualizarPropriedade(id) {
    // Mostrar loading no modal
    document.getElementById('modalVisualizarPropriedadeBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2">Carregando detalhes da propriedade...</p>
        </div>
    `;
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalVisualizarPropriedade'));
    modal.show();
    
    // Buscar dados da propriedade
    fetch(`<?php echo SITE_URL; ?>/api/get_propriedade_detalhes.php?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro ao carregar dados da propriedade');
            }
            return response.json();
        })
        .then(data => {
            const prop = data.propriedade;
            const stats = data.estatisticas;
            const lavouras = data.lavouras;
            const atividades = data.atividades_recentes;
            
            // Gerar HTML do modal
            let html = `
                <div class="row">
                    <!-- Informações Básicas -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-info-circle"></i> Informações Básicas
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Nome:</strong> ${prop.nome}</p>
                                <p><strong>Área Total:</strong> ${prop.area_total}</p>
                                <p><strong>Data de Cadastro:</strong> ${prop.data_cadastro}</p>
                                <p><strong>Endereço:</strong> ${prop.endereco}</p>
                                <p><strong>Bairro:</strong> ${prop.bairro}</p>
                                <p><strong>CEP:</strong> ${prop.cep}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Localização -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-geo-alt"></i> Localização
                                </h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Cidade:</strong> ${prop.cidade}</p>
                                <p><strong>Estado:</strong> ${prop.estado} - ${prop.estado_nome}</p>
                                <p><strong>Latitude:</strong> ${prop.latitude}</p>
                                <p><strong>Longitude:</strong> ${prop.longitude}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estatísticas -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-graph-up"></i> Estatísticas
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <div class="border-end">
                                            <h4 class="text-primary">${stats.total_lavouras}</h4>
                                            <small class="text-muted">Lavouras</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border-end">
                                            <h4 class="text-success">${stats.total_atividades}</h4>
                                            <small class="text-muted">Atividades</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-info">${stats.area_total_lavouras}</h4>
                                        <small class="text-muted">Área Plantada</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Adicionar seção de lavouras se houver
            if (lavouras.length > 0) {
                html += `
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-tree"></i> Lavouras (${lavouras.length})
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Cultura</th>
                                                    <th>Área</th>
                                                    <th>Data Plantio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                `;
                
                lavouras.forEach(lavoura => {
                    html += `
                        <tr>
                            <td>${lavoura.nome}</td>
                            <td>${lavoura.cultura || '-'}</td>
                            <td>${lavoura.area ? lavoura.area + ' ha' : '-'}</td>
                            <td>${lavoura.data_plantio_formatada || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Adicionar seção de atividades recentes se houver
            if (atividades.length > 0) {
                html += `
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-calendar-check"></i> Atividades Recentes (${atividades.length})
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Atividade</th>
                                                    <th>Data</th>
                                                    <th>Status</th>
                                                    <th>Categoria</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                `;
                
                atividades.forEach(atividade => {
                    const statusClass = atividade.status === 'Concluído' ? 'success' : 
                                      atividade.status === 'Em andamento' ? 'warning' : 'secondary';
                    const statusBadge = `<span class="badge bg-${statusClass}">${atividade.status}</span>`;
                    
                    html += `
                        <tr>
                            <td>${atividade.nome}</td>
                            <td>${atividade.data_formatada}</td>
                            <td>${statusBadge}</td>
                            <td>${atividade.categoria_nome || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Adicionar observações se houver
            if (prop.observacoes && prop.observacoes !== 'Não informado') {
                html += `
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-chat-text"></i> Observações
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-0">${prop.observacoes}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Atualizar conteúdo do modal
            document.getElementById('modalVisualizarPropriedadeBody').innerHTML = html;
        })
        .catch(error => {
            console.error('Erro ao carregar dados da propriedade:', error);
            document.getElementById('modalVisualizarPropriedadeBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    Erro ao carregar dados da propriedade. Tente novamente.
                </div>
            `;
        });
}
</script>
