<?php
$page_title = "Listar Atividades Diárias";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar atividades diárias com joins para nomes das entidades relacionadas
$atividades = [];
$counter = 1;
try {
    $db = Database::getInstance()->getConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($user->hasPermission(NIVEL_GESTOR)) {
        // Vê tudo (exceto excluídos)
        $whereClause = "WHERE ad.excluido = 0";
    } else {
        $associado_id = $user->getAssociadoId();
        if (!$associado_id) $associado_id = -1;
        $whereClause = "WHERE ad.associado_id = :associado_id AND ad.excluido = 0";
        $params[':associado_id'] = $associado_id;
    }
    
    $query = "
        SELECT 
            ad.id, 
            ad.uuid,
            ad.nome, 
            ad.data_atividade, 
            ad.hora_inicio, 
            ad.hora_fim, 
            ad.tipo_atividade, 
            ad.status,
            ad.imagem_diaria,
            p.nome AS propriedade_nome,
            l.nome AS lavoura_nome,
            a.nome AS associado_nome,
            ca.nome AS categoria_nome,
            ca.cor AS categoria_cor
        FROM atividades_diarias ad
        LEFT JOIN propriedades p ON ad.propriedade_id = p.id
        LEFT JOIN lavouras l ON ad.lavoura_id = l.id
        LEFT JOIN associados a ON ad.associado_id = a.id
        LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
        $whereClause
        ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $atividades = [];
}
?>

<h1 class="mt-4">Atividades Diárias</h1>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Atividades Diárias</span>
        <div>
            <div class="btn-group me-2" role="group">
                <a href="<?php echo SITE_URL; ?>/api/relatorios/relatorio_atividades_usuario.php?formato=pdf" 
                   class="btn btn-danger btn-sm" 
                   target="_blank"
                   title="Gerar Relatório em PDF">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <a href="<?php echo SITE_URL; ?>/api/relatorios/relatorio_atividades_usuario.php?formato=excel" 
                   class="btn btn-success btn-sm" 
                   target="_blank"
                   title="Gerar Relatório em Excel">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
            </div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/novo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Adicionar Nova Atividade
            </a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard" class="btn btn-secondary btn-sm ms-2"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="atividadesTable" class="table table-striped table-bordered table-responsive-stack" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Propriedade</th>
                        <th>Lavoura</th>
                        <th>Associado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($atividades) > 0): ?>
                        <?php foreach ($atividades as $atividade): ?>
                            <tr>
                                <td data-label="#"><?php echo $counter++; ?></td>
                                <td data-label="Nome"><?php echo htmlspecialchars($atividade['nome']); ?></td>
                                <td data-label="Data"><?php echo $atividade['data_atividade'] ? date('d/m/Y', strtotime($atividade['data_atividade'])) : '-'; ?></td>
                                <td data-label="Horário">
                                    <?php 
                                    if ($atividade['hora_inicio'] && $atividade['hora_fim']) {
                                        echo date('H:i', strtotime($atividade['hora_inicio'])) . ' - ' . date('H:i', strtotime($atividade['hora_fim']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td data-label="Tipo"><?php echo htmlspecialchars($atividade['tipo_atividade']); ?></td>
                                <td data-label="Categoria">
                                    <?php if ($atividade['categoria_nome']): ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($atividade['categoria_cor']); ?>; color: white;">
                                            <?php echo htmlspecialchars($atividade['categoria_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php 
                                    $statusClass = '';
                                    switch($atividade['status']) {
                                        case 'Pendente': $statusClass = 'badge bg-warning'; break;
                                        case 'Em andamento': $statusClass = 'badge bg-info'; break;
                                        case 'Concluído': $statusClass = 'badge bg-success'; break;
                                        default: $statusClass = 'badge bg-secondary';
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($atividade['status']); ?></span>
                                </td>
                                <td data-label="Propriedade"><?php echo htmlspecialchars($atividade['propriedade_nome'] ?? '-'); ?></td>
                                <td data-label="Lavoura"><?php echo htmlspecialchars($atividade['lavoura_nome'] ?? '-'); ?></td>
                                <td data-label="Associado"><?php echo htmlspecialchars($atividade['associado_nome'] ?? '-'); ?></td>
                                <td data-label="Ações">
                                    <div class="btn-group" role="group">
                                        <?php $atividade_link = !empty($atividade['uuid']) ? $atividade['uuid'] : $atividade['id']; ?>
                                        <?php if (!empty($atividade['imagem_diaria'])): ?>
                                            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/<?php echo $atividade_link; ?>/imagens" 
                                               class="btn btn-outline-info btn-sm" 
                                               title="Visualizar Imagens">
                                                <i class="bi bi-images"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/<?php echo $atividade_link; ?>/editar" 
                                           class="btn btn-outline-primary btn-sm" 
                                           title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <?php if (!isset($_SESSION['user_tipo']) || $_SESSION['user_tipo'] !== 'usuario'): ?>
                                            <form method="POST" action="<?php echo SITE_URL; ?>/modulos/caderno_campo/atividade_diaria/excluir.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta atividade?');">
                                                <input type="hidden" name="id" value="<?php echo (int) $atividade['id']; ?>">
                                                <?php echo CSRFProtection::getTokenField(); ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Excluir">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">Nenhuma atividade encontrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
<script>
$(document).ready(function() {
    $('#atividadesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        }
    });
});
</script>
