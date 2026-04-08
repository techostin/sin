<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sisagro');

// Configurações do Sistema
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/app');
define('SITE_TITLE', 'SISAGRO');

// Configurações de Nível de Acesso
define('NIVEL_ADMIN', 1);
define('NIVEL_GESTOR', 2);
define('NIVEL_USUARIO', 3);

// Iniciar a sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
