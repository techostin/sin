<?php
/**
 * API Controller - Insumos Orgânicos Aprovados
 * 
 * Consulta lista de insumos permitidos conforme Portaria MAPA 52/2021
 */

// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

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

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit();
}

// Filtros
$categoria = $_GET['categoria'] ?? null;
$busca = $_GET['q'] ?? null;
$permitido = isset($_GET['permitido']) ? (bool)$_GET['permitido'] : null;

$sql = "SELECT * FROM insumos_organicos_aprovados WHERE 1=1";
$params = [];

if ($categoria) {
    $sql .= " AND categoria = ?";
    $params[] = $categoria;
}

if ($busca) {
    $sql .= " AND (nome LIKE ? OR nome_comercial LIKE ? OR principio_ativo LIKE ?)";
    $like = "%$busca%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($permitido !== null) {
    $sql .= " AND permitido = ?";
    $params[] = $permitido ? 1 : 0;
}

$sql .= " ORDER BY categoria, nome";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $insumos,
        'total' => count($insumos)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar insumos',
        'message' => $e->getMessage()
    ]);
}
