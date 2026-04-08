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

// Inclui os arquivos necessários
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$associado = new Associado();
$associados = $associado->getAll();

?>

<h1 class="mt-4">Associados Cadastrados</h1>
<p>Abaixo está a lista de todos os associados registrados no sistema.</p>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Associados</span>
        <div class="btn-group">
            <?php if (in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)): ?>
                <a href="<?php echo SITE_URL; ?>/associado/novo" class="btn btn-primary btn-sm me-2">
                    <i class="bi bi-plus-circle"></i> Adicionar Novo
                </a>
                <a href="<?php echo SITE_URL; ?>/associados/relatorio" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> Gerar Relatório
                </a>
            <?php endif; ?>
        </div>
    </div>
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
                        <?php foreach ($associados as $assoc): ?>
                            <tr>
                                <td data-label="Foto">
                                    <?php 
                                    $foto_url = $assoc['foto_url'] ?? null;
                                    if ($foto_url && file_exists('uploads/associados/' . $foto_url)): 
                                    ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/associados/<?php echo htmlspecialchars($foto_url); ?>" 
                                             class="img-thumbnail rounded-circle" 
                                             style="width: 50px; height: 50px; object-fit: cover;" 
                                             alt="Foto do associado"
                                             title="ID: <?php echo htmlspecialchars($assoc['id'] ?? ''); ?>">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mx-md-0" 
                                             style="width: 50px; height: 50px; font-size: 20px;"
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
                                    <a href="<?php echo SITE_URL; ?>/associado/<?php echo $assoc['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    <?php if ($_SESSION['user_nivel'] == NIVEL_ADMIN): ?>
                                        <button type="button" class="btn btn-danger btn-sm" title="Excluir" onclick="confirmarExclusao(<?php echo $assoc['id']; ?>)">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
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

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

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
                "columnDefs": [
                    {
                        "targets": -1, // última coluna (ações)
                        "orderable": false,
                        "searchable": false
                    }
                ]
            });
            
            console.log("DataTable inicializado com sucesso!");
            
        } catch (error) {
            console.error("Erro ao inicializar DataTable:", error);
        }
    });
</script>
