<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../../config/error_config.php';

// Configurações de cabeçalho para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir arquivos necessários
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

// Verificar autenticação
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

// Obter o tipo de relatório
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// Inicializar o array de resposta
$response = [
    'sucesso' => true,
    'dados' => [],
    'mensagem' => ''
];

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($tipo) {
        case 'hoje':
            // Relatório de atividades de hoje
            $sql = "SELECT 
                        a.id, 
                        a.nome as titulo, 
                        a.descricao, 
                        DATE_FORMAT(a.data_atividade, '%d/%m/%Y %H:%i') as data_formatada,
                        p.nome as propriedade,
                        c.nome as categoria
                    FROM atividades_diarias a
                    LEFT JOIN propriedades p ON a.propriedade_id = p.id
                    LEFT JOIN categorias_atividades c ON a.categoria_id = c.id
                    WHERE DATE(a.data_atividade) = CURDATE() AND a.excluido = 0
                    ORDER BY a.data_atividade DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($atividades) > 0) {
                $response['dados'] = [
                    'titulo' => 'Atividades de Hoje',
                    'colunas' => ['Título', 'Descrição', 'Data/Hora', 'Propriedade', 'Categoria'],
                    'dados' => $atividades
                ];
            } else {
                $response['mensagem'] = 'Nenhuma atividade encontrada para hoje.';
            }
            break;
            
        case 'pendentes':
            // Relatório de atividades pendentes
            $sql = "SELECT 
                        a.id, 
                        a.nome as titulo, 
                        a.descricao, 
                        DATE_FORMAT(a.data_atividade, '%d/%m/%Y %H:%i') as data_prevista,
                        p.nome as propriedade,
                        c.nome as categoria
                    FROM atividades_diarias a
                    LEFT JOIN propriedades p ON a.propriedade_id = p.id
                    LEFT JOIN categorias_atividades c ON a.categoria_id = c.id
                    WHERE a.status = 'Pendente' AND a.excluido = 0
                    ORDER BY a.data_atividade ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($pendentes) > 0) {
                $response['dados'] = [
                    'titulo' => 'Atividades Pendentes',
                    'colunas' => ['Título', 'Descrição', 'Data Prevista', 'Propriedade', 'Categoria'],
                    'dados' => $pendentes
                ];
            } else {
                $response['mensagem'] = 'Nenhuma atividade pendente encontrada.';
            }
            break;
            
        case 'estoque-minimo':
            // Relatório de itens abaixo do estoque mínimo
            $sql = "SELECT 
                        i.id,
                        i.nome as produto,
                        i.quantidade_estoque as quantidade_atual,
                        f.nome as fornecedor,
                        f.telefone as contato_fornecedor
                    FROM insumos i
                    LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                    WHERE i.excluido = 0 AND i.quantidade_estoque < 10
                    ORDER BY i.quantidade_estoque ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($estoque) > 0) {
                $response['dados'] = [
                    'titulo' => 'Itens com Estoque Baixo',
                    'colunas' => ['Produto', 'Quantidade Atual', 'Fornecedor', 'Contato'],
                    'dados' => $estoque
                ];
            } else {
                $response['mensagem'] = 'Nenhum item com estoque baixo encontrado.';
            }
            break;
            
        default:
            $response['sucesso'] = false;
            $response['mensagem'] = 'Tipo de relatório não especificado ou inválido.';
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'sucesso' => false,
        'mensagem' => 'Erro ao processar o relatório: ' . $e->getMessage()
    ];
}

// Retornar a resposta em formato JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
