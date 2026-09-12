<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';

// Verificar se há mensagens de sucesso ou erro
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['erro'])) {
    $tipo_mensagem = 'danger';
    
    switch ($_GET['erro']) {
        case 'permissao_admin':
            $mensagem = 'Apenas administradores podem excluir associados.';
            break;
        case 'id_nao_informado':
            $mensagem = 'ID do associado não informado.';
            break;
        case 'registros_vinculados':
            $mensagem = isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Não é possível excluir o associado pois existem registros vinculados a ele.';
            break;
        case 'exclusao':
            $mensagem = isset($_GET['msg']) ? urldecode($_GET['msg']) : 'Ocorreu um erro ao tentar excluir o associado.';
            break;
        case 'csrf':
            $mensagem = 'Falha de segurança na exclusão. Atualize a página e tente novamente.';
            break;
        default:
            $mensagem = 'Ocorreu um erro inesperado.';
    }
} elseif (isset($_GET['msg'])) {
    $tipo_mensagem = 'success';
    
    switch ($_GET['msg']) {
        case 'excluido':
            $nome = isset($_GET['nome']) ? ' ' . urldecode($_GET['nome']) : '';
            $mensagem = "Associado{$nome} excluído com sucesso!";
            break;
        default:
            $mensagem = 'Operação realizada com sucesso!';
    }
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_nivel'])) {
    // Se não estiver logado, redireciona para o login
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Se não for admin nem gestor, redireciona para a edição do próprio perfil
if (!in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)) {
    if (isset($_SESSION['user_associado_id'])) {
        header('Location: ' . SITE_URL . '/associado/' . $_SESSION['user_associado_id'] . '/editar');
    } else {
        // Se não tiver associado_id, redireciona para o perfil do usuário
        header('Location: ' . SITE_URL . '/usuario/' . $_SESSION['user_id'] . '/editar');
    }
    exit;
}

// Define o título da página
$page_title = "Listar Associados";
$fab_action = SITE_URL . '/associado/novo';
$fab_label  = 'Novo Associado';

// Inclui os arquivos necessários
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/FichaAdesao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$associado = new Associado();
$associados = $associado->getAll();

?>

<style>
    .associados-header {
        gap: 1rem;
    }
    .associados-header .header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }
    .dataTables_wrapper .dt-buttons {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        float: none !important;
        text-align: right;
        margin-top: 0.5rem;
    }
    @media (max-width: 767px) {
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            text-align: left;
        }
    }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 associados-header">
    <div>
        <h1 class="h3 mb-0">Lista de Associados</h1>
        <p class="mb-0 text-muted">Abaixo está a lista de todos os associados registrados no sistema.</p>
    </div>
    <div class="header-actions">
        <?php if (in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)): ?>
            <a href="<?php echo SITE_URL; ?>/associado/novo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Adicionar Novo
            </a>
            <a href="<?php echo SITE_URL; ?>/associados/relatorio" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Gerar Relatório
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="associadosTable" class="table table-striped table-hover table-responsive-stack">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nome Completo</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Cidade</th>
                        <th>Status</th>
                        <th>Nível</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($associados) > 0): ?>
                        <?php 
                        // Instanciar FichaAdesao para verificar fichas
                        $fichaModel = new FichaAdesao();
                        ?>
                        <?php foreach ($associados as $assoc): ?>
                            <?php 
                            $foto_url = $assoc['foto_url'] ?? null;
                            $rowPhotoUrl = ($foto_url && file_exists('uploads/associados/' . $foto_url)) ? SITE_URL . '/uploads/associados/' . htmlspecialchars($foto_url) : '';
                            $rowNome = htmlspecialchars((isset($assoc['nome']) ? $assoc['nome'] : '') . ' ' . (isset($assoc['sobrenome']) ? $assoc['sobrenome'] : ''));
                            
                            // Verificar se o associado tem ficha de adesão
                            $ficha = $fichaModel->getByAssociadoId($assoc['id']);
                            $temFicha = $ficha !== null;
                            $fichaStatus = $temFicha ? $ficha['status'] : null;
                            $fichaId = $temFicha ? $ficha['id'] : null;
                            ?>
                            <tr <?php echo $rowPhotoUrl ? 'data-full-url="' . $rowPhotoUrl . '" data-nome="' . $rowNome . '"' : ''; ?>>
                                <td data-label="Foto">
                                    <?php if ($rowPhotoUrl): ?>
                                        <img src="<?php echo $rowPhotoUrl; ?>" 
                                             class="associado-photo img-thumbnail rounded-circle" 
                                             style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;" 
                                             data-full-url="<?php echo $rowPhotoUrl; ?>"
                                             data-nome="<?php echo $rowNome; ?>"
                                             alt="Foto do associado"
                                             title="Clique para ampliar">
                                    <?php else: ?>
                                        <div class="associado-photo-placeholder bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mx-md-0" 
                                             style="width: 50px; height: 50px; font-size: 20px; cursor: default;"
                                             title="ID: <?php echo htmlspecialchars($assoc['id'] ?? ''); ?>">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Nome Completo"><?php echo htmlspecialchars((isset($assoc['nome']) ? $assoc['nome'] : '') . ' ' . (isset($assoc['sobrenome']) ? $assoc['sobrenome'] : '')); ?></td>
                                <td data-label="E-mail"><?php echo htmlspecialchars(isset($assoc['email']) ? $assoc['email'] : ''); ?></td>
                                <td data-label="Telefone"><?php echo htmlspecialchars(isset($assoc['telefone']) ? $assoc['telefone'] : ''); ?></td>
                                <td data-label="Cidade"><?php echo htmlspecialchars(isset($assoc['cidade_nome']) ? $assoc['cidade_nome'] : (isset($assoc['cidade']) ? $assoc['cidade'] : '')); ?></td>
                                <td data-label="Status"><span class="badge bg-<?php echo (isset($assoc['status']) && $assoc['status'] == 'ativo') ? 'success' : 'warning'; ?>"><?php echo ucfirst(htmlspecialchars(isset($assoc['status']) ? $assoc['status'] : '')); ?></span></td>
                                <td data-label="Nível"><?php echo htmlspecialchars(isset($assoc['tipo']) ? $assoc['tipo'] : ''); ?></td>
                                <td data-label="Ações">
                                    <?php if ($_SESSION['user_nivel'] == NIVEL_ADMIN): ?>
                                        <a href="<?php echo SITE_URL; ?>/associado/<?php echo $assoc['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                        <button type="button" class="btn btn-danger btn-sm" title="Excluir" onclick="confirmarExclusao(<?php echo $assoc['id']; ?>)">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo SITE_URL; ?>/associado/<?php echo $assoc['id']; ?>/editar?view=1" class="btn btn-secondary btn-sm" title="Visualizar"><i class="bi bi-eye-fill"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if ($temFicha): ?>
                                        <?php
                                        // Determinar cor do botão conforme status da ficha
                                        $btnClass = match($fichaStatus) {
                                            'aprovada' => 'btn-success',
                                            'pendente' => 'btn-warning text-dark',
                                            'recusada' => 'btn-danger',
                                            'rascunho' => 'btn-secondary',
                                            default => 'btn-outline-primary'
                                        };
                                        $btnTitle = match($fichaStatus) {
                                            'aprovada' => 'Ficha Aprovada',
                                            'pendente' => 'Ficha Pendente de Análise',
                                            'recusada' => 'Ficha Recusada',
                                            'rascunho' => 'Ficha em Rascunho',
                                            default => 'Ficha de Adesão'
                                        };
                                        ?>
                                        <a href="javascript:void(0);" 
                                           onclick="abrirFichaModal(<?php echo $fichaId; ?>)"
                                           class="btn <?php echo $btnClass; ?> btn-sm" 
                                           title="<?php echo $btnTitle; ?>">
                                            <i class="bi bi-file-earmark-person"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="javascript:void(0);" 
                                           onclick="abrirFichaModal(<?php echo $assoc['id']; ?>)"
                                           class="btn btn-outline-primary btn-sm" 
                                           title="Criar Ficha de Adesão">
                                            <i class="bi bi-file-earmark-plus"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum associado encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalLabel">Foto do Associado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImage" src="" alt="Foto ampliada" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
// Função para confirmar a exclusão de um associado
function confirmarExclusao(id) {
    if (confirm('Tem certeza que deseja excluir este associado? Esta ação não pode ser desfeita.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo SITE_URL; ?>/modulos/associados/excluir.php';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '<?php echo CSRF_TOKEN_NAME; ?>';
        tokenInput.value = '<?php echo htmlspecialchars(CSRFProtection::generateToken(), ENT_QUOTES, "UTF-8"); ?>';
        form.appendChild(tokenInput);

        document.body.appendChild(form);
        form.submit();
    }
}

$(document).ready(function() {
        try {
            // Debug: verificar dados antes de inicializar DataTable
            var dados = <?php echo json_encode($associados); ?>;
            console.log("Dados para associadosTable:", dados);
            
            if (dados.length > 0) {
                console.log("Primeira linha de dados:", dados[0]);
                console.log("Número de colunas na primeira linha:", Object.keys(dados[0]).length);
                console.log("Campos disponíveis:", Object.keys(dados[0]));
            }
            
            // Verificar se a tabela existe e tem a estrutura correta
            var table = $('#associadosTable');
            if (table.length === 0) {
                console.error("Tabela associadosTable não encontrada!");
                return;
            }
            
            // Verificar se há linhas na tabela
            var rows = table.find('tbody tr');
            console.log("Número de linhas na tabela:", rows.length);
            
            // Destruir DataTable existente se houver
            if ($.fn.DataTable.isDataTable('#associadosTable')) {
                $('#associadosTable').DataTable().destroy();
            }
            
            // Inicializar DataTable com configurações mais robustas
            var dataTable = table.DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
                },
                "responsive": true,
                "autoWidth": false,
                "dom": '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 text-end"Bf>>rtip',
                "buttons": [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                        className: 'btn btn-outline-success btn-sm me-1',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                        className: 'btn btn-outline-danger btn-sm me-1',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bi bi-printer"></i> Imprimir',
                        className: 'btn btn-outline-secondary btn-sm',
                        exportOptions: {
                            columns: ':not(:last-child)'
                        }
                    }
                ],
                "columnDefs": [
                    {
                        "targets": -1, // última coluna (ações)
                        "orderable": false,
                        "searchable": false
                    }
                ]
            });
            
            console.log("DataTable inicializado com sucesso!");

            // Inicializar click para ampliar foto do associado nas imagens
            document.querySelectorAll('.associado-photo').forEach(function(photo) {
                photo.addEventListener('click', function(event) {
                    event.stopPropagation();
                    var imagemUrl = this.dataset.fullUrl || this.src;
                    var nome = this.dataset.nome || 'Foto do Associado';
                    document.getElementById('photoModalLabel').textContent = nome;
                    document.getElementById('photoModalImage').src = imagemUrl;
                    var photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
                    photoModal.show();
                });
            });

            // Inicializar clique na linha para ampliar a foto, exceto em botões/links
            document.querySelectorAll('#associadosTable tbody tr[data-full-url]').forEach(function(row) {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function(event) {
                    if (event.target.closest('a, button, input, select, textarea, .btn')) {
                        return;
                    }
                    var imagemUrl = this.dataset.fullUrl;
                    var nome = this.dataset.nome || 'Foto do Associado';
                    if (!imagemUrl) {
                        return;
                    }
                    document.getElementById('photoModalLabel').textContent = nome;
                    document.getElementById('photoModalImage').src = imagemUrl;
                    var photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
                    photoModal.show();
                });
            });
        } catch (error) {
            console.error("Erro ao inicializar DataTable:", error);
        }
    });
</script>

<!-- Modal para visualização da ficha de adesão -->
<div class="modal fade" id="modalFichaVisualizacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard-fill me-2"></i>Ficha de Adesão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Abre a ficha de adesão do associado em modal via AJAX
 */
function abrirFichaModal(fichaId) {
    const modalEl = document.getElementById('modalFichaVisualizacao');
    if (!modalEl || !window.bootstrap) return;
    
    const modal = new bootstrap.Modal(modalEl);
    const modalBody = modalEl.querySelector('.modal-body');
    
    // Mostrar loading
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2 text-muted">Carregando ficha de adesão...</p>
        </div>
    `;
    modal.show();
    
    // Carregar via AJAX
    fetch('<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=' + fichaId + '&modal=1', {
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        }
    })
    .then(response => response.text())
    .then(html => {
        modalBody.innerHTML = html;
        
        // Re-inicializar tooltips se necessário
        if (window.bootstrap && bootstrap.Tooltip) {
            const tooltips = modalEl.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
    })
    .catch(err => {
        modalBody.innerHTML = `
            <div class="alert alert-danger text-center p-4">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Erro ao carregar a ficha. Tente novamente.
            </div>
        `;
    });
}
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
</create_file>
