<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar dados do formulário
        $notificacoes_ativas = isset($_POST['notificacoes_ativas']) ? 1 : 0;
        $dias_antecedencia = intval($_POST['dias_antecedencia'] ?? 7);
        $notif_eventos_hoje = isset($_POST['notif_eventos_hoje']) ? 1 : 0;
        $notif_eventos_proximos = isset($_POST['notif_eventos_proximos']) ? 1 : 0;
        $notif_eventos_atrasados = isset($_POST['notif_eventos_atrasados']) ? 1 : 0;
        
        // Atualizar configurações de notificação na tabela associados
        $stmt = $db->prepare("
            UPDATE associados 
            SET notificacoes_ativas = ?, 
                dias_antecedencia_evento = ? 
            WHERE id = ?
        ");
        $stmt->execute([$notificacoes_ativas, $dias_antecedencia, $user_id]);
        
        // Salvar configurações específicas de tipos de notificação
        // (pode ser expandido para uma tabela separada se necessário)
        $_SESSION['user_notif_config'] = [
            'notif_eventos_hoje' => $notif_eventos_hoje,
            'notif_eventos_proximos' => $notif_eventos_proximos,
            'notif_eventos_atrasados' => $notif_eventos_atrasados
        ];
        
        $mensagem = 'success';
        
    } catch (PDOException $e) {
        $mensagem = 'error';
        error_log('Erro ao salvar notificações: ' . $e->getMessage());
    }
}

// Redirecionar de volta para configurações com mensagem
$redirect_url = SITE_URL . '/usuario/configuracoes';
if ($mensagem === 'success') {
    $redirect_url .= '?msg=notif_success';
} else {
    $redirect_url .= '?msg=notif_error';
}

header('Location: ' . $redirect_url);
exit;
?> 