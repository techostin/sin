<?php
// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações de cabeçalho para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir arquivos necessários
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

// Inicializar a resposta
$response = [
    'status' => 'error',
    'message' => 'Requisição inválida',
    'data' => []
];

try {
    // Verificar autenticação
    $user = new User();
    if (!$user->isLoggedIn()) {
        http_response_code(401);
        $response['message'] = 'Não autorizado';
        echo json_encode($response);
        exit;
    }

    // Verificar autenticação
    $user = new User();
    if (!$user->isLoggedIn()) {
        http_response_code(401);
        $response['message'] = 'Não autorizado';
        echo json_encode($response);
        exit;
    }

    // Conectar ao banco de dados
    $db = Database::getInstance()->getConnection();
    
    // Construir a consulta SQL
    $sql = "SELECT 
                associados.id, 
                CONCAT(associados.nome, ' ', associados.sobrenome) as nome,
                associados.email,
                associados.telefone,
                associados.status,
                cidades.nome as cidade,
                estados.uf,
                estados.nome as estado,
                (SELECT nome FROM associacoes WHERE associacoes.id = associados.vinculo LIMIT 1) as nome_associacao
            FROM associados 
            LEFT JOIN cidades ON associados.cidade = cidades.id
            LEFT JOIN estados ON associados.estado = estados.id
            WHERE associados.status = 'ativo'
            ORDER BY nome, sobrenome";
    
    // Preparar e executar a consulta
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    // Obter os resultados
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar os dados
    $response = [
        'status' => 'success',
        'message' => 'Associados listados com sucesso',
        'data' => $associados
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'status' => 'error',
        'message' => 'Erro ao listar associados: ' . $e->getMessage(),
        'data' => []
    ];
}

// Retornar a resposta em formato JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
