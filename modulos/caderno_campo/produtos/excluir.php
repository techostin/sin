<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../../classes/User.php';

$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !is_numeric((string)$id)) {
    header('Location: ' . SITE_URL . '/caderno-campo/produtos?erro=csrf');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // IDOR Check
    if (!$user->hasPermission(NIVEL_GESTOR)) {
        $stmt_check = $db->prepare("SELECT p.associado_id 
                                    FROM produtos pr 
                                    INNER JOIN lavouras l ON pr.lavoura_id = l.id 
                                    INNER JOIN propriedades p ON l.propriedade_id = p.id 
                                    WHERE pr.id = ?");
        $stmt_check->execute([(int)$id]);
        $res = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if (!$res || $res['associado_id'] != $user->getAssociadoId()) {
            header('Location: ' . SITE_URL . '/caderno-campo/produtos?erro=acesso_negado');
            exit;
        }
    }

    $stmt = $db->prepare('DELETE FROM produtos WHERE id = ?');
    $stmt->execute([(int)$id]);
    header('Location: ' . SITE_URL . '/caderno-campo/produtos?msg=excluido');
    exit;
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . '/caderno-campo/produtos?erro=exclusao');
    exit;
}

