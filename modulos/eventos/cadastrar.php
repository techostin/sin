<?php
$page_title = "Cadastrar Evento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_evento = $_POST['data_evento'] ?? '';
    $local = $_POST['local'] ?? '';
    $status = $_POST['status'] ?? 'Agendado';
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO eventos (nome, descricao, data_evento, local, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $descricao, $data_evento, $local, $status]);
        $mensagem = '<div class="alert alert-success">Evento cadastrado com sucesso!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao cadastrar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Cadastrar Evento</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="data_evento" class="form-label">Data e Hora</label>
                <input type="datetime-local" class="form-control" id="data_evento" name="data_evento" required>
            </div>
            <div class="mb-3">
                <label for="local" class="form-label">Local</label>
                <input type="text" class="form-control" id="local" name="local">
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="Agendado">Agendado</option>
                    <option value="Realizado">Realizado</option>
                    <option value="Cancelado">Cancelado</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 