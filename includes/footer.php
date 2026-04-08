    </div> <!-- /container-fluid -->
</div>
<!-- /#page-content-wrapper -->
</div>
<!-- /#wrapper -->

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
</script>
</body>
</html>
