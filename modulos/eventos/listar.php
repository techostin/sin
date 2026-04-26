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

if (!$user->hasPermission(NIVEL_USUARIO)) {
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
        <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
            <a href="<?php echo SITE_URL; ?>/evento/novo" class="btn btn-primary btn-sm">Adicionar Novo Evento</a>
        <?php endif; ?>
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
                                <button type="button" class="btn btn-secondary btn-sm" title="Visualizar" onclick="visualizarEvento(<?php echo $evento['id']; ?>)">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                                <button type="button" class="btn btn-success btn-sm" title="Acompanhar Presença" onclick="abrirModalPresenca(<?php echo $evento['id']; ?>, '<?php echo addslashes(htmlspecialchars($evento['nome'])); ?>')">
                                    <i class="bi bi-people-fill"></i>
                                </button>
                                <?php if ($user->hasPermission(NIVEL_GESTOR)): ?>
                                    <a href="<?php echo SITE_URL; ?>/evento/<?php echo $evento['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                    <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                                <?php endif; ?>
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

function abrirModalPresenca(eventoId, eventoNome) {
    const modalHtml = `
    <div class="modal fade" id="modalPresenca" tabindex="-1" aria-labelledby="modalPresencaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" style="max-width: 1120px;">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalPresencaLabel">
                        <i class="bi bi-people-fill me-2"></i>Presenças - ` + eventoNome + `
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="mt-2">Carregando dados...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
    
    $('#modalPresenca').remove();
    $('body').append(modalHtml);
    $('#modalPresenca').modal('show');
    
    $('#modalPresenca').on('hidden.bs.modal', function() {
        $(this).remove();
    });
    
    fetch(window.SITE_URL + '/api/get_presenca_evento.php?evento_id=' + eventoId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<div class="row">';
            
            // Confirmados
            html += '<div class="col-md-6">';
            html += '<h6 class="text-success"><i class="bi bi-check-circle-fill"></i> Confirmados (' + data.confirmados.length + ')</h6>';
            if (data.confirmados.length > 0) {
                html += '<ul class="list-group mb-3" style="max-height: 300px; overflow-y: auto;">';
                data.confirmados.forEach(function(conf) {
                    let nome = 'ID: ' + conf.id;
                    let badge = '';
                    if (conf.table === 'associados' && data.nomes_confirmados.associados && data.nomes_confirmados.associados[conf.id]) {
                        nome = data.nomes_confirmados.associados[conf.id].nome;
                        let tipo = data.nomes_confirmados.associados[conf.id].tipo;
                        if (tipo === 'Admin') badge = '<span class="badge bg-danger ms-1">Admin</span>';
                        else if (tipo === 'Gestor') badge = '<span class="badge bg-primary ms-1">Gestor</span>';
                        else badge = '<span class="badge bg-secondary ms-1">Associado</span>';
                    } else if (conf.table === 'usuarios' && data.nomes_confirmados.usuarios && data.nomes_confirmados.usuarios[conf.id]) {
                        nome = data.nomes_confirmados.usuarios[conf.id].nome;
                        let tipo = data.nomes_confirmados.usuarios[conf.id].tipo;
                        if (tipo === 'Gestor') badge = '<span class="badge bg-primary ms-1">Gestor</span>';
                        else badge = '<span class="badge bg-info ms-1">Usuário</span>';
                    }
                    let dataFormatada = conf.data ? new Date(conf.data).toLocaleString('pt-BR') : '';
                    html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                            '<span><i class="bi bi-person-fill text-success me-2"></i>' + nome + '</span>' +
                            '<span>' + badge + (dataFormatada ? '<br><small class="text-muted">' + dataFormatada + '</small>' : '') + '</span></li>';
                });
                html += '</ul>';
            } else {
                html += '<p class="text-muted">Nenhum participante confirmado ainda.</p>';
            }
            html += '</div>';
            
            // Pendentes
            const totalPendente = data.nao_confirmados_associados.length + data.nao_confirmados_usuarios.length + data.nao_confirmados_gestores.length;
            html += '<div class="col-md-6">';
            html += '<h6 class="text-warning"><i class="bi bi-clock-fill"></i> Pendentes (' + totalPendente + ')</h6>';
            if (totalPendente > 0) {
                html += '<ul class="list-group" style="max-height: 300px; overflow-y: auto;">';
                
                // Gestores (first, since they are special)
                if (data.nao_confirmados_gestores && data.nao_confirmados_gestores.length > 0) {
                    data.nao_confirmados_gestores.forEach(function(gestor) {
                        html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                '<span><i class="bi bi-shield-fill text-primary me-2"></i>' + gestor.nome + '</span>' +
                                '<span class="badge bg-primary">Gestor</span></li>';
                    });
                }
                
                // Associados
                data.nao_confirmados_associados.forEach(function(assoc) {
                    let badge = '';
                    if (assoc.tipo === 'Admin') badge = '<span class="badge bg-danger">Admin</span>';
                    else if (assoc.tipo === 'Gestor') badge = '<span class="badge bg-primary">Gestor</span>';
                    else badge = '<span class="badge bg-secondary">Associado</span>';
                    html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                            '<span><i class="bi bi-person text-secondary me-2"></i>' + assoc.nome + ' ' + assoc.sobrenome + '</span>' +
                            badge + '</li>';
                });
                
                // Usuarios puros (sem vinculo com associado)
                if (data.nao_confirmados_usuarios && data.nao_confirmados_usuarios.length > 0) {
                    data.nao_confirmados_usuarios.forEach(function(usr) {
                        html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                                '<span><i class="bi bi-person text-info me-2"></i>' + usr.nome + '</span>' +
                                '<span class="badge bg-info">Usuário</span></li>';
                    });
                }
                html += '</ul>';
            } else {
                html += '<p class="text-success"><i class="bi bi-check-all"></i> Todos já confirmaram!</p>';
            }
            html += '</div>';
            
            html += '</div>';
            
            // Info do evento
            html += '<hr><div class="alert alert-info mb-0">';
            html += '<i class="bi bi-calendar-event me-2"></i><strong>Data:</strong> ' + data.evento.data_evento;
            if (data.evento.local) {
                html += ' &nbsp;|&nbsp; <i class="bi bi-geo-alt me-2"></i><strong>Local:</strong> ' + data.evento.local;
            }
            html += ' &nbsp;|&nbsp; <span class="badge bg-' + (data.evento.status === 'Agendado' ? 'warning' : data.evento.status === 'Realizado' ? 'success' : 'danger') + '">' + data.evento.status + '</span>';
            html += '</div>';
            
            $('#modalPresenca .modal-body').html(html);
        } else {
            $('#modalPresenca .modal-body').html('<div class="alert alert-danger">Erro ao carregar dados: ' + (data.erro || 'Erro desconhecido') + '</div>');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        $('#modalPresenca .modal-body').html('<div class="alert alert-danger">Erro ao carregar dados. Tente novamente.</div>');
    });
}
</script> 