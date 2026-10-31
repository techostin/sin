<?php
/**
 * Portal do Associado - Logout
 *
 * Encerra SEMPRE a sessão do portal (associado_id e dados derivados).
 * Quando o acesso foi feito por Admin/Diretoria via "Consultar por CPF"
 * ($_SESSION['portal_acesso_via'] === 'consulta_admin'), a sessão principal
 * do usuário é preservada: ele apenas sai do portal do associado consultado.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/PortalAccess.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$acessoVia = $_SESSION['portal_acesso_via'] ?? '';
$mantemSessaoPrincipal = ($acessoVia === 'consulta_admin') && !empty($_SESSION['user_id']);

if (function_exists('session_regenerate_id')) {
    // Evita que a sessão do portal continue válida no mesmo ID
    @session_regenerate_id(true);
}

// Destruir a sessão do portal (nunca reutilizar dados do associado anterior)
unset(
    $_SESSION['associado_id'],
    $_SESSION['associado_nome'],
    $_SESSION['associado_cpf'],
    $_SESSION['associado_email'],
    $_SESSION['portal_acesso_via'],
    $_SESSION['portal_acesso_por']
);

if ($mantemSessaoPrincipal) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

session_destroy();

header('Location: ' . SITE_URL . '/portal/login');
exit;
