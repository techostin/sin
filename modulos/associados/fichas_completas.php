<?php
/**
 * Todas as Fichas de Adesão — listagem completa para Admin/Diretoria.
 * DataTable com todas as fichas (pendentes, aprovadas, recusadas, rascunho).
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/FichaAdesao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$page_title = "Todas as Fichas de Adesão";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

$user = new User();

// Apenas Admin do sistema e Diretoria Executiva (Presidente, Diretor, Secretário, Tesoureiro)
if (!$user->hasPermission(NIVEL_ADMIN) && !$user->isDiretoriaExecutiva()) {
    echo "<div class='alert alert-danger m-4'>Acesso negado. Apenas administradores do sistema e membros da Diretoria Executiva (Presidente, Diretor, Secretário, Tesoureiro) podem visualizar todas as fichas de adesão.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$model = new FichaAdesao();

// Buscar todas as fichas com filtro de status opcional
$statusFilter = $_GET['status'] ?? '';
$where = '';
$params = [];
if ($statusFilter && in_array($statusFilter, ['pendente', 'aprovada', 'recusada', 'rascunho'], true)) {
    $where = 'WHERE f.status = ?';
    $params[] = $statusFilter;
}

try {
    $sql = "
        SELECT f.*, a.nome, a.sobrenome, a.email, a.telefone, a.foto_url
        FROM ficha_adesao f
        INNER JOIN associados a ON a.id = f.associado_id
        {$where}
        ORDER BY f.data_submissao DESC, f.id DESC
    ";
    $stmt = Database::getInstance()->getConnection()->prepare($sql);
    $stmt->execute($params);
    $todasFichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('FichasCompletas::listar - ' . $e->getMessage());
    $todasFichas = [];
}

// Contadores por status
$contadores = ['pendente' => 0, 'aprovada' => 0, 'recusada' => 0, 'rascunho' => 0, 'total' => 0];
foreach ($todasFichas as $f) {
    $contadores['total']++;
    if (isset($contadores[$f['status']])) {
        $contadores[$f['status']]++;
    }
}
?>

<div class="assoc-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/associados-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<div class="assoc-hero">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="mb-1">
                <i class="bi bi-person-vcard-fill me-2" style="color:var(--am-gold);"></i>Todas as Fichas de Adesão
            </h1>
            <p class="mb-0">
                Listagem completa de fichas de adesão ao quadro associativo.
            </p>
        </div>
        <a href="<?php echo SITE_URL; ?>/cadastros-pendentes" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-hourglass-split me-1"></i> Apenas Pendentes
        </a>
    </div>
</div>

<!-- Filtros de Status -->
<div class="assoc-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold small">Filtrar por status:</span>
            <a href="<?php echo SITE_URL; ?>/fichas-completas" class="btn btn-sm <?php echo $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <i class="bi bi-list-ul me-1"></i> Todas <span class="badge bg-secondary ms-1"><?php echo $contadores['total']; ?></span>
            </a>
            <a href="<?php echo SITE_URL; ?>/fichas-completas?status=pendente" class="btn btn-sm <?php echo $statusFilter === 'pendente' ? 'btn-warning text-dark' : 'btn-outline-warning'; ?>">
                <i class="bi bi-hourglass-split me-1"></i> Pendentes <span class="badge bg-warning text-dark ms-1"><?php echo $contadores['pendente']; ?></span>
            </a>
            <a href="<?php echo SITE_URL; ?>/fichas-completas?status=aprovada" class="btn btn-sm <?php echo $statusFilter === 'aprovada' ? 'btn-success' : 'btn-outline-success'; ?>">
                <i class="bi bi-check-circle me-1"></i> Aprovadas <span class="badge bg-success ms-1"><?php echo $contadores['aprovada']; ?></span>
            </a>
            <a href="<?php echo SITE_URL; ?>/fichas-completas?status=recusada" class="btn btn-sm <?php echo $statusFilter === 'recusada' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                <i class="bi bi-x-circle me-1"></i> Recusadas <span class="badge bg-danger ms-1"><?php echo $contadores['recusada']; ?></span>
            </a>
            <a href="<?php echo SITE_URL; ?>/fichas-completas?status=rascunho" class="btn btn-sm <?php echo $statusFilter === 'rascunho' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                <i class="bi bi-pencil me-1"></i> Rascunho <span class="badge bg-secondary ms-1"><?php echo $contadores['rascunho']; ?></span>
            </a>
        </div>
    </div>
</div>

<?php if (empty($todasFichas)): ?>
    <div class="assoc-card empty-state">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-text text-muted" style="font-size:3rem;"></i>
            <h5 class="mt-3 mb-1">Nenhuma ficha encontrada</h5>
            <p class="text-muted">Não há fichas de adesão cadastradas.</p>
        </div>
    </div>
<?php else: ?>
    <div class="assoc-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="todasFichasTable">
                    <thead class="table-light">
                        <tr>
                            <th>Ficha</th>
                            <th>Associado(a)</th>
                            <th>Tipo de Associado</th>
                            <th>CPF</th>
                            <th>Município</th>
                            <th>Status</th>
                            <th>Submetida em</th>
                            <th>Analisada em</th>
                            <th class="text-center" style="width:180px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todasFichas as $f): ?>
                        <tr>
                            <td data-label="Ficha"><?php echo $f['id']; ?></td>
                            <td data-label="Associado(a)">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
                                         style="width:36px;height:36px;font-size:.85rem;flex-shrink:0;">
                                        <?php echo mb_strtoupper(mb_substr($f['nome'], 0, 1) . mb_substr($f['sobrenome'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <span class="d-block"><?php echo htmlspecialchars($f['nome'] . ' ' . $f['sobrenome']); ?></span>
                                        <small class="text-muted"><?php echo htmlspecialchars($f['email'] ?? '—'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Tipo de Associado">
                                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                    <?php echo htmlspecialchars(FichaAdesao::labelTipoAssociado($f['tipo_associado'])); ?>
                                </span>
                            </td>
                            <td data-label="CPF"><?php echo htmlspecialchars($f['cpf'] ?? '—'); ?></td>
                            <td data-label="Município">
                                <?php echo htmlspecialchars($f['municipio'] ?? '—'); ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars($f['estado'] ?? ''); ?></small>
                            </td>
                            <td data-label="Status">
                                <?php
                                [$label, $cor] = FichaAdesao::labelStatus($f['status']);
                                echo '<span class="badge bg-' . $cor . '">' . htmlspecialchars($label) . '</span>';
                                ?>
                            </td>
                            <td data-label="Submetida em">
                                <?php echo !empty($f['data_submissao']) ? date('d/m/Y H:i', strtotime($f['data_submissao'])) : '<span class="text-muted">—</span>'; ?>
                            </td>
                            <td data-label="Analisada em">
                                <?php echo !empty($f['data_analise']) ? date('d/m/Y H:i', strtotime($f['data_analise'])) : '<span class="text-muted">—</span>'; ?>
                            </td>
                            <td data-label="Ações" class="text-center">
                                <div class="d-inline-flex gap-1 flex-wrap justify-content-center">
                                    <a href="<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=<?php echo (int)$f['id']; ?>"
                                       class="btn btn-outline-primary btn-sm" title="Visualizar ficha completa">
                                        <i class="bi bi-eye me-1"></i> Ver
                                    </a>
                                    <?php if ($f['status'] === 'pendente'): ?>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Aprovar a ficha de adesão de <?php echo htmlspecialchars(addslashes($f['nome'] . ' ' . $f['sobrenome'])); ?>? O número de cadastro será gerado automaticamente.');">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <input type="hidden" name="acao" value="aprovar">
                                        <input type="hidden" name="ficha_id" value="<?php echo (int)$f['id']; ?>">
                                        <button class="btn btn-success btn-sm" title="Aprovar">
                                            <i class="bi bi-check-lg me-1"></i> Aprovar
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-recusar-ficha"
                                            data-ficha-id="<?php echo (int)$f['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($f['nome'] . ' ' . $f['sobrenome'], ENT_QUOTES); ?>"
                                            title="Recusar">
                                        <i class="bi bi-x-lg me-1"></i> Recusar
                                    </button>
                                    <?php endif; ?>
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
    $('#todasFichasTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
        },
        order: [[5, 'desc']],
        pageLength: 25,
        responsive: true,
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ]
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