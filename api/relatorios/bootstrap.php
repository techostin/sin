<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../../config/error_config.php';

// Definir constantes básicas
// __DIR__ = <app>/api/relatorios  =>  o app está dois níveis acima.
// Antes apontava para <htdocs> e todo endpoint que usa este bootstrap
// (ex.: api/relatorios/gerar.php) terminava em HTTP 500.
define('ROOT_PATH', dirname(__DIR__, 2));

// Carregar configurações
require_once ROOT_PATH . '/config/config.php';

// Configurações de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Função para carregar classes automaticamente
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autorizado']);
        exit();
    }
}
