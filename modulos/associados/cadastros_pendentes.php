<?php
/**
 * Cadastros Pendentes — análise das Fichas de Adesão pela Diretoria/Presidência.
 * DataTable com todas as fichas submetidas aguardando avaliação (status 'pendente'),
 * com ações de Visualizar, Aprovar e Recusar (com parecer).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/FichaAdesao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$page_title = "Cadastros Pendentes";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

$user = new User();

// Apenas Admin do sistema e Diretoria Executiva (Presidente, Diretor, Secretário, Tesoureiro)
if (!$user->hasPermission(NIVEL_ADMIN) && !$user->isDiretoriaExecutiva()) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Apenas administradores do sistema e membros da Diretoria Executiva (Presidente, Diretor, Secretário, Tesoureiro) podem avaliar as fichas de adesão pendentes.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$model = new FichaAdesao();

$msg = '';
$msg_type = '';

// ── Processamento de avaliação (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if (!CSRFProtection::validatePostRequest()) {
        $msg = 'Erro de segurança: token CSRF inválido.';
        $msg_type = 'danger';
    } else {
        $fichaId = (int)($_POST['ficha_id'] ?? 0);
        $analisadoPor = (int)($_SESSION['user_id'] ?? $_SESSION['associado_id'] ?? 0);

        if ($_POST['acao'] === 'aprovar') {
            $res = $model->aprovar($fichaId, $analisadoPor);
        } elseif ($_POST['acao'] === 'recusar') {
            $res = $model->recusar($fichaId, $analisadoPor, (string)($_POST['motivo'] ?? ''));
        } else {
            $res = ['success' => false, 'message' => 'Ação desconhecida.'];
        }

        $msg = $res['message'];
        $msg_type = $res['success'] ? 'success' : 'danger';
    }
}

$pendentes = $model->listarPendentes();
$avaliadas = $model->listarAvaliadas(10);
?>

<div class="assoc-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/associados-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<div class="assoc-hero">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="mb-1">
                <i class="bi bi-person-fill-exclamation me-2" style="color:var(--am-gold);"></i>Cadastros Pendentes
            </h1>
            <p class="mb-0">
                Fichas de adesão ao quadro associativo aguardando avaliação da Diretoria/Presidência.
                <?php if (count($pendentes) > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo count($pendentes); ?> pendente(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-assoc-ghost btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Ver Todos os Associados
        </a>
    </div>
</div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-1"></i>
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pendentes)): ?>
        <div class="assoc-card empty-state">
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
                <h5 class="mt-3 mb-1">Nenhum cadastro pendente</h5>
                <p class="text-muted">Todas as fichas de adesão já foram analisadas.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="assoc-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cadastrosPendentesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Ficha</th>
                                <th>Associado(a)</th>
                                <th>Tipo de Associado</th>
                                <th>CPF</th>
                                <th>Município</th>
                                <th>Submetida em</th>
                                <th class="text-center" style="width:250px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendentes as $p): ?>
                            <tr>
                                <td data-label="Ficha"><?php echo $p['id']; ?></td>
                                <td data-label="Associado(a)">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center text-dark fw-bold"
                                             style="width:36px;height:36px;font-size:.85rem;flex-shrink:0;">
                                            <?php echo mb_strtoupper(mb_substr($p['nome'], 0, 1) . mb_substr($p['sobrenome'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <span class="d-block"><?php echo htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($p['email'] ?? '—'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Tipo de Associado">
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                        <?php echo htmlspecialchars(FichaAdesao::labelTipoAssociado($p['tipo_associado'])); ?>
                                    </span>
                                </td>
                                <td data-label="CPF"><?php echo htmlspecialchars($p['cpf'] ?? '—'); ?></td>
                                <td data-label="Município">
                                    <?php echo htmlspecialchars($p['municipio'] ?? '—'); ?>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($p['estado'] ?? ''); ?></small>
                                </td>
                                <td data-label="Submetida em">
                                    <?php echo !empty($p['data_submissao']) ? date('d/m/Y H:i', strtotime($p['data_submissao'])) : '—'; ?>
                                </td>
                                <td data-label="Ações" class="text-center">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                        <a href="<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=<?php echo (int)$p['id']; ?>"
                                           class="btn btn-outline-primary btn-sm" title="Analisar a ficha completa">
                                            <i class="bi bi-eye me-1"></i> Visualizar
                                        </a>
                                        <form method="POST" class="d-inline"
                                              onsubmit="return confirm('Aprovar a ficha de adesão de <?php echo htmlspecialchars(addslashes($p['nome'] . ' ' . $p['sobrenome'])); ?>? O número de cadastro será gerado automaticamente.');">
                                            <?php echo CSRFProtection::getTokenField(); ?>
                                            <input type="hidden" name="acao" value="aprovar">
                                            <input type="hidden" name="ficha_id" value="<?php echo (int)$p['id']; ?>">
                                            <button class="btn btn-success btn-sm" title="Aprovar">
                                                <i class="bi bi-check-lg me-1"></i> Aprovar
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-recusar-ficha"
                                                data-ficha-id="<?php echo (int)$p['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($p['nome'] . ' ' . $p['sobrenome'], ENT_QUOTES); ?>"
                                                title="Recusar">
                                            <i class="bi bi-x-lg me-1"></i> Recusar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($avaliadas)): ?>
        <div class="assoc-card mt-4">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-clock-history me-1 text-primary"></i> Últimas Avaliações (10)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ficha</th>
                                <th>Associado(a)</th>
                                <th>Nº de Cadastro</th>
                                <th>Resultado</th>
                                <th>Analisada em</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avaliadas as $a): ?>
                            <tr>
                                <td><?php echo $a['id']; ?></td>
                                <td><?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome']); ?></td>
                                <td><?php echo $a['numero_cadastro'] ? htmlspecialchars($a['numero_cadastro']) : '—'; ?></td>
                                <td>
                                    <?php
                                    [$label, $cor] = FichaAdesao::labelStatus($a['status']);
                                    echo '<span class="badge bg-' . $cor . '">' . htmlspecialchars($label) . '</span>';
                                    ?>
                                </td>
                                <td><?php echo !empty($a['data_analise']) ? date('d/m/Y H:i', strtotime($a['data_analise'])) : '—'; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-link">Visualizar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
</div>

<!-- Modal: recusar ficha (parecer) -->
<div class="modal fade" id="modalRecusarFicha" tabindex="-1" aria-labelledby="modalRecusarFichaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formRecusarFicha">
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type="hidden" name="acao" value="recusar">
                <input type="hidden" name="ficha_id" id="recusarFichaId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRecusarFichaLabel"><i class="bi bi-x-octagon text-danger me-2"></i>Recusar Ficha de Adesão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        Recusar a ficha de <strong id="recusarFichaNome"></strong>?
                        O associado verá o parecer no formulário e poderá corrigir e reenviar.
                    </p>
                    <label class="form-label small fw-semibold">Parecer da Diretoria (motivo da recusa) <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="motivo" rows="4" maxlength="2000" required
                              placeholder="Ex.: documentação incompleta, informações divergentes..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg me-1"></i> Confirmar Recusa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#cadastrosPendentesTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        order: [[5, 'asc']],
        pageLength: 25,
        responsive: true
    });

    $('.btn-recusar-ficha').on('click', function () {
        $('#recusarFichaId').val($(this).data('ficha-id'));
        $('#recusarFichaNome').text($(this).data('nome'));
        var modal = new bootstrap.Modal(document.getElementById('modalRecusarFicha'));
        modal.show();
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
