<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Caixa.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}
$podeGerenciarCaixa = $user->hasPermission(NIVEL_GESTOR);
// Botão "voltar" para o Dashboard da Diretoria: mesma permissão exigida pela
// propria pagina (/dashboard/diretoria) — Admin ou membro do quadro diretivo.
$podeAcessarDashboardDiretoria = $user->podeAcessarDiretoria();

$f = [
    'associacao_id' => (int) ($_GET['associacao_id'] ?? 0),
    'tipo' => trim((string) ($_GET['tipo'] ?? '')),
    'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
    'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
];

$caixaModel = new Caixa();
$itens = $caixaModel->getAll(array_filter($f));
$resumo = $caixaModel->getSaldo($f['associacao_id'] ?: null);
$filtrados = array_filter($f);
if (!empty($f['data_inicio']) || !empty($f['data_fim'])) {
    $resumoPeriodo = $caixaModel->getResumoPeriodo(
        $f['data_inicio'] ?: '1970-01-01',
        $f['data_fim'] ?: date('Y-m-d'),
        $f['associacao_id'] ?: null
    );
} else {
    $resumoPeriodo = null;
}

$associacoes = (new Associacao())->getAll();

$page_title = "Caixa";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-bank text-primary me-2"></i>Caixa</h1>
            <span class="text-muted">Fluxo de entradas e saídas das associações.</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($podeAcessarDashboardDiretoria): ?>
            <a href="<?php echo SITE_URL; ?>/dashboard/diretoria" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Dashboard da Diretoria</a>
            <?php endif; ?>
            <?php if ($podeGerenciarCaixa): ?>
            <a href="<?php echo SITE_URL; ?>/caixa/lancamento" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Novo Lançamento</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Mensalidades</a>
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

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-success shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Entradas</div>
                    <div class="fs-4 fw-bold text-success">+ R$ <?php echo number_format($resumo['entradas'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Saídas</div>
                    <div class="fs-4 fw-bold text-danger">- R$ <?php echo number_format($resumo['saidas'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase">Saldo Geral</div>
                    <div class="fs-4 fw-bold text-<?php echo $resumo['saldo'] >= 0 ? 'primary' : 'danger'; ?>">R$ <?php echo number_format($resumo['saldo'], 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-dark shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small text-uppercase"><?php echo $resumoPeriodo ? 'Saldo no Filtro' : 'Lançamentos'; ?></div>
                    <?php if ($resumoPeriodo): ?>
                        <div class="fs-4 fw-bold">R$ <?php echo number_format($resumoPeriodo['saldo_periodo'], 2, ',', '.'); ?></div>
                    <?php else: ?>
                        <div class="fs-4 fw-bold"><?php echo count($itens); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtros</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/caixa" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Associação</label>
                    <select name="associacao_id" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $f['associacao_id'] === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="Entrada" <?php echo $f['tipo'] === 'Entrada' ? 'selected' : ''; ?>>Entrada</option>
                        <option value="Saida" <?php echo $f['tipo'] === 'Saida' ? 'selected' : ''; ?>>Saída</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">De</label>
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?php echo $f['data_inicio']; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Até</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?php echo $f['data_fim']; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-1"></i>Lançamentos</span>
            <span class="badge bg-light text-dark"><?php echo count($itens); ?></span>
        </div>
        <div class="card-body table-responsive">
            <table id="caixaTable" class="table table-striped table-hover small">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Associação</th>
                        <th>Valor</th>
                        <?php if ($podeGerenciarCaixa): ?><th class="text-end">Ações</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($itens)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Nenhum lançamento encontrado.</td></tr>
                <?php else: foreach ($itens as $l): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($l['data'])); ?></td>
                        <td><span class="badge bg-<?php echo $l['tipo'] === 'Entrada' ? 'success' : 'danger'; ?>"><?php echo $l['tipo']; ?></span></td>
                        <td><?php echo htmlspecialchars($l['descricao']); ?></td>
                        <td><?php echo $l['categoria'] ? htmlspecialchars($l['categoria']) : '—'; ?></td>
                        <td><?php echo $l['associacao_sigla'] ? htmlspecialchars($l['associacao_sigla']) : ($l['associacao_nome'] ? htmlspecialchars($l['associacao_nome']) : '—'); ?></td>
                        <td class="fw-bold text-<?php echo $l['tipo'] === 'Entrada' ? 'success' : 'danger'; ?>">R$ <?php echo number_format((float)$l['valor'], 2, ',', '.'); ?></td>
                        <?php if ($podeGerenciarCaixa): ?>
                        <td class="text-end text-nowrap">
                            <form action="<?php echo SITE_URL; ?>/caixa/<?php echo $l['id']; ?>/excluir" method="post" style="display:inline;" onsubmit="return confirm('Excluir este lançamento de caixa?')">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <button class="btn btn-outline-danger btn-sm" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#caixaTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [{ "targets": -1, "orderable": false }]
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>