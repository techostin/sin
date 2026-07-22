<?php
/**
 * Script para atualizar todos os arquivos de relatório com o novo sistema de geração de PDF
 * Uso: Acesse este arquivo pelo navegador ou execute via linha de comando
 */

// Habilitar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações
$reportsDir = __DIR__ . '/relatorios/';
$templatesDir = $reportsDir . 'templates/';

// Lista de relatórios para atualizar
$reportFiles = [
    'atividades',
    'eventos',
    'financeiro',
    'insumos',
    'lavouras',
    'produtores',
    'propriedades'
];

// Template para os arquivos de relatório
$reportTemplate = <<<'EOT'
<?php
// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $reportManager->gerarRelatorioPDF('{REPORT_NAME}', $_GET, $formato);
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
    $reportManager->gerarRelatorioPDF('{REPORT_NAME}', $_GET, $formato);
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
    $dados = $reportManager->get{REPORT_CLASS}Report([
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
    $templateFile = __DIR__ . '/relatorios/visualizar_{REPORT_NAME}.php';
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
EOT;

// Função para converter nome do relatório para formato de classe
function toClassName($name) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
}

// Processar cada relatório
$results = [];
foreach ($reportFiles as $report) {
    $reportFile = $reportsDir . $report . '.php';
    $reportClass = toClassName($report);
    
    // Criar o conteúdo do arquivo
    $content = str_replace(
        ['{REPORT_NAME}', '{REPORT_CLASS}'],
        [$report, $reportClass],
        $reportTemplate
    );
    
    // Salvar o arquivo
    $result = file_put_contents($reportFile, $content);
    
    $results[] = [
        'file' => $reportFile,
        'success' => ($result !== false),
        'size' => $result
    ];
}

// Exibir resultados
if (php_sapi_name() === 'cli') {
    // Modo linha de comando
    foreach ($results as $result) {
        echo sprintf(
            "%s: %s (%d bytes)\n",
            $result['file'],
            $result['success'] ? 'OK' : 'ERRO',
            $result['size']
        );
    }
} else {
    // Modo navegador
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Atualização de Relatórios</title><meta charset="UTF-8">';
    echo '<style>body{font-family: Arial, sans-serif; line-height: 1.6; margin: 20px;}';
    echo 'table{border-collapse: collapse; width: 100%;}th,td{padding: 8px; text-align: left; border-bottom: 1px solid #ddd;}';
    echo '.success{color: green;}.error{color: red;}</style></head><body>';
    echo '<h1>Atualização de Relatórios</h1>';
    echo '<table><tr><th>Arquivo</th><th>Status</th><th>Tamanho</th></tr>';
    
    foreach ($results as $result) {
        echo sprintf(
            '<tr><td>%s</td><td class="%s">%s</td><td>%d bytes</td></tr>',
            basename($result['file']),
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Atualizado' : 'Erro',
            $result['size']
        );
    }
    
    echo '</table></body></html>';
}
