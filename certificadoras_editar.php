<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/menu.php';

if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) { echo "<div class='alert alert-danger'>ID não informado</div>"; require_once __DIR__ . '/includes/footer.php'; exit; }

// Buscar dados
// Usar fetch no cliente JS; aqui apenas template
?>
<div class="container mt-4">
    <h1>Editar Certificadora</h1>
    <form id="formEdit">
        <input type="hidden" name="id" id="inputId">
        <div class="mb-3">
            <label class="form-label">Nome</label>
            <input type="text" id="inputNome" name="nome" class="form-control" required>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label">Cidade</label><input id="inputCidade" class="form-control" name="cidade"></div>
            <div class="col-md-2 mb-3"><label class="form-label">UF</label><input id="inputUf" class="form-control" name="uf"></div>
            <div class="col-md-6 mb-3"><label class="form-label">Contato / Email</label><input id="inputContato" class="form-control" name="contato"></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Site</label>
            <input id="inputSite" type="text" name="site" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Logo (upload de arquivo)</label>
            <input type="file" name="logo" id="inputLogoFile" class="form-control" accept="image/png,image/jpeg,image/webp">
            <small class="form-text text-muted">Formatos aceitos: PNG, JPG, WEBP. Tamanho máximo: 2MB. Deixe em branco para manter a logo atual.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Logo (URL pública — opcional)</label>
            <input id="inputLogo" type="text" name="logo_url" class="form-control">
        </div>
        <button class="btn btn-primary">Salvar</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
const apiUrl = '<?php echo SITE_URL; ?>/api/certificadoras.php';
const id = new URLSearchParams(window.location.search).get('id');
if (!id) { alert('ID ausente'); window.location.href = '<?php echo SITE_URL; ?>/certificadoras_list.php'; }
fetch(apiUrl + '?id=' + id).then(r=>r.json()).then(data=>{
    const c = data;
    document.getElementById('inputId').value = c.id;
    document.getElementById('inputNome').value = c.nome || '';
    document.getElementById('inputCidade').value = c.cidade || '';
    document.getElementById('inputUf').value = c.uf || '';
    document.getElementById('inputContato').value = c.contato || c.email || '';
    document.getElementById('inputSite').value = c.site || '';
    document.getElementById('inputLogo').value = c.logo_url || c.logo_filename || '';
}).catch(err=>{ console.error(err); alert('Erro ao carregar'); });

document.getElementById('formEdit').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('_method', 'PUT');
    if (!fd.get('logo') || fd.get('logo').size === 0) fd.delete('logo');
    if (fd.get('logo_url') === '') fd.delete('logo_url');
    fd.append('csrf_token', window.CSRF_TOKEN);
    fetch(apiUrl, { method: 'POST', headers: window.csrfHeaders(), body: fd })
        .then(r=>r.json()).then(j=>{ if (j.success) { alert('Atualizado'); window.location.href = '<?php echo SITE_URL; ?>/certificadoras_list.php'; } else alert('Erro: ' + JSON.stringify(j)); })
        .catch(err=>{ console.error(err); alert('Erro'); });
});
</script>