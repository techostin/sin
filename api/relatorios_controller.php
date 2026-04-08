<?php
/**
 * Controlador de Relatórios
 * 
 * Este arquivo processa as requisições de geração de relatórios
 */

// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Relatorio.php';

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

// Verificar se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Obter os dados da requisição
$input = json_decode(file_get_contents('php://input'), true);

// Validar os parâmetros obrigatórios
if (empty($input['tipo']) || empty($input['formato'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit();
}

$tipo = $input['tipo'];
$formato = $input['formato'];

// Tipos de relatório permitidos
$tiposPermitidos = ['atividades', 'propriedades', 'insumos', 'financeiro', 'personalizado'];
if (!in_array($tipo, $tiposPermitidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de relatório inválido']);
    exit();
}

// Formatos permitidos
$formatosPermitidos = ['pdf', 'excel'];
if (!in_array($formato, $formatosPermitidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de saída inválido']);
    exit();
}

try {
    // Criar instância do relatório
    $relatorio = new Relatorio();
    
    // Configurar período
    if (!empty($input['data_inicio']) && !empty($input['data_fim'])) {
        $relatorio->setPeriodo($input['data_inicio'], $input['data_fim']);
    }
    
    // Configurar filtros adicionais
    $filtros = [];
    
    // Filtros comuns
    $filtrosComuns = ['status', 'categoria_id', 'tipo', 'usuario_id', 'propriedade_id'];
    foreach ($filtrosComuns as $filtro) {
        if (!empty($input[$filtro])) {
            $filtros[$filtro] = $input[$filtro];
        }
    }
    
    // Adicionar filtros específicos para cada tipo de relatório
    switch ($tipo) {
        case 'atividades':
            // Filtros específicos de atividades
            break;
            
        case 'propriedades':
            // Filtros específicos de propriedades
            break;
            
        case 'insumos':
            // Filtros específicos de insumos
            break;
            
        case 'financeiro':
            // Filtros específicos de financeiro
            break;
            
        case 'personalizado':
            // Configurações para relatório personalizado
            if (!empty($input['colunas'])) {
                $filtros['colunas'] = $input['colunas'];
            }
            if (!empty($input['ordenacao'])) {
                $filtros['ordenacao'] = $input['ordenacao'];
            }
            break;
    }
    
    // Aplicar filtros
    if (!empty($filtros)) {
        $relatorio->setFiltros($filtros);
    }
    
    // Gerar o relatório no formato solicitado
    if ($formato === 'pdf') {
        $resultado = $relatorio->gerarPdf($tipo);
        $contentType = 'application/pdf';
    } else {
        $resultado = $relatorio->gerarExcel($tipo);
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
    
    // Configurar cabeçalhos para download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $resultado['filename'] . '"');
    header('Content-Length: ' . strlen($resultado['content']));
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');
    
    // Enviar o conteúdo do arquivo
    echo $resultado['content'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao gerar relatório',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
