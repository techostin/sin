<?php
$page_title = "Listar Categorias de Atividades";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar categorias com join para nome do associado
$categorias = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT 
            ca.id, 
            ca.nome, 
            ca.descricao, 
            ca.cor, 
            ca.created_at,
            a.nome AS associado_nome
        FROM categorias_atividades ca
        LEFT JOIN associados a ON ca.associado_id = a.id
        ORDER BY ca.nome ASC
    ");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}
?>

<h1 class="mt-4">Categorias de Atividades</h1>
<p>Abaixo está a lista de todas as categorias de atividades registradas no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Categorias de Atividades</span>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/categoria-atividade/novo" class="btn btn-primary btn-sm">Adicionar Nova Categoria</a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <table id="categoriasTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Cor</th>
                    <th>Associado</th>
                    <th>Data de Criação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categorias) > 0): ?>
                    <?php foreach ($categorias as $categoria): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($categoria['id']); ?></td>
                            <td><?php echo htmlspecialchars($categoria['nome']); ?></td>
                            <td><?php echo htmlspecialchars($categoria['descricao'] ?? '-'); ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="color-preview me-2" style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($categoria['cor']); ?>; border: 1px solid #ddd; border-radius: 3px;"></div>
                                    <span><?php echo htmlspecialchars($categoria['cor']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($categoria['associado_nome'] ?? '-'); ?></td>
                            <td><?php echo $categoria['created_at'] ? date('d/m/Y H:i', strtotime($categoria['created_at'])) : '-'; ?></td>
                            <td>
                                <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario'): ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/categoria-atividade/<?php echo $categoria['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/categoria-atividade/<?php echo $categoria['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/categorias_atividades/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $categoria['id']; ?>">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Nenhuma categoria encontrada.</td>
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
        $('#categoriasTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            }
        });
    });
</script>
