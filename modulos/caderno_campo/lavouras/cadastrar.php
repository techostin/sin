<?php
$page_title = "Cadastrar Lavoura";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';

$message = '';
$message_type = '';

// Apenas usuários com nível de Gestor ou superior podem cadastrar
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$propriedade_obj = new Propriedade();
if ($user->hasPermission(NIVEL_GESTOR)) {
    $propriedades = $propriedade_obj->getAll();
} else {
    $associado_id = $user->getAssociadoId();
    if (!$associado_id) $associado_id = -1;
    $propriedades = $propriedade_obj->getByAssociadoId($associado_id);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lavoura = new Lavoura();
    
    // O método create agora lida com a sanitização e retorna o erro específico
    $resultado = $lavoura->create($_POST);

    if ($resultado === true) {
        $message = "Lavoura cadastrada com sucesso!";
        $message_type = "success";
    } else {
        $message = $resultado; // A mensagem de erro vem diretamente do método create()
        $message_type = "danger";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Cadastrar Nova Lavoura</h1>
    <a href="<?php echo SITE_URL; ?>/lavouras" class="btn btn-secondary">Voltar para Lista</a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="<?php echo SITE_URL; ?>/caderno-campo/lavoura/novo" method="POST" class="row g-3">
    
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome da Lavoura</label>
        <input type="text" class="form-control" id="nome" name="nome" required placeholder="Ex: Talhão 01, Safra de Soja 2024">
    </div>
    <div class="col-md-6">
        <label for="propriedade_id" class="form-label">Propriedade</label>
        <select class="form-select" id="propriedade_id" name="propriedade_id" required>
            <option value="">Selecione uma Propriedade</option>
            <?php foreach ($propriedades as $prop): ?>
                <option value="<?php echo htmlspecialchars($prop['id']); ?>"><?php echo htmlspecialchars($prop['propriedade']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label for="cultura" class="form-label">Cultura</label>
        <input type="text" class="form-control" id="cultura" name="cultura" required placeholder="Ex: Soja, Milho, Café">
    </div>
    <div class="col-md-3">
        <label for="area" class="form-label">Área (em hectares)</label>
        <input type="number" step="0.01" class="form-control" id="area" name="area" required placeholder="Ex: 15.5">
    </div>

    <div class="col-md-3">
        <label for="data_plantio" class="form-label">Data de Plantio</label>
        <input type="date" class="form-control" id="data_plantio" name="data_plantio" required>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Lavoura</button>
        <a href="<?php echo SITE_URL; ?>/lavouras" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
