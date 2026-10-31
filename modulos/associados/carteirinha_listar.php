<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Carteirinha.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}
$podeGerenciarCarteirinhas = $user->hasPermission(NIVEL_GESTOR);

$f = [
    'busca' => trim((string) ($_GET['busca'] ?? '')),
];

$associadoModel = new Associado();
$associados = $associadoModel->getAll();

if (!empty($f['busca'])) {
    $b = $f['busca'];
    $associados = array_filter($associados, function ($a) use ($b) {
        return stripos(($a['nome'] ?? '') . ' ' . ($a['sobrenome'] ?? '') . ' ' . ($a['email'] ?? ''), $b) !== false;
    });
}

$mensalidadeModel = new Mensalidade();
$carteirinhaModel = new Carteirinha();

foreach ($associados as &$a) {
    $a['financeiro'] = $mensalidadeModel->getResumoFinanceiro((int) $a['id']);
    $a['carteirinha'] = $carteirinhaModel->getByAssociado((int) $a['id']);
}
unset($a);

$page_title = "Carteirinhas dos Associados";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-credit-card-2-front text-success me-2"></i>Carteirinhas dos Associados</h1>
            <span class="text-muted">Geração, impressão e envio por e-mail das carteirinhas.</span>
        </div>
        <div class="d-flex gap-2">
            <?php if ($podeGerenciarCarteirinhas): ?>
            <button type="button" class="btn btn-secondary btn-sm" id="btnSelecionarTodos"><i class="bi bi-check-all me-1"></i>Selecionar todos</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnEnviarSelecionados"><i class="bi bi-envelope-paper me-1"></i>Enviar por e-mail (selecionados)</button>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtrar</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/associados/carteirinhas" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por nome ou e-mail..."
                        value="<?php echo htmlspecialchars($f['busca']); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i>Associados (<?php echo count($associados); ?>)</span>
            <span class="badge bg-light text-dark"><?php echo count(array_filter($associados, fn($a) => !empty($a['email']))); ?> com e-mail</span>
        </div>
        <div class="card-body table-responsive">
            <form method="post" action="<?php echo SITE_URL; ?>/modulos/associados/carteirinhas_enviar_massa.php" id="formEnvioMassa">
                <?php echo CSRFProtection::getTokenField(); ?>
                <table id="carteirinhasTable" class="table table-striped table-hover small">
                    <thead>
                        <tr>
                            <?php if ($podeGerenciarCarteirinhas): ?>
                            <th class="text-center"><input type="checkbox" id="cbTodos" title="Selecionar todos"></th>
                            <?php endif; ?>
                            <th>Associado</th>
                            <th>E-mail</th>
                            <th>Categoria</th>
                            <th>Situação</th>
                            <th class="text-center">Financeiro</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($associados)): ?>
                        <tr><td colspan="7" class="text-center text-muted">Nenhum associado encontrado.</td></tr>
                    <?php else: foreach ($associados as $a): ?>
                        <tr>
                            <?php if ($podeGerenciarCarteirinhas): ?>
                            <td class="text-center">
                                <input type="checkbox" class="cbAssociado" name="associados[]" value="<?php echo (int) $a['id']; ?>" <?php echo empty($a['email']) ? 'disabled title="Sem e-mail"' : ''; ?>>
                            </td>
                            <?php endif; ?>
                            <td class="fw-bold"><?php echo htmlspecialchars(($a['nome'] ?? '') . ' ' . ($a['sobrenome'] ?? '')); ?></td>
                            <td><?php echo !empty($a['email']) ? htmlspecialchars($a['email']) : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($a['tipo_associado'] ?? 'Efetivo')); ?></td>
                            <td>
                                <?php
                                $st = $a['status_associado'] ?? 'Ativo';
                                $cor = in_array($st, ['Ativo'], true) ? 'success' : (in_array($st, ['Inadimplente', 'Afastado'], true) ? 'warning text-dark' : 'secondary');
                                ?>
                                <span class="badge bg-<?php echo $cor; ?>"><?php echo htmlspecialchars(ucfirst($st)); ?></span>
                            </td>
                            <td class="text-center text-nowrap">
                                <?php if ((int)($a['financeiro']['qtd_em_aberto'] ?? 0) > 0): ?>
                                    <span class="badge bg-danger" title="Em aberto">R$ <?php echo number_format((float)($a['financeiro']['total_em_aberto'] ?? 0), 2, ',', '.'); ?> em aberto</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Em dia</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?php echo SITE_URL; ?>/associado/<?php echo (int) $a['id']; ?>/carteirinha-impressa" target="_blank" class="btn btn-outline-dark btn-sm" title="Imprimir/PDF completo"><i class="bi bi-printer"></i></a>
                                <?php if ($podeGerenciarCarteirinhas && !empty($a['email'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/associado/<?php echo (int) $a['id']; ?>/carteirinha-enviar" class="btn btn-outline-primary btn-sm" title="Enviar por e-mail" onclick="return false;" data-associado="<?php echo (int) $a['id']; ?>"><i class="bi bi-envelope-paper"></i></a>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-envelope-slash"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#carteirinhasTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [{ "targets": [0, -1], "orderable": false }]
    });

    $('#cbTodos').on('change', function () {
        $('.cbAssociado:not(:disabled)').prop('checked', this.checked);
    });
    $('#btnSelecionarTodos').on('click', function () {
        $('.cbAssociado:not(:disabled)').prop('checked', true);
        $('#cbTodos').prop('checked', true);
    });
    $('#btnEnviarSelecionados').on('click', function () {
        const n = $('.cbAssociado:checked').length;
        if (n === 0) {
            alert('Selecione ao menos um associado com e-mail.');
            return;
        }
        if (!confirm('Enviar a carteirinha por e-mail para ' + n + ' associado(s)?')) return;
        $('#formEnvioMassa').submit();
    });

    $('a[data-associado]').on('click', function () {
        const id = $(this).data('associado');
        if (confirm('Enviar a carteirinha por e-mail deste associado?')) {
            $('<form method="post" action="<?php echo SITE_URL; ?>/associado/' + id + '/carteirinha-enviar">' +
                '<input type="hidden" name="csrf_token" value="<?php echo CSRFProtection::getToken(); ?>"></form>').appendTo('body').submit();
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>