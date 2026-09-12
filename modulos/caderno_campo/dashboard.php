<?php
$page_title = "Dashboard - Caderno de Campo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Permitir acesso a todos os usuários autenticados
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

// Buscar atividades do usuário logado
$atividades = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT ad.*, p.propriedade, l.nome AS lavoura_nome FROM atividades_diarias ad
        LEFT JOIN propriedades p ON ad.propriedade_id = p.id
        LEFT JOIN lavouras l ON ad.lavoura_id = l.id
        WHERE ad.associado_id = ? AND ad.excluido = 0 ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC");
    $stmt->execute([$user_id]);
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $atividades = [];
}
?>
<h1 class="mt-4">Painel de Caderno de Campo</h1>
<div class="row mb-4">
    <!-- Cards dos submódulos -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-house-door-fill display-4 text-primary"></i>
                <h5 class="card-title mt-2">Propriedades</h5>
                <p class="card-text">Gerencie suas propriedades rurais.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedades" class="btn btn-outline-primary btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-tree-fill display-4 text-success"></i>
                <h5 class="card-title mt-2">Lavouras</h5>
                <p class="card-text">Visualize e registre suas lavouras.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/lavouras" class="btn btn-outline-success btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-droplet-half display-4 text-info"></i>
                <h5 class="card-title mt-2">Insumos</h5>
                <p class="card-text">Controle os insumos utilizados.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/insumos" class="btn btn-outline-info btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-box-seam display-4 text-warning"></i>
                <h5 class="card-title mt-2">Produtos</h5>
                <p class="card-text">Gerencie os produtos agrícolas.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/produtos" class="btn btn-outline-warning btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-grid-3x3-gap-fill display-4 text-secondary"></i>
                <h5 class="card-title mt-2">Lotes de Produção</h5>
                <p class="card-text">Acompanhe os lotes de produção.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/lotes" class="btn btn-outline-secondary btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-tags-fill display-4 text-dark"></i>
                <h5 class="card-title mt-2">Categorias de Atividades</h5>
                <p class="card-text">Defina categorias para suas atividades.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/categorias-atividades" class="btn btn-outline-dark btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <i class="bi bi-calendar-check-fill display-4 text-primary"></i>
                <h5 class="card-title mt-2">Atividade Diária</h5>
                <p class="card-text">Registre e acompanhe suas atividades diárias.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/atividades-diarias" class="btn btn-outline-primary btn-sm">Acessar</a>
            </div>
        </div>
    </div>
    <!-- Card de Relatórios -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center border-success">
            <div class="card-body">
                <i class="bi bi-bar-chart-fill display-4 text-success"></i>
                <h5 class="card-title mt-2">Relatórios</h5>
                <p class="card-text">Gere relatórios por período, propriedade, lavoura ou geral do Caderno de Campo.</p>
                <a href="<?php echo SITE_URL; ?>/caderno-campo/relatorios" class="btn btn-success btn-sm">Gerar Relatórios</a>
            </div>
        </div>
    </div>
</div>
<!-- DataTable de atividades do usuário logado -->
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Minhas Atividades Diárias</span>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/novo" class="btn btn-primary btn-sm">Nova Atividade</a>
    </div>
    <div class="card-body">
        <table id="atividadesTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Data</th>
                    <th>Propriedade</th>
                    <th>Lavoura</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atividades as $atividade): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($atividade['nome']); ?></td>
                        <td><?php echo $atividade['data_atividade'] ? date('d/m/Y', strtotime($atividade['data_atividade'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($atividade['propriedade'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($atividade['lavoura_nome'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($atividade['tipo_atividade']); ?></td>
                        <td><?php echo htmlspecialchars($atividade['status']); ?></td>
                        <td>
                            <a href="<?php echo SITE_URL; ?>/modulos/caderno_campo/atividade_diaria/editar.php?id=<?php echo $atividade['id']; ?>" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                            <?php require_once __DIR__ . '/../../classes/CSRFProtection.php'; ?>
                            <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/atividade_diaria/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta atividade?');">
                                <input type="hidden" name="id" value="<?php echo (int) $atividade['id']; ?>">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <button type="submit" class="btn btn-danger btn-sm" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Widget de Eventos Próximos -->
<div class="row mt-4">
    <div class="col-md-12">
        <?php include __DIR__ . '/../../includes/widget_eventos.php'; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Inicializar DataTable das atividades
    $('#atividadesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "order": [[1, "desc"]] // Ordenar por data (segunda coluna) de forma decrescente
    });

    // Inicializar tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>