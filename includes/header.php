<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

$user = new User();

// Proteção de página: se não estiver logado, redireciona para o login
if (!$user->isLoggedIn()) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_TITLE : SITE_TITLE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/agricultura-familiar.css">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <script src="<?php echo SITE_URL; ?>/assets/js/masks.js"></script>
    <script>
        // Registrar Service Worker para PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo SITE_URL; ?>/sw.js')
                    .then(reg => console.log('SW registrado', reg))
                    .catch(err => console.log('Erro SW', err));
            });
        }
    </script>
    <script>
        // Definir a URL base do site globalmente
        window.SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/eventos.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/eventos_modal_debug.js"></script>
    <?php if (isset($_SESSION['mostrar_modal_eventos']) && $_SESSION['mostrar_modal_eventos'] === true): ?>
    <script>
        // Definir variável global para o JavaScript saber que deve mostrar o modal
        window.temEventosPendentes = true;
        
        // Remover a flag para não mostrar novamente
        fetch('<?php echo SITE_URL; ?>/api/limpar_modal_eventos.php', {
            method: 'POST',
            credentials: 'same-origin'
        });
    </script>
    <?php endif; ?>
</head>
<body>
<div class="d-flex" id="wrapper">

<?php
// Incluir lembretes apenas se o usuário estiver logado
if ($user->isLoggedIn()) {
    require_once __DIR__ . '/lembretes.php';
}
?>
