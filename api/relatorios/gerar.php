<?php
// Incluir o arquivo de inicialização
require_once __DIR__ . '/bootstrap.php';

// Verificar autenticação
verificarAutenticacao();

// Verificar se o tipo de relatório foi informado
if (!isset($_GET['tipo'])) {
    http_response_code(400);
    echo json_encode(['erro' => 'Tipo de relatório não especificado']);
    exit();
}

try {
    $tipo = $_GET['tipo'];
    $formato = isset($_GET['formato']) ? $_GET['formato'] : 'I'; // I = inline, D = download
    
    // Inicializa o gerenciador de relatórios
    $reportManager = new ReportManager();
    
    // Obtém os filtros da requisição
    $filtros = [];
    $filtrosPermitidos = ['data_inicio', 'data_fim', 'status', 'categoria_id', 'vinculo'];
    
    foreach ($filtrosPermitidos as $filtro) {
        if (isset($_GET[$filtro])) {
            $filtros[$filtro] = $_GET[$filtro];
        }
    }
    
    // Gera o relatório em PDF
    $resultado = $reportManager->gerarRelatorioPDF($tipo, $filtros, $formato);
    
    if ($resultado === false) {
        throw new Exception('Erro ao gerar o relatório');
    }
    
    // Se chegou até aqui, o PDF já foi enviado para o navegador
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro ao gerar relatório',
        'detalhes' => $e->getMessage()
    ]);
    exit();
}
