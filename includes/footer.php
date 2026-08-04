    </div> <!-- /container-fluid -->
</div>
<!-- /#page-content-wrapper -->
</div>
<!-- /#wrapper -->

<!-- ============================================================
     Bottom Navigation Bar — FORA do #wrapper, posição fixed
     display:none em desktop, display:flex em mobile (<992px)
     via CSS em style.css
     ============================================================ -->
<?php if (isset($bnav_inicio_url)): ?>
<nav id="bottom-nav" role="navigation" aria-label="Navegação principal">

    <a href="<?php echo $bnav_inicio_url; ?>"
       class="bottom-nav-item" data-page="home" aria-label="Início">
        <i class="bi bi-house-door-fill"></i>
        <span>Início</span>
    </a>

    <?php if (empty($bnav_is_gestor)): ?>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/dashboard-agricultor"
       class="bottom-nav-item" data-page="campo" aria-label="Caderno de Campo">
        <i class="bi bi-book-fill"></i>
        <span>Caderno</span>
    </a>
    <?php else: ?>
    <a href="<?php echo SITE_URL; ?>/associados"
       class="bottom-nav-item" data-page="gestao" aria-label="Associados">
        <i class="bi bi-people-fill"></i>
        <span>Gestão</span>
    </a>
    <?php endif; ?>

    <?php if (empty($bnav_is_gestor)): ?>
    <a href="<?php echo SITE_URL; ?>/atividade-diaria/novo"
       class="bottom-nav-item fab" data-page="novo" aria-label="Nova Atividade">
        <div class="fab-circle"><i class="bi bi-plus-lg"></i></div>
        <span>Novo</span>
    </a>
    <?php else: ?>
    <a href="<?php echo SITE_URL; ?>/relatorios"
       class="bottom-nav-item fab" data-page="relatorios" aria-label="Relatórios">
        <div class="fab-circle"><i class="bi bi-bar-chart-fill"></i></div>
        <span>Relatórios</span>
    </a>
    <?php endif; ?>

    <a href="<?php echo SITE_URL; ?>/eventos"
       class="bottom-nav-item" data-page="eventos" aria-label="Eventos">
        <i class="bi bi-calendar-event-fill"></i>
        <span>Eventos</span>
    </a>

    <button type="button" class="bottom-nav-item" data-page="mais"
            id="bnav-mais-btn" aria-label="Mais opções">
        <i class="bi bi-grid-3x3-gap-fill"></i>
        <span id="bnav-sync-label">Mais</span>
        <span id="bnav-sync-badge" class="bottom-nav-badge" aria-hidden="true"></span>
    </button>

</nav>
<?php endif; ?>

<?php if (isset($fab_action) && isset($fab_label)): ?>
<!-- FAB Global — visível apenas em mobile, quando a página define $fab_action -->
<button id="fab-global-trigger"
        onclick="window.location.href='<?php echo htmlspecialchars($fab_action, ENT_QUOTES); ?>'"
        title="<?php echo htmlspecialchars($fab_label, ENT_QUOTES); ?>"
        aria-label="<?php echo htmlspecialchars($fab_label, ENT_QUOTES); ?>">
    <i class="bi bi-plus-lg"></i>
</button>
<?php endif; ?>

<!-- Toast Container (usado pelo db_sync.js) -->
<div class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3" id="toast-container" style="z-index: 2000; margin-bottom: calc(var(--bottom-nav-height, 64px) + 0.5rem);">
</div>

<!-- Rodapé Global -->
<footer class="text-center text-white-50 py-3 mt-4" style="background:rgba(0,0,0,0.3); font-size:0.8rem; border-top:1px solid rgba(255,255,255,0.08);">
    <?php echo SITE_TITLE; ?> &mdash; Sistema Integrado da Produção Orgânica<br>
    &copy; <?php echo date('Y'); ?> Edmildon L. Santos. Todos os direitos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/masks.js"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/eventos.js"></script>
<script>
// Definir variável global para SITE_URL
window.SITE_URL = '<?php echo SITE_URL; ?>';

// Toggle para o menu lateral
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

// ── Bottom Nav JS (o elemento #bottom-nav já está no DOM aqui) ──
(function() {
    var overlay = document.getElementById('sidebar-overlay');

    // Botão "Mais" abre/fecha sidebar
    var bnavMais = document.getElementById('bnav-mais-btn');
    if (bnavMais) {
        bnavMais.addEventListener('click', function() {
            document.getElementById('wrapper').classList.toggle('toggled');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    // Marcar item ativo pela URL
    var path = window.location.pathname;
    document.querySelectorAll('.bottom-nav-item[data-page]').forEach(function(item) {
        var page = item.dataset.page;
        var active = false;
        if (page === 'home'       && (path === '/' || path.includes('dashboard'))) active = true;
        if (page === 'campo'      && (path.includes('atividade') || path.includes('caderno-campo'))) active = true;
        if (page === 'gestao'     && path.includes('associados')) active = true;
        if (page === 'eventos'    && path.includes('eventos'))    active = true;
        if (page === 'relatorios' && path.includes('relatorio'))  active = true;
        if (active) item.classList.add('active');
    });

    // Sync badge count
    if (window.syncEngine && typeof window.syncEngine.checkPendingCount === 'function') {
        var checkBadge = function() {
            window.syncEngine.checkPendingCount(function(count) {
                var badge = document.getElementById('bnav-sync-badge');
                var label = document.getElementById('bnav-sync-label');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.add('visible');
                        if (label) label.textContent = count + ' pend.';
                    } else {
                        badge.classList.remove('visible');
                        if (label) label.textContent = 'Mais';
                    }
                }
            });
        };
        setTimeout(checkBadge, 1200);
        window.addEventListener('online', function() { setTimeout(checkBadge, 500); });
    }
})();
</script>
</body>
</html>
