<?php
$page_title = "Listar Lavouras";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

// Apenas usuários logados podem ver a lista
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar lavouras com join para nome da propriedade
$lavouras = [];
try {
    $db = Database::getInstance()->getConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($user->hasPermission(NIVEL_ADMIN)) {
        // Admin vê tudo
    } elseif ($user->hasPermission(NIVEL_GESTOR)) {
        // Gestor vê tudo (preparado para futura filtragem regional/polo)
    } else {
        // Usuário vê apenas lavouras vinculadas às suas próprias propriedades
        $associado_id = $user->getAssociadoId();
        // Fallback caso getAssociadoId falhe ou seja nulo
        if (!$associado_id) $associado_id = -1; 
        
        $whereClause = "WHERE p.associado_id = :associado_id";
        $params[':associado_id'] = $associado_id;
    }
    
    $query = "SELECT l.id, l.uuid, l.nome, l.tipo_cultura, l.area, l.data_plantio, p.propriedade AS propriedade_nome, p.cidade, p.estado 
              FROM lavouras l 
              INNER JOIN propriedades p ON l.propriedade_id = p.id 
              $whereClause
              ORDER BY l.id DESC";
              
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lavouras = [];
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Lavouras</h1>
    <div>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/lavoura/novo" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Lavoura
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Lavouras</span>
        <a href="<?php echo SITE_URL; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="lavourasTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="display:none;">ID</th>
                        <th>Nome</th>
                        <th>Propriedade</th>
                        <th>Cultura</th>
                        <th>Área (ha)</th>
                        <th>Plantio</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lavouras) > 0): ?>
                        <?php foreach ($lavouras as $lav): ?>
                            <tr>
                                <td style="display:none;"><?php echo htmlspecialchars($lav['id']); ?></td>
                                <td><?php echo htmlspecialchars($lav['nome']); ?></td>
                                <td><?php echo htmlspecialchars($lav['propriedade_nome']); ?></td>
                                <td><?php echo htmlspecialchars($lav['tipo_cultura']); ?></td>
                                <td class="text-end"><?php echo number_format($lav['area'], 2, ',', '.'); ?></td>
                                <td><?php echo $lav['data_plantio'] ? date('d/m/Y', strtotime($lav['data_plantio'])) : '-'; ?></td>
                                <td class="text-center">
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/lavoura/<?php echo $lav['uuid']; ?>/editar" 
                                       class="btn btn-sm btn-info" 
                                       title="Editar">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <?php // BUG corrigido: hasPermission('admin') era comparado como texto e sempre dava false (is_numeric('admin')).
                                    if ($user->hasPermission(NIVEL_ADMIN)): ?>
                                        <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/lavouras/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta lavoura?');">
                                            <input type="hidden" name="id" value="<?php echo (int) $lav['id']; ?>">
                                            <?php echo CSRFProtection::getTokenField(); ?>
                                            <button type="submit" class="btn btn-sm btn-danger" title="Excluir">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhuma lavoura cadastrada.</td>
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
        $('#lavourasTable').DataTable({
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
                    "targets": 0, // ID
                    "visible": false,
                    "searchable": false
                },
                { 
                    "width": "100px", 
                    "targets": 4 // Área
                },
                { 
                    "width": "120px", 
                    "targets": 5 // Data de plantio
                },
                { 
                    "width": "100px", 
                    "targets": -1 // Ações
                }
            ],
            "order": [[0, "desc"]], // Ordena por ID decrescente
            "pageLength": 25
        });
        
        console.log("DataTable de lavouras inicializado com sucesso!");
        
    } catch (error) {
        console.error("Erro ao inicializar DataTable:", error);
    }
});
</script>
