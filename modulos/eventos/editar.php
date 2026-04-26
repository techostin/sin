<?php
$page_title = "Editar Evento";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Evento.php';

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

$objEvento = new Evento();
$objAssociado = new Associado();
$todosAssociados = $objAssociado->getAll();
$presencasAtuais = $objEvento->getPresencasByEvento($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $data_evento = $_POST['data_evento'] ?? '';
    $local = $_POST['local'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $status = $_POST['status'] ?? 'Agendado';
    
    try {
        $stmt = $db->prepare("UPDATE eventos SET nome=?, data_evento=?, local=?, descricao=?, status=? WHERE id=?");
        $stmt->execute([$nome, $data_evento, $local, $descricao, $status, $id]);
        
        // Processar presenças
        $presencas_post = $_POST['presenca'] ?? [];
        $objEvento->atualizarPresencas($id, $presencas_post);
        
        $mensagem = '<div class="alert alert-success">Evento e lista de presença atualizados com sucesso!</div>';
        $evento['nome'] = $nome;
        $evento['data_evento'] = $data_evento;
        $evento['local'] = $local;
        $evento['descricao'] = $descricao;
        $evento['status'] = $status;
        
        // Atualizar presenças locais para exibição
        $presencasAtuais = $objEvento->getPresencasByEvento($id);
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}
?>
<h1 class="mt-4">Editar Evento</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form id="formEvento" method="post">
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
    </div>
</div>

<div class="card mt-4 mb-5">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-check-fill me-2"></i>Controle de Presença (Lista de Chamada)</span>
        <a href="<?php echo SITE_URL; ?>/modulos/eventos/relatorio_presenca.php?evento_id=<?php echo $id; ?>" target="_blank" class="btn btn-outline-light btn-sm">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>Gerar Lista (PDF)
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small">Marque a presença dos associados participantes. Registros são salvos ao clicar em "Salvar Alterações".</p>
        <div class="table-responsive">
            <table id="presencaTable" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Associado</th>
                        <th class="text-center">Status de Presença</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todosAssociados as $assoc): ?>
                        <?php 
                        $status_atual = $presencasAtuais[$assoc['id']] ?? 'Nenhum';
                        ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($assoc['nome'] . ' ' . $assoc['sobrenome']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($assoc['email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <div class="form-check form-check-inline">
                                        <input class="btn-check" type="radio" name="presenca[<?php echo $assoc['id']; ?>]" id="p_<?php echo $assoc['id']; ?>_pres" value="Presente" <?php echo $status_atual === 'Presente' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success btn-sm px-3" for="p_<?php echo $assoc['id']; ?>_pres">Presente</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="btn-check" type="radio" name="presenca[<?php echo $assoc['id']; ?>]" id="p_<?php echo $assoc['id']; ?>_aus" value="Ausente" <?php echo $status_atual === 'Ausente' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-danger btn-sm px-3" for="p_<?php echo $assoc['id']; ?>_aus">Ausente</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="btn-check" type="radio" name="presenca[<?php echo $assoc['id']; ?>]" id="p_<?php echo $assoc['id']; ?>_jus" value="Justificado" <?php echo $status_atual === 'Justificado' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-warning btn-sm px-3 text-dark" for="p_<?php echo $assoc['id']; ?>_jus">Justificado</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="btn-check" type="radio" name="presenca[<?php echo $assoc['id']; ?>]" id="p_<?php echo $assoc['id']; ?>_nen" value="Nenhum" <?php echo ($status_atual === 'Nenhum' || $status_atual === 'Confirmado') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary btn-sm px-3" for="p_<?php echo $assoc['id']; ?>_nen">Limpar</label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">Voltar</a>
        </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#presencaTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "pageLength": 25,
        "order": [[0, "asc"]]
    });

    // Submissão do formulário: garantir que TODOS os rádios do DataTable sejam enviados
    $('#formEvento').on('submit', function(e) {
        var form = this;
        
        // Obter todos os inputs de rádio da tabela (incluindo páginas não visíveis)
        table.$('input:checked').each(function() {
            // Se o input já estiver no formulário visível (DOM), não duplicar
            if (!$.contains(form, this)) {
                // Criar um campo oculto para os valores de páginas ocultas
                $(form).append(
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('name', this.name)
                        .val(this.value)
                );
            }
        });
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>