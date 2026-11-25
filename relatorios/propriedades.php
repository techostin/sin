<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';

// Incluir arquivos necessários
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportManager.php';

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Inicializar o gerenciador de relatórios
$reportManager = new ReportManager();

// Verificar se é uma requisição de geração de PDF/Excel
if (isset($_GET['tipo']) && ($_GET['tipo'] === 'pdf' || $_GET['tipo'] === 'excel')) {
    $formato = $_GET['tipo'] === 'pdf' ? 'I' : 'D';
    $reportManager->gerarRelatorioPDF('propriedades', $_GET, $formato);
    exit();
}

// Verificar se está acessando pela URL amigável (ex: /{report_name}/pdf)
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($request_uri, '/'));
$last_part = end($path_parts);

// Se o último segmento da URL for 'pdf' ou 'excel', usar como tipo
if (in_array($last_part, ['pdf', 'excel'])) {
    $_GET['tipo'] = $last_part;
    $formato = $last_part === 'pdf' ? 'I' : 'D';
    $reportManager->gerarRelatorioPDF('propriedades', $_GET, $formato);
    exit();
}

// Se não for PDF nem Excel, redirecionar para o dashboard de relatórios
header('Location: ' . SITE_URL . '/relatorios/');
exit();