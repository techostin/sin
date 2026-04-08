<?php
$page_title = "Listar Produtos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Apenas Admin ou Gestor podem ver a lista completa
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$produto = new Produto();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $produtos = $produto->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $produtos = $produto->getByAssociadoId($associado_id);
}

?>

<h1 class="mt-4">Produtos Cadastrados</h1>
<p>Abaixo está a lista de todos os produtos registrados no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Produtos</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/produto/novo" class="btn btn-primary btn-sm">Adicionar Novo Produto</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <table id="produtosTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th style="display:none;">ID</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Unidade</th>
                    <th>Quantidade</th>
                    <th>Data Colheita</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($produtos) > 0): ?>
                    <?php foreach ($produtos as $prod): ?>
                        <tr>
                            <td style="display:none;"><?php echo htmlspecialchars(isset($prod['id']) ? $prod['id'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($prod['nome']) ? $prod['nome'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($prod['tipo']) ? $prod['tipo'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($prod['unidade_medida']) ? $prod['unidade_medida'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($prod['quantidade_produzida']) ? $prod['quantidade_produzida'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($prod['data_colheita']) ? $prod['data_colheita'] : ''); ?></td>
                            <td>
                                <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario'): ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/produto/<?php echo $prod['uuid']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/produto/<?php echo $prod['uuid']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/produtos/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este produto?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $prod['id']; ?>">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Nenhum produto encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
<script>
    $(document).ready(function() {
        try {
            // Debug: verificar dados antes de inicializar DataTable
            var dados = <?php echo json_encode($produtos); ?>;
            console.log("Dados para produtosTable:", dados);
            
            if (dados.length > 0) {
                console.log("Primeira linha de dados:", dados[0]);
                console.log("Número de colunas na primeira linha:", Object.keys(dados[0]).length);
                console.log("Campos disponíveis:", Object.keys(dados[0]));
            }
            
            // Verificar se a tabela existe e tem a estrutura correta
            var table = $('#produtosTable');
            if (table.length === 0) {
                console.error("Tabela produtosTable não encontrada!");
                return;
            }
            
            // Verificar se há linhas na tabela
            var rows = table.find('tbody tr');
            console.log("Número de linhas na tabela:", rows.length);
            
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
                    },
                    {
                        "targets": 0, // ID
                        "visible": false,
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
