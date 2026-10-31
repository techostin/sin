<?php
/**
 * Lista de Assembleias
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';

$user = new User();
// Admin/Gestor do sistema OU membro da Diretoria (Presidente, Vice-Presidente,
// Diretor, Secretário, Tesoureiro e comissões/conselhos) da associação.
if (!$user->hasPermission(NIVEL_GESTOR) && !$user->isMembroDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_associacao = $_GET['associacao_id'] ?? '';

$sql = "
    SELECT a.*, 
           ac.nome as associacao_nome, ac.sigla as associacao_sigla,
           CONCAT(u.nome, ' ', u.sobrenome) as criador_nome,
           (SELECT COUNT(*) FROM assembleia_pautas WHERE assembleia_id = a.id) as qtd_pautas,
           (SELECT COUNT(*) FROM assembleia_votos WHERE assembleia_id = a.id) as qtd_votos
    FROM assembleias a
    LEFT JOIN associacoes ac ON ac.id = a.associacao_id
    LEFT JOIN usuarios u ON u.id = a.created_by
    WHERE 1=1
";
$params = [];

if ($filtro_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filtro_status;
}
if ($filtro_associacao) {
    $sql .= " AND a.associacao_id = ?";
    $params[] = $filtro_associacao;
}

$sql .= " ORDER BY a.data_inicio DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assembleias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Associações para filtro
$associacoes = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Assembleias e Votações";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Assembleias e Votações</h1>
            <span class="text-muted">Gestão de assembleias online e votações eletrônicas</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/assembleia/novo" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Nova Assembleia</a>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="rascunho" <?php echo $filtro_status==='rascunho'?'selected':''; ?>>Rascunho</option>
                        <option value="ativa" <?php echo $filtro_status==='ativa'?'selected':''; ?>>Ativa</option>
                        <option value="encerrada" <?php echo $filtro_status==='encerrada'?'selected':''; ?>>Encerrada</option>
                        <option value="cancelada" <?php echo $filtro_status==='cancelada'?'selected':''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Associação</label>
                    <select name="associacao_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $filtro_associacao==$a['id']?'selected':''; ?>><?php echo htmlspecialchars($a['nome'].' ('.$a['sigla'].')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i> Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaAssembleias">
                    <thead class="table-light">
                        <tr>
                            <th>Título</th>
                            <th>Associação</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th class="text-center">Pautas</th>
                            <th class="text-center">Votos</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assembleias as $a): 
                            $statusClass = [
                                'rascunho' => 'secondary',
                                'ativa' => 'success',
                                'encerrada' => 'info',
                                'cancelada' => 'danger'
                            ][$a['status']] ?? 'secondary';
                            $agora = new DateTime();
                            $inicio = new DateTime($a['data_inicio']);
                            $fim = new DateTime($a['data_fim']);
                            $podeVotar = $a['status'] === 'ativa' && $agora >= $inicio && $agora <= $fim;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($a['titulo']); ?></strong>
                                <?php if (!empty($a['descricao'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($a['descricao'], 0, 80)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $a['associacao_nome'] ? htmlspecialchars($a['associacao_nome'].' ('.$a['associacao_sigla'].')') : '<span class="text-muted">Todas</span>'; ?></td>
                            <td>
                                <div><?php echo date('d/m/Y H:i', strtotime($a['data_inicio'])); ?></div>
                                <small class="text-muted">até <?php echo date('d/m/Y H:i', strtotime($a['data_fim'])); ?></small>
                            </td>
                            <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($a['status']); ?></span></td>
                            <td class="text-center"><?php echo $a['qtd_pautas']; ?></td>
                            <td class="text-center"><?php echo $a['qtd_votos']; ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/editar" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>" class="btn btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                    <?php if ($a['status'] === 'rascunho'): ?>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/pautas" class="btn btn-outline-success" title="Gerenciar Pautas"><i class="bi bi-list-task"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/publicar" class="btn btn-outline-warning" title="Publicar/Ativar" onclick="return confirm('Publicar esta assembleia?')"><i class="bi bi-send"></i></a>
                                    <?php elseif ($a['status'] === 'ativa'): ?>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/resultado" class="btn btn-outline-info" title="Ver Resultado"><i class="bi bi-graph-up"></i></a>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/encerrar" class="btn btn-outline-danger" title="Encerrar" onclick="return confirm('Encerrar votação?')"><i class="bi bi-stop-circle"></i></a>
                                    <?php elseif ($a['status'] === 'encerrada'): ?>
                                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $a['id']; ?>/resultado" class="btn btn-outline-info" title="Ver Resultado"><i class="bi bi-graph-up"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assembleias)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma assembleia encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaAssembleias').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 15,
        "order": [[2, 'desc']]
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>