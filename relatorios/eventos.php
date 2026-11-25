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
    $reportManager->gerarRelatorioPDF('eventos', $_GET, $formato);
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
    $reportManager->gerarRelatorioPDF('eventos', $_GET, $formato);
    exit();
}

// Obter parâmetros
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'html';
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$ordenarPor = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'id';
$colunas = isset($_GET['colunas']) ? explode(',', $_GET['colunas']) : [];

// Se não houver colunas selecionadas, usar as padrão
if (empty($colunas)) {
    $colunas = ['id', 'nome', 'data_cadastro', 'status'];
}

try {
    // Obter dados do relatório
    $dados = $reportManager->getEventosReport([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'ordenar_por' => $ordenarPor,
        'colunas' => $colunas
    ]);
    
    // Se for uma requisição AJAX, retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $dados
        ]);
        exit();
    }
    
    // Incluir o template de visualização
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/menu.php';
    
    // Incluir o template específico do relatório
    $templateFile = __DIR__ . '/relatorios/visualizar_eventos.php';
    if (file_exists($templateFile)) {
        include $templateFile;
    } else {
        // Template padrão caso não exista um específico
        include __DIR__ . '/relatorios/visualizar_padrao.php';
    }
    
    include __DIR__ . '/includes/footer.php';
    
} catch (Exception $e) {
    // Se ocorrer um erro, redirecionar de volta com mensagem
    $_SESSION['erro'] = 'Erro ao gerar relatório: ' . $e->getMessage();
    header('Location: ' . SITE_URL . '/relatorios');
    exit();
}