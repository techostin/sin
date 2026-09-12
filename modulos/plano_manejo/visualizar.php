<?php
$page_title = "Visualizar Plano de Manejo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de plano inválido.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar o plano principal
$stmt = $db->prepare("SELECT p.*, pr.propriedade as propriedade_nome, a.nome as associado_nome, a.sobrenome as associado_sobrenome 
                      FROM plano_manejo p 
                      JOIN propriedades pr ON p.propriedade_id = pr.id 
                      JOIN associados a ON p.associado_id = a.id 
                      WHERE p.id = ?");
$stmt->execute([$id]);
$plano = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plano) {
    echo "<div class='alert alert-danger'>Plano de manejo não encontrado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Buscar cultivos
$stmtC = $db->prepare("SELECT * FROM plano_manejo_cultivos WHERE plano_manejo_id = ?");
$stmtC->execute([$id]);
$cultivos = $stmtC->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <h1 class="mb-1"><i class="bi bi-journal-check me-2" style="color:var(--gm-primary);"></i>Detalhes do Plano de Manejo</h1>
            <div class="btn-group">
                <a href="<?php echo SITE_URL; ?>/planos-manejo" class="btn btn-assoc-ghost">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <button class="btn btn-assoc-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <?php $statusNormalizado = trim((string) ($plano['status'] ?? '')); $statusNormalizado = strtolower(str_replace(['_', '-'], ' ', $statusNormalizado)); ?>
                <?php if (($statusNormalizado === 'em análise' || $statusNormalizado === 'em analise' || $statusNormalizado === 'em analise') && $user->hasPermission(NIVEL_GESTOR)): ?>
                    <a href="<?php echo SITE_URL; ?>/plano-manejo/<?php echo $id; ?>/aprovar" class="btn btn-assoc-success">
                        <i class="bi bi-check-lg"></i> Aprovar Plano
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Badge Card -->
    <?php
        $statusLabel = trim((string) ($plano['status'] ?? ''));
        $statusLower = strtolower(str_replace(['_', '-'], ' ', $statusLabel));
        $statusColor = 'info';
        if ($statusLower === 'aprovado') {
            $statusColor = 'success';
        } elseif ($statusLower === 'reprovado') {
            $statusColor = 'danger';
        } elseif ($statusLower === 'revisao necessaria' || $statusLower === 'revisão necessária' || $statusLower === 'revisao necessaria') {
            $statusColor = 'warning';
        }
    ?>
    <div class="gm-card mb-4 border-start border-4 border-<?php echo $statusColor; ?>">
        <div class="p-4 d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small">STATUS ATUAL:</span>
                <span class="h5 mb-0 ms-2"><?php echo htmlspecialchars($statusLabel ?: 'Rascunho'); ?></span>
            </div>
            <div class="text-end">
                <span class="text-muted small">SAFRA:</span>
                <span class="h5 mb-0 ms-2"><?php echo $plano['ano_safra']; ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Coluna da Esquerda: Informações Gerais -->
        <div class="col-lg-8">
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-info-circle me-1" style="color:var(--gm-primary);"></i> 1. Caracterização da Unidade de Produção
                </div>
                <div class="p-4">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Propriedade</label>
                            <strong><?php echo $plano['propriedade_nome']; ?></strong>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block">Agricultor</label>
                            <strong><?php echo $plano['associado_nome'] . ' ' . $plano['associado_sobrenome']; ?></strong>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Área Total</label>
                            <?php echo $plano['area_total_ha'] ?: '-'; ?> ha
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Área Orgânica</label>
                            <?php echo $plano['area_organica_ha'] ?: '-'; ?> ha
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Topografia</label>
                            <?php echo $plano['topografia']; ?>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Descrição do Solo</label>
                        <p><?php echo nl2br($plano['descricao_solo']) ?: 'Não informado.'; ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Fontes de Água</label>
                        <p><?php echo nl2br($plano['fonte_agua']) ?: 'Não informado.'; ?></p>
                    </div>
                </div>
            </div>

            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-tools me-1" style="color:var(--gm-primary);"></i> 2. Práticas de Manejo e Conservação
                </div>
                <div class="p-4">
                    <div class="mb-4">
                        <label class="fw-bold text-success small">MANEJO DE PRAGAS E DOENÇAS</label>
                        <p class="border-start border-3 border-light ps-3"><?php echo nl2br($plano['manejo_pragas']) ?: 'Nenhuma prática específica registrada.'; ?></p>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold text-success small">MANEJO DE SOLO E ADUBAÇÃO</label>
                        <p class="border-start border-3 border-light ps-3"><?php echo nl2br($plano['adubacao_verde']) ?: 'Nenhuma prática específica registrada.'; ?></p>
                    </div>
                    <div>
                        <label class="fw-bold text-success small">ROTAÇÃO DE CULTURAS E COMPOSTAGEM</label>
                        <p class="border-start border-3 border-light ps-3"><?php echo nl2br($plano['compostagem']) ?: 'Não informado.'; ?></p>
                    </div>
                </div>
            </div>

            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-flower1 me-1" style="color:var(--gm-primary);"></i> 3. Planejamento de Cultivos
                </div>
                <div class="p-4">
                    <table class="table table-sm table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th>Cultura</th>
                                <th>Área</th>
                                <th>Semente</th>
                                <th>Expectativa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cultivos as $c): ?>
                            <tr>
                                <td><strong><?php echo $c['cultura']; ?></strong> <small class="text-muted"><?php echo $c['variedade']; ?></small></td>
                                <td><?php echo $c['area_cultivada_ha']; ?> ha</td>
                                <td><?php echo $c['tipo_semente']; ?></td>
                                <td><?php echo $c['producao_estimada_kg'] ? $c['producao_estimada_kg'].'kg' : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Coluna da Direita: Croqui e Assinaturas -->
        <div class="col-lg-4">
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-map me-1" style="color:var(--gm-primary);"></i> Croqui da Propriedade
                </div>
                <div class="p-4 text-center">
                    <?php if ($plano['croqui_propriedade_url']): ?>
                        <img src="<?php echo $plano['croqui_propriedade_url']; ?>" class="img-fluid rounded mb-2" alt="Croqui">
                    <?php else: ?>
                        <div class="bg-light p-5 rounded border border-dashed mb-2">
                            <i class="bi bi-image text-muted h1"></i>
                            <p class="text-muted small">Croqui ainda não enviado.</p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?php echo SITE_URL; ?>/plano-manejo/<?php echo $id; ?>/upload_croqui" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-upload"></i> Enviar/Atualizar Croqui
                    </a>
                </div>
            </div>

            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-file-earmark-check me-1"></i> Autenticação Tecnica
                </div>
                <div class="p-4 small">
                    <ul class="list-unstyled">
                        <li class="mb-2"><strong>Versão:</strong> <?php echo $plano['versao']; ?></li>
                        <li class="mb-2"><strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($plano['created_at'])); ?></li>
                        <li class="mb-2"><strong>Aprovado por:</strong> <?php echo $plano['aprovado_por'] ?: 'Pendente'; ?></li>
                        <li><strong>Data Aprovação:</strong> <?php echo $plano['data_aprovacao'] ? date('d/m/Y', strtotime($plano['data_aprovacao'])) : 'Pendente'; ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<style>
@media print {
    #wizard-steps, .btn-group, form, .input-group { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: #f8f9fa !important; border-bottom: 2px solid #dee2e6 !important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>