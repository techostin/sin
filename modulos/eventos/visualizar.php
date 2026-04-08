<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

// Log de debug
error_log("Visualizar evento chamado - ID: " . ($_GET['id'] ?? 'não informado'));

// Verificar se é uma requisição AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    error_log("Erro: Não é uma requisição AJAX");
    http_response_code(403);
    echo json_encode(['erro' => 'Acesso negado - não é uma requisição AJAX']);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    error_log("Erro: ID inválido - " . $id);
    http_response_code(400);
    echo json_encode(['erro' => 'ID do evento não informado ou inválido']);
    exit;
}

try {
    error_log("Conectando ao banco de dados...");
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->execute([$id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        error_log("Evento não encontrado para ID: " . $id);
        http_response_code(404);
        echo json_encode(['erro' => 'Evento não encontrado']);
        exit;
    }
    
    error_log("Evento encontrado: " . $evento['nome']);
    
    // Formatar dados para exibição
    $data_formatada = date('d/m/Y H:i', strtotime($evento['data_evento']));
    $dias_para_evento = '';
    
    $data_evento = new DateTime($evento['data_evento']);
    $hoje = new DateTime();
    $diferenca = $hoje->diff($data_evento);
    
    if ($data_evento->format('Y-m-d') === $hoje->format('Y-m-d')) {
        $dias_para_evento = 'Hoje';
        $classe_badge = 'bg-warning';
    } elseif ($data_evento->format('Y-m-d') === (new DateTime('tomorrow'))->format('Y-m-d')) {
        $dias_para_evento = 'Amanhã';
        $classe_badge = 'bg-info';
    } else {
        if ($diferenca->invert) {
            $dias_para_evento = 'Atrasado (' . $diferenca->days . ' dias)';
            $classe_badge = 'bg-danger';
        } else {
            $dias_para_evento = 'Em ' . $diferenca->days . ' dias';
            $classe_badge = 'bg-primary';
        }
    }
    
    // Definir cor do status
    $status_classe = '';
    switch($evento['status']) {
        case 'Agendado':
            $status_classe = 'bg-primary';
            break;
        case 'Realizado':
            $status_classe = 'bg-success';
            break;
        case 'Cancelado':
            $status_classe = 'bg-danger';
            break;
        default:
            $status_classe = 'bg-secondary';
    }
    
    $html = '
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="mb-0">' . htmlspecialchars($evento['nome']) . '</h4>
                    <span class="badge ' . $status_classe . '">' . htmlspecialchars($evento['status']) . '</span>
                </div>
                
                <div class="alert alert-info">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-fill me-2"></i>
                        <strong>Data e Hora:</strong> ' . $data_formatada . '
                    </div>
                    <div class="d-flex align-items-center mt-1">
                        <i class="bi bi-calendar-week-fill me-2"></i>
                        <strong>Status:</strong> <span class="badge ' . $classe_badge . ' ms-1">' . $dias_para_evento . '</span>
                    </div>
                </div>';
    
    if (!empty($evento['local'])) {
        $html .= '
                <div class="mb-3">
                    <h6><i class="bi bi-geo-alt-fill text-primary me-2"></i>Local</h6>
                    <p class="mb-0">' . htmlspecialchars($evento['local']) . '</p>
                </div>';
    }
    
    if (!empty($evento['descricao'])) {
        $html .= '
                <div class="mb-3">
                    <h6><i class="bi bi-file-text-fill text-primary me-2"></i>Descrição</h6>
                    <p class="mb-0">' . nl2br(htmlspecialchars($evento['descricao'])) . '</p>
                </div>';
    }
    
    // Buscar informações de presença se houver
    try {
        $stmt_presenca = $db->prepare("
            SELECT COUNT(*) as total_presenca 
            FROM evento_presenca 
            WHERE evento_id = ? AND status_presenca IN ('Confirmado', 'Presente')
        ");
        $stmt_presenca->execute([$id]);
        $presenca = $stmt_presenca->fetch(PDO::FETCH_ASSOC);
        
        if ($presenca['total_presenca'] > 0) {
            $html .= '
                <div class="mb-3">
                    <h6><i class="bi bi-people-fill text-primary me-2"></i>Participação</h6>
                    <p class="mb-0">' . $presenca['total_presenca'] . ' pessoa(s) confirmaram presença</p>
                </div>';
        }
    } catch (PDOException $e) {
        // Ignorar erro se a tabela não existir
    }
    
    $html .= '
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>';
    
    // Mostrar botão de editar apenas para usuários com permissão
    if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] !== 'usuario') {
        $html .= '
            <a href="' . SITE_URL . '/evento/' . $evento['id'] . '/editar" class="btn btn-primary">
                <i class="bi bi-pencil-fill"></i> Editar Evento
            </a>';
    }
    
    $html .= '
        </div>';
    
    $response = [
        'success' => true,
        'html' => $html,
        'titulo' => $evento['nome']
    ];
    
    // Definir cabeçalhos para JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro ao buscar dados do evento: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro geral: ' . $e->getMessage()]);
}
?> 