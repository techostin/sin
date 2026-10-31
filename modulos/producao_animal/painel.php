<?php
$page_title = "Painel de Produção Animal";
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ProducaoAnimal.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$nivelAtual = (int)($_SESSION['user_nivel'] ?? 0);
$isGestor   = $user->hasPermission(NIVEL_GESTOR);
$assoc_id   = $user->getAssociadoId();

$paClass = new ProducaoAnimal();
$stats  = $paClass->estatisticas($isGestor ? null : $assoc_id);

$rebanho = [
    ['Animais ativos', number_format($stats['animais_ativos']), 'bi-egg-fill', 'bg-success', '/manejo-animal'],
    ['Total no rebanho', number_format($stats['animais_total']), 'bi-collection', 'bg-primary', '/manejo-animal'],
    ['Lotes', number_format($stats['lotes']), 'bi-boxes', 'bg-info', '/producao-animal/lotes'],
    ['Cabeças em lotes', number_format($stats['cabecas_lotes']), 'bi-grid-3x3', 'bg-secondary', '/producao-animal/lotes'],
    ['Registros de sanidade', number_format($stats['sanidade']), 'bi-heart-pulse', 'bg-danger', '/producao-animal/sanidade'],
    ['Eventos reprodutivos', number_format($stats['reproducao']), 'bi-stars', 'bg-warning text-dark', '/producao-animal/reproducao'],
    ['Sendo orgânicos', '—', 'bi-check-circle', 'bg-success', '/manejo-animal'],
    ['Em conversão/convencional', number_format($stats['convencionais']), 'bi-hourglass-split', 'bg-warning text-dark', '/manejo-animal'],
];

echo '';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<h1 class="mt-4"><i class="bi bi-egg-fill text-success me-2"></i>Painel de Produção Animal</h1>
<p class="text-muted">Acompanhe rebanho, lotes, alimentação, sanidade e reprodução conforme a Portaria MAPA 52/2021.</p>

<div class="row mb-4">
    <?php foreach ($rebanho as $c): ?>
    <div class="col-6 col-md-4 col-lg-3 mb-3">
        <a href="<?php echo SITE_URL . $c[4]; ?>" class="text-decoration-none">
            <div class="card <?php echo $c[3]; ?> text-white h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi <?php echo $c[2]; ?> display-4"></i>
                    <div class="fs-3 fw-bold"><?php echo $c[1]; ?></div>
                    <div class="small"><?php echo $c[0]; ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-question-circle text-success me-1"></i> Conformidade Animal (Portaria 52/2021)</div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li><strong>Nutrição (art. 34-37):</strong> alimentação da própria unidade ou orgânica; exceção aprovada no PMO até 15% MS (ruminantes) / 20% MS (não ruminantes).</li>
                    <li><strong>Ambiente (art. 38-44):</strong> acesso a áreas externas, sombreamento e densidades máximas por espécie.</li>
                    <li><strong>Sanidade (art. 59-63):</strong> vacinação e prevenção; tratamento excepcional com carência dobrada (≥96h) e aprovação da OCS, máx. 2 tratamentos/ano.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-speedometer2 text-success me-1"></i> Ações rápidas</div>
            <div class="card-body">
                <a href="<?php echo SITE_URL; ?>/manejo-animal/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Cadastrar animal</a>
                <a href="<?php echo SITE_URL; ?>/producao-animal/lote/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Novo lote</a>
                <a href="<?php echo SITE_URL; ?>/producao-animal/alimentacao/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Registrar alimentação</a>
                <a href="<?php echo SITE_URL; ?>/producao-animal/sanidade/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Registrar sanidade</a>
                <a href="<?php echo SITE_URL; ?>/producao-animal/reproducao/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Registrar reprodução</a>
                <a href="<?php echo SITE_URL; ?>/producao-animal/ambiente/novo" class="btn btn-outline-success btn-sm mb-2"><i class="bi bi-plus-circle"></i> Cadastrar ambiente</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>