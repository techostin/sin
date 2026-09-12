<?php
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/AuditLog.php';

$user = new User();
AuditLog::registrar('LOGOUT', null, null, 'Usuário realizou logout');
$user->logout();

header("Location: " . SITE_URL . "/login");
exit;
?>