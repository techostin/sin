<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';

// Iniciar buffer de saída
ob_start();

// Registrar função para capturar erros
function handleError($errno, $errstr, $errfile, $errline) {
    $log = "Erro: [$errno] $errstr em $errfile na linha $errline";
    error_log($log);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => $log]);
    exit;
}
set_error_handler('handleError');

// Registrar função para capturar exceções
function handleException($e) {
    $log = "Exceção: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine();
    error_log($log);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => $log]);
    exit;
}
set_exception_handler('handleException');

// Função para registrar logs
function logMessage($message) {
    $log = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    file_put_contents(__DIR__ . '/../logs/api_debug.log', $log, FILE_APPEND);
}

// Criar diretório de logs se não existir
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

logMessage("Iniciando processamento da requisição");

// Configurar cabeçalhos CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Se for uma requisição OPTIONS, retornar sucesso
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Lembrete.php';

// Inicializar array de resposta
$response = [
    'success' => false,
    'message' => '',
    'eventos' => []
];

try {
    logMessage("Verificando sessão do usuário");
    // Verificar se o usuário está logado
    session_start();
    logMessage("Sessão iniciada. Sessão: " . json_encode($_SESSION));
    
    if (!isset($_SESSION['user_id'])) {
        logMessage("Erro: Usuário não autenticado");
        throw new Exception('Usuário não autenticado');
    }
    
    logMessage("Usuário autenticado. ID: " . $_SESSION['user_id']);

    // Buscar eventos pendentes
    logMessage("Criando instância da classe Lembrete");
    $lembrete = new Lembrete();
    
    // Buscar eventos de hoje e próximos 30 dias
    logMessage("Buscando eventos de hoje");
    $eventos_hoje = $lembrete->getEventosHoje();
    logMessage("Eventos de hoje encontrados: " . count($eventos_hoje));
    
    logMessage("Buscando próximos eventos (30 dias)");
    $eventos_proximos = $lembrete->getEventosProximos(30); // Próximos 30 dias
    logMessage("Próximos eventos encontrados: " . count($eventos_proximos));
    
    // Combinar e remover duplicatas
    logMessage("Combinando e removendo duplicatas");
    $todos_eventos = array_merge($eventos_hoje, $eventos_proximos);
    $eventos_unicos = [];
    $ids_vistos = [];
    
    logMessage("Total de eventos antes da remoção de duplicatas: " . count($todos_eventos));
    
    foreach ($todos_eventos as $evento) {
        if (!in_array($evento['id'], $ids_vistos)) {
            $eventos_unicos[] = $evento;
            $ids_vistos[] = $evento['id'];
        }
    }
    
    logMessage("Total de eventos após remoção de duplicatas: " . count($eventos_unicos));
    
    // Ordenar por data
    logMessage("Ordenando eventos por data");
    usort($eventos_unicos, function($a, $b) {
        return strtotime($a['data_evento']) - strtotime($b['data_evento']);
    });
    
    // Preparar resposta
    logMessage("Preparando resposta");
    $response['success'] = true;
    $response['eventos'] = $eventos_unicos;
    
    // Adicionar informações de debug à resposta
    $response['debug'] = [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'eventos_hoje_count' => count($eventos_hoje),
        'eventos_proximos_count' => count($eventos_proximos),
        'eventos_unicos_count' => count($eventos_unicos),
        'server' => [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null,
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    ];
    
    logMessage("Resposta preparada com sucesso");
    
} catch (Exception $e) {
    $errorMsg = 'Erro em get_eventos_pendentes.php: ' . $e->getMessage() . ' em ' . $e->getFile() . ' na linha ' . $e->getLine();
    error_log($errorMsg);
    logMessage($errorMsg);
    
    $response = [
        'success' => false,
        'message' => $errorMsg,
        'debug' => [
            'error' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ],
            'session' => $_SESSION ?? null,
            'server' => $_SERVER
        ]
    ];
}

// Enviar cabeçalhos
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Registrar resposta no log
$jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
logMessage("Resposta enviada: " . $jsonResponse);

// Enviar resposta
echo $jsonResponse;

// Finalizar buffer e enviar saída
if (ob_get_level() > 0) {
    ob_end_flush();
}

exit;
