<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/PlanoMensalidade.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->hasPermission(NIVEL_GESTOR)) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && CSRFProtection::validatePostRequest()) {
    $id = (int) $_GET['id'];
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT COUNT(*) FROM mensalidades WHERE plano_id = ?");
    $stmt->execute([$id]);
    $emUso = (int) $stmt->fetchColumn();

    if ($emUso > 0) {
        //
        // Plano já utilizado em cobranças: não exclui fisicamente,
        // apenas inativa para preservar o histórico financeiro.
        //
        $upd = $db->prepare("UPDATE planos_mensalidades SET ativo = 0 WHERE id = ?");
        if ($upd->execute([$id])) {
            $_SESSION['success_message'] = 'Plano inativado (possui lançamentos). Para excluir, remova os lançamentos antes.';
        } else {
            $_SESSION['error_message'] = 'Erro ao inativar o plano.';
        }
    } else {
        if ((new PlanoMensalidade())->delete($id)) {
            $_SESSION['success_message'] = 'Plano excluído com sucesso.';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir o plano.';
        }
    }
} else {
    $_SESSION['error_message'] = 'Operação inválida.';
}

header('Location: ' . SITE_URL . '/planos');
exit;