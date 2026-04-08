<?php
$page_title = "Página não encontrada";
require_once __DIR__ . '/includes/header.php';
?>
<div class="container mt-5 text-center">
    <h1 class="display-4">404</h1>
    <p class="lead">Ops! A página que você procura não foi encontrada.</p>
    <a href="<?php echo SITE_URL; ?>/" class="btn btn-primary">Voltar para o início</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?> 