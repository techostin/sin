<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'erro' => 'Acesso negado - não é uma requisição AJAX']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erro' => 'Método inválido']);
    exit;
}

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'erro' => 'Usuário não autenticado']);
    exit;
}

// Proteção CSRF (reforço além do header X-Requested-With)
require_once __DIR__ . '/../classes/CSRFProtection.php';
if (!CSRFProtection::validateToken(CSRFProtection::tokenFromRequest())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'erro' => 'Token CSRF inválido ou ausente.']);
    exit;
}

$evento_id = $_POST['evento_id'] ?? null;
if (!$evento_id || !is_numeric($evento_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'ID do evento inválido']);
    exit;
}

// Obter ID e tabela do usuário logado
$user_id = $_SESSION['user_id'];
$user_table = $_SESSION['user_table'] ?? 'associados';

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar o evento e a lista de confirmações
    $stmt = $db->prepare("SELECT confirmados FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evento) {
        http_response_code(404);
        echo json_encode(['success' => false, 'erro' => 'Evento não encontrado']);
        exit;
    }
    
    $confirmados = json_decode($evento['confirmados'] ?? '[]', true);
    if (!is_array($confirmados)) $confirmados = [];
    
    // Verificar se o usuário já confirmou
    $index = -1;
    foreach ($confirmados as $i => $conf) {
        if ($conf['id'] == $user_id && $conf['table'] == $user_table) {
            $index = $i;
            break;
        }
    }
    
    if ($index !== -1) {
        // Remover confirmação do JSON
        array_splice($confirmados, $index, 1);
        $novo_confirmados = json_encode($confirmados);
        $stmt_update = $db->prepare("UPDATE eventos SET confirmados = ? WHERE id = ?");
        $stmt_update->execute([$novo_confirmados, $evento_id]);
        
        // Remover da tabela evento_presenca
        if ($user_table === 'associados') {
            $stmt_del = $db->prepare("DELETE FROM evento_presenca WHERE evento_id = ? AND associado_id = ?");
            $stmt_del->execute([$evento_id, $user_id]);
        } else {
            $stmt_del = $db->prepare("DELETE FROM evento_presenca WHERE evento_id = ? AND usuario_id = ?");
            $stmt_del->execute([$evento_id, $user_id]);
        }
        
        echo json_encode(['success' => true, 'acao' => 'removido', 'mensagem' => 'Presença cancelada com sucesso']);
    } else {
        // Adicionar confirmação ao JSON
        $confirmados[] = ['id' => $user_id, 'table' => $user_table, 'data' => date('Y-m-d H:i:s')];
        $novo_confirmados = json_encode($confirmados);
        $stmt_update = $db->prepare("UPDATE eventos SET confirmados = ? WHERE id = ?");
        $stmt_update->execute([$novo_confirmados, $evento_id]);
        
        // Adicionar na tabela evento_presenca
        $associado_id = null;
        $usuario_id = null;
        
        if ($user_table === 'associados') {
            $associado_id = $user_id;
        } else {
            $usuario_id = $user_id;
            // Tentar buscar associado_id vinculado ao usuário
            $stmt_user = $db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
            $stmt_user->execute([$user_id]);
            $usr_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            if ($usr_data) {
                $associado_id = $usr_data['associado_id'];
            }
        }
        
        $stmt_ins = $db->prepare("INSERT INTO evento_presenca (evento_id, associado_id, usuario_id, status_presenca) VALUES (?, ?, ?, 'Confirmado')");
        $stmt_ins->execute([$evento_id, $associado_id, $usuario_id]);
        
        echo json_encode(['success' => true, 'acao' => 'adicionado', 'mensagem' => 'Presença confirmada com sucesso']);
    }
} catch (PDOException $e) {
    error_log("Erro PDO ao confirmar presença: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'erro' => 'Erro ao registrar presença']);
} catch (Exception $e) {
    error_log("Erro ao confirmar presença: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'erro' => 'Erro geral']);
}
?>
