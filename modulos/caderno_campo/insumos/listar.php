<?php
$page_title = "Listar Insumos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Insumo.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Apenas Admin ou Gestor podem ver a lista completa
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$insumo = new Insumo();

$user_nivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
if ($user_nivel == NIVEL_ADMIN || $user_nivel == NIVEL_GESTOR) {
    $insumos = $insumo->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $insumos = $insumo->getByAssociadoId($associado_id);
}

?>

<h1 class="mt-4">Insumos Cadastrados</h1>
<p>Abaixo está a lista de todos os insumos registrados no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Insumos</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/novo" class="btn btn-primary btn-sm">Adicionar Novo Insumo</a>
            <a href="<?php echo dashboard_destino(); ?>" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <table id="insumosTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th style="display:none;">ID</th>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Associado</th>
                    <th>Fornecedor</th>
                    <th>Est. Anterior</th>
                    <th>Quant. Recebida</th>
                    <th>Quant. Estoque</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($insumos) > 0): ?>
                    <?php foreach ($insumos as $ins): ?>
                        <tr>
                            <td style="display:none;"><?php echo htmlspecialchars($ins['id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ins['nome'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($ins['descricao'] ?? ''); ?></td>
                            <td><?php echo !empty($ins['nome_associado']) ? htmlspecialchars($ins['nome_associado']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            <td><?php echo !empty($ins['nome_fornecedor']) ? htmlspecialchars($ins['nome_fornecedor']) : '<span class="text-muted">Não informado</span>'; ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($ins['estoq_anterior'] ?? '0'); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($ins['quant_recebida'] ?? '0'); ?></td>
                            <td class="text-end fw-bold"><?php echo htmlspecialchars($ins['quantidade_estoque'] ?? '0'); ?></td>
                            <td>
                                <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario'): ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/<?php echo $ins['uuid']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/<?php echo $ins['uuid']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/insumos/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este insumo?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $ins['id']; ?>">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Nenhum insumo encontrado.</td>
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
            var dados = <?php echo json_encode($insumos); ?>;
            console.log("Dados para insumosTable:", dados);
            
            if (dados.length > 0) {
                console.log("Primeira linha de dados:", dados[0]);
                console.log("Número de colunas na primeira linha:", Object.keys(dados[0]).length);
                console.log("Campos disponíveis:", Object.keys(dados[0]));
            }
            
            // Verificar se a tabela existe e tem a estrutura correta
            var table = $('#insumosTable');
            if (table.length === 0) {
                console.error("Tabela insumosTable não encontrada!");
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
                    }
                ]
            });
            
            console.log("DataTable inicializado com sucesso!");
            
        } catch (error) {
            console.error("Erro ao inicializar DataTable:", error);
        }
    });
</script>
