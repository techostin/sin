<?php
$page_title = "Editar Evento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$evento) {
        echo "<div class='alert alert-danger'>Evento não encontrado.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao buscar evento: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $data_evento = $_POST['data_evento'] ?? '';
    $local = $_POST['local'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $status = $_POST['status'] ?? 'Agendado';
    
    try {
        $stmt = $db->prepare("UPDATE eventos SET nome=?, data_evento=?, local=?, descricao=?, status=? WHERE id=?");
        $stmt->execute([$nome, $data_evento, $local, $descricao, $status, $id]);
        $mensagem = '<div class="alert alert-success">Evento atualizado com sucesso!</div>';
        $evento['nome'] = $nome;
        $evento['data_evento'] = $data_evento;
        $evento['local'] = $local;
        $evento['descricao'] = $descricao;
        $evento['status'] = $status;
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Editar Evento</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($evento['nome']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="data_evento" class="form-label">Data</label>
                <input type="date" class="form-control" id="data_evento" name="data_evento" value="<?php echo isset($evento['data_evento']) ? date('Y-m-d', strtotime($evento['data_evento'])) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="local" class="form-label">Local</label>
                <input type="text" class="form-control" id="local" name="local" value="<?php echo htmlspecialchars($evento['local']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($evento['descricao']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status do Evento</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="Agendado" <?php echo (isset($evento['status']) && $evento['status'] === 'Agendado') ? 'selected' : ''; ?>>Agendado</option>
                    <option value="Realizado" <?php echo (isset($evento['status']) && $evento['status'] === 'Realizado') ? 'selected' : ''; ?>>Realizado</option>
                    <option value="Cancelado" <?php echo (isset($evento['status']) && $evento['status'] === 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                </select>
                <div class="form-text">Eventos marcados como 'Realizado' ou 'Cancelado' não serão exibidos nos lembretes.</div>
            </div>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>