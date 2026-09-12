<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

$user = new User();

// Proteção de página: se não estiver logado, redireciona para o login
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
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
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/agricultura-familiar.css">
    <link rel="manifest" href="<?php echo SITE_URL; ?>/manifest.json">
    <meta name="theme-color" content="#198754">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SINPORG">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/img/icon-192.png">
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spin {
            display: inline-block;
            animation: spin 1.5s linear infinite;
        }
    </style>
    <script src="<?php echo SITE_URL; ?>/assets/js/masks.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/db_sync.js"></script>
    <script>
        // Expor o sync engine globalmente para páginas que precisam
        document.addEventListener('DOMContentLoaded', function() {
            if (window.syncEngine) {
                window.syncEngine.checkPendingCount(function(count) {
                    var els = ['sync-badge-count', 'sync-badge-count-top'];
                    els.forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) {
                            el.textContent = count;
                            el.style.display = count > 0 ? 'inline' : 'none';
                        }
                    });
                });
            }
        });
    </script>
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
        // Exportar nível do usuário para JS (bottom nav, FAB contextual)
        window.USER_NIVEL = '<?php echo htmlspecialchars($_SESSION['nivel'] ?? '', ENT_QUOTES); ?>';
        // Classe de modo no body (mobile/desktop via resize)
        (function() {
            function applyMode() {
                document.body.classList.toggle('is-mobile', window.innerWidth < 992);
                document.body.classList.toggle('is-desktop', window.innerWidth >= 992);
            }
            document.addEventListener('DOMContentLoaded', applyMode);
            window.addEventListener('resize', applyMode);
        })();
    </script>
    <script src="<?php echo SITE_URL; ?>/assets/js/eventos.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/eventos_modal_debug.js"></script>
    <?php require_once __DIR__ . '/../classes/CSRFProtection.php'; ?>
    <script>
        // Token CSRF global para requisições fetch/AJAX
        window.CSRF_TOKEN = '<?php echo htmlspecialchars(CSRFProtection::getToken(), ENT_QUOTES); ?>';
        // Helper para obter o header CSRF
        window.csrfHeaders = function() {
            return { 'X-CSRF-Token': window.CSRF_TOKEN };
        };
        // Wrapper global: injeta o token CSRF em TODAS as requisições fetch
        // (mesma origem), sem precisar editar cada chamada. Respeita headers
        // já definidos explicitamente.
        (function() {
            const originalFetch = window.fetch.bind(window);
            window.fetch = function(input, init) {
                init = init || {};
                const method = (init.method || (input && input.method) || 'GET').toUpperCase();
                // Apenas métodos de escrita exigem o token no servidor
                if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method) !== -1) {
                    init.headers = init.headers || {};
                    if (!(init.headers instanceof Headers)) {
                        const h = {};
                        if (init.headers) {
                            for (const k in init.headers) h[k] = init.headers[k];
                        }
                        h['X-CSRF-Token'] = window.CSRF_TOKEN;
                        init.headers = h;
                    } else if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.append('X-CSRF-Token', window.CSRF_TOKEN);
                    }
                }
                return originalFetch(input, init);
            };
        })();
    </script>
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

// Modal da Ficha de Adesão ao Quadro Associativo (primeiro acesso).
if ($user->isLoggedIn()) {
    require_once __DIR__ . '/ficha_adesao_modal.php';
}
?>
