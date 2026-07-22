<?php
$page_title = "Listar Ocorrências";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Ocorrencia.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Apenas usuários logados podem ver a lista
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar ocorrências
$ocorrencias = [];
try {
    $ocorrencia_obj = new Ocorrencia();
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $ocorrencias = $ocorrencia_obj->getAllByAssociadoId($associado_id);
} catch (PDOException $e) {
    $ocorrencias = [];
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Ocorrências</h1>
    <div>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/novo" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Ocorrência
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Ocorrências</span>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="ocorrenciasTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Nome Comum</th>
                        <th>Data Detecção</th>
                        <th>Severidade</th>
                        <th>Propriedade</th>
                        <th>Lavoura</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ocorrencias) > 0): ?>
                        <?php foreach ($ocorrencias as $oc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($oc['id']); ?></td>
                                <td><?php echo htmlspecialchars($oc['tipo']); ?></td>
                                <td><?php echo htmlspecialchars($oc['nome_comum']); ?></td>
                                <td><?php echo $oc['data_deteccao'] ? date('d/m/Y', strtotime($oc['data_deteccao'])) : '-'; ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                    switch ($oc['severidade']) {
                                        case 'Baixa': echo 'success'; break;
                                        case 'Média': echo 'warning'; break;
                                        case 'Alta': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>"><?php echo htmlspecialchars($oc['severidade']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($oc['propriedade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($oc['lavoura_nome'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencia/<?php echo $oc['id']; ?>/editar" 
                                       class="btn btn-sm btn-info" 
                                       title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/ocorrencias/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta ocorrência?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $oc['id']; ?>">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhuma ocorrência cadastrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<style>
    .table th {
        white-space: nowrap;
    }
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.2rem;
    }
</style>

<script>
$(document).ready(function() {
    try {
        $('#ocorrenciasTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            },
            "responsive": true,
            "autoWidth": false,
            "columnDefs": [
                {
                    "targets": -1, // última coluna (ações)
                    "orderable": false,
                    "searchable": false,
                    "className": "text-center"
                },
                { 
                    "width": "100px", 
                    "targets": 3 // Data
                },
                { 
                    "width": "100px", 
                    "targets": -1 // Ações
                }
            ],
            "order": [[0, "desc"]], // Ordena por ID decrescente
            "pageLength": 25
        });
        
        console.log("DataTable de ocorrências inicializado com sucesso!");
        
    } catch (error) {
        console.error("Erro ao inicializar DataTable:", error);
    }
});
</script>
