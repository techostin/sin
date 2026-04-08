<?php
/*
 * Arquivo de configuração do banco de dados
 * Defina as constantes para conexão com o MySQL.
 */

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');       // Host do banco de dados
define('DB_USER', 'root');            // Usuário do banco de dados
define('DB_PASS', '');               // Senha do banco de dados
define('DB_NAME', 'sisagro');          // Nome do banco de dados

// Configurações do Sistema
 // URL base do seu site
#define('SITE_URL', 'https://sinporg.com.br/app');//
define('SITE_URL', 'http://localhost/app'); // URL base do seu site
define('SITE_TITLE', 'SINPORG');             // Título do seu site

// Configurações de Nível de Acesso
define('NIVEL_ADMIN', 1);
define('NIVEL_GESTOR', 2);
define('NIVEL_USUARIO', 3);

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');

// Iniciar a sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
