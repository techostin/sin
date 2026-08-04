<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/menu.php';

if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

?>
<div class="container mt-4">
    <h1>Cadastrar Certificadora</h1>
    <form id="formCertificadora">
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Cidade</label><input class="form-control" name="cidade"></div>
            <div class="col-md-2 mb-3"><label class="form-label">UF</label><input class="form-control" name="uf"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Contato / Email</label><input class="form-control" name="contato"></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Site</label>
            <input type="text" name="site" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Logo (upload de arquivo)</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
            <small class="form-text text-muted">Formatos aceitos: PNG, JPG, WEBP. Tamanho máximo: 2MB. A logo será redimensionada para no máximo 400x400px.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Logo (URL pública — opcional)</label>
            <input type="text" name="logo_url" class="form-control" placeholder="https://exemplo.com/logo.png">
            <small class="form-text text-muted">Use apenas se preferir informar uma URL externa em vez de fazer upload.</small>
        </div>
        <button class="btn btn-primary">Salvar</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.getElementById('formCertificadora').addEventListener('submit', function(e){
    e.preventDefault();
    const form = this;
    // Envia como multipart (suporta upload de arquivo). Remover logo_url vazio.
    const fd = new FormData(form);
    if (!fd.get('logo') || fd.get('logo').size === 0) fd.delete('logo');
    if (!fd.get('logo_url')) fd.delete('logo_url');
    fetch('<?php echo SITE_URL; ?>/api/certificadoras.php', {
        method: 'POST',
        body: fd
    }).then(r=>r.json()).then(j=>{
        if (j.success) { alert('Criado com sucesso'); window.location.href = '<?php echo SITE_URL; ?>/certificadoras_list.php'; }
        else alert('Erro: ' + (j.error || JSON.stringify(j)));
    }).catch(err=>{ console.error(err); alert('Erro de requisição'); });
});
</script>