<?php
$page_title = "Listar Eventos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Exibir mensagens de sucesso/erro
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'excluido':
            echo '<div class="alert alert-success">Evento excluído com sucesso!</div>';
            break;
    }
}

if (isset($_GET['erro'])) {
    switch ($_GET['erro']) {
        case 'permissao':
            echo '<div class="alert alert-danger">Você não tem permissão para executar esta ação.</div>';
            break;
        case 'id_nao_informado':
            echo '<div class="alert alert-warning">ID do evento não informado.</div>';
            break;
        case 'exclusao':
            $mensagem = $_GET['msg'] ?? 'Erro ao excluir o evento.';
            echo '<div class="alert alert-danger">' . htmlspecialchars($mensagem) . '</div>';
            break;
    }
}

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Buscar eventos do banco (ordenados por data mais próxima e mostrando apenas agendados primeiro)
$eventos = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, nome, data_evento, local, status 
                         FROM eventos 
                         WHERE data_evento >= NOW() AND status = 'Agendado' 
                         ORDER BY data_evento ASC");
    $stmt->execute();
    $eventos_agendados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar eventos concluídos/cancelados separadamente
    $stmt = $db->prepare("SELECT id, nome, data_evento, local, status 
                         FROM eventos 
                         WHERE data_evento < NOW() OR status != 'Agendado' 
                         ORDER BY data_evento DESC");
    $stmt->execute();
    $outros_eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combinar resultados (agendados primeiro, depois os outros)
    $eventos = array_merge($eventos_agendados, $outros_eventos);
    
} catch (PDOException $e) {
    $eventos = [];
    error_log("Erro ao buscar eventos: " . $e->getMessage());
}
?>
<h1 class="mt-4">Eventos</h1>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Eventos</span>
        <a href="<?php echo SITE_URL; ?>/evento/novo" class="btn btn-primary btn-sm">Adicionar Novo Evento</a>
    </div>
    <div class="card-body">
        <table id="eventosTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Data</th>
                    <th>Local</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($eventos) > 0): ?>
                    <?php foreach ($eventos as $evento): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($evento['id']); ?></td>
                            <td><?php echo htmlspecialchars($evento['nome']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($evento['data_evento'])); ?></td>
                            <td><?php echo htmlspecialchars($evento['local']); ?></td>
                            <td>
                                <?php 
                                $status_class = '';
                                switch($evento['status']) {
                                    case 'Agendado':
                                        $status_class = 'bg-warning text-dark';
                                        break;
                                    case 'Realizado':
                                        $status_class = 'bg-success';
                                        break;
                                    case 'Cancelado':
                                        $status_class = 'bg-danger';
                                        break;
                                    default:
                                        $status_class = 'bg-secondary';
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($evento['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>/evento/<?php echo $evento['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum evento encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<style>
.btn-danger[title*="desabilitada"] {
    opacity: 0.6;
    cursor: pointer;
    position: relative;
}

.btn-danger[title*="desabilitada"]:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.btn-danger[title*="desabilitada"]::after {
    content: "🚫";
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
$(document).ready(function() {
    $('#eventosTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        }
    });
});

function mostrarAdvertenciaExclusao() {
    // Criar modal de advertência
    const modalHtml = `
        <div class="modal fade" id="modalAdvertencia" tabindex="-1" aria-labelledby="modalAdvertenciaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="modalAdvertenciaLabel">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Aviso Importante
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="bi bi-shield-exclamation text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <p class="text-center"><strong>A funcionalidade de exclusão de eventos foi temporariamente desabilitada.</strong></p>
                        <p>Esta medida foi implementada para:</p>
                        <ul>
                            <li>🔒 Proteger dados importantes do sistema</li>
                            <li>⚠️ Evitar perda acidental de informações</li>
                            <li>🛡️ Manter a integridade dos registros</li>
                        </ul>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Alternativas disponíveis:</strong><br>
                            • Utilize a opção de <strong>Editar</strong> para fazer alterações<br>
                            • Entre em contato com o administrador do sistema se necessário
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                            <i class="bi bi-check-circle me-2"></i>Entendi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior se existir
    $('#modalAdvertencia').remove();
    
    // Adicionar modal ao body
    $('body').append(modalHtml);
    
    // Mostrar modal
    $('#modalAdvertencia').modal('show');
    
    // Remover modal do DOM após fechar
    $('#modalAdvertencia').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}
</script> 