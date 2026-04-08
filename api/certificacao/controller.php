<?php
/**
 * API Controller - Certificação Participativa
 * 
 * Gerencia operações CRUD para certificações SPG/OCS
 * Base: Portaria MAPA 52/2021
 */

// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir configuração do banco
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

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

// Obter método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

// Roteador de métodos
switch ($method) {
    case 'GET':
        handleGet($db);
        break;
    
    case 'POST':
        handlePost($db);
        break;
    
    case 'PUT':
        handlePut($db);
        break;
    
    case 'DELETE':
        handleDelete($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}

/**
 * GET - Listar certificações ou obter uma específica
 */
function handleGet($db) {
    // Se tem ID, busca uma certificação específica
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        getCertificacaoById($db, $_GET['id']);
        return;
    }
    
    // Caso contrário, lista todas (com filtros opcionais)
    listCertificacoes($db);
}

/**
 * Buscar certificação por ID
 */
function getCertificacaoById($db, $id) {
    $sql = "SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM certificacao_participativa c
            LEFT JOIN associados a ON c.associado_id = a.id
            WHERE c.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $certificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificacao) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }
    
    // Decodificar JSON
    if (!empty($certificacao['dados_adicionais'])) {
        $certificacao['dados_adicionais'] = json_decode($certificacao['dados_adicionais'], true);
    }
    
    http_response_code(200);
    echo json_encode($certificacao);
}

/**
 * Listar certificações com filtros
 */
function listCertificacoes($db) {
    $sql = "SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM certificacao_participativa c
            LEFT JOIN associados a ON c.associado_id = a.id
            WHERE 1=1";
    
    $params = [];
    
    // Filtros opcionais
    if (isset($_GET['tipo_certificacao']) && !empty($_GET['tipo_certificacao'])) {
        $sql .= " AND c.tipo_certificacao = ?";
        $params[] = $_GET['tipo_certificacao'];
    }
    
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
        $sql .= " AND c.associado_id = ?";
        $params[] = $_GET['associado_id'];
    }
    
    // Filtro para certificações vencidas/a vencer
    if (isset($_GET['validade']) && $_GET['validade'] === 'vencidas') {
        $sql .= " AND c.data_validade < CURDATE()";
    } elseif (isset($_GET['validade']) && $_GET['validade'] === 'proximas') {
        // Vence nos próximos 30 dias
        $sql .= " AND c.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
    
    // Ordenação
    $sql .= " ORDER BY c.data_validade DESC, c.created_at DESC";
    
    // Paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Contar total de registros
    $countSql = "SELECT COUNT(*) as total FROM certificacao_participativa c WHERE 1=1";
    foreach ($params as $param) {
        $countSql .= " AND ?";
    }
    $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM (" . str_replace("SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome", "SELECT c.id", $sql) . ") as subquery");
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Buscar registros com paginação
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $certificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON de dados adicionais
    foreach ($certificacoes as &$cert) {
        if (!empty($cert['dados_adicionais'])) {
            $cert['dados_adicionais'] = json_decode($cert['dados_adicionais'], true);
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'data' => $certificacoes,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * POST - Criar nova certificação
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validações
    if (empty($input['associado_id']) || !is_numeric($input['associado_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'associado_id é obrigatório']);
        return;
    }
    
    if (empty($input['tipo_certificacao'])) {
        $input['tipo_certificacao'] = 'SPG'; // Default
    }
    
    // Validar tipo de certificação
    $tiposPermitidos = ['SPG', 'OCS', 'Rede Ecovida', 'Outro'];
    if (!in_array($input['tipo_certificacao'], $tiposPermitidos)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de certificação inválido']);
        return;
    }
    
    // Campos opcionais
    $numero_certificado = $input['numero_certificado'] ?? null;
    $data_emissao = $input['data_emissao'] ?? null;
    $data_validade = $input['data_validade'] ?? null;
    $status = $input['status'] ?? 'Em Conversão';
    $organismo_certificador = $input['organismo_certificador'] ?? null;
    $nome_grupo = $input['nome_grupo'] ?? null;
    $documento_url = $input['documento_url'] ?? null;
    $declaracao_compromisso_url = $input['declaracao_compromisso_url'] ?? null;
    $observacoes = $input['observacoes'] ?? null;
    
    // Dados adicionais (JSON)
    $dados_adicionais = null;
    if (isset($input['dados_adicionais']) && is_array($input['dados_adicionais'])) {
        $dados_adicionais = json_encode($input['dados_adicionais']);
    }
    
    // Inserir no banco
    $sql = "INSERT INTO certificacao_participativa 
            (associado_id, tipo_certificacao, numero_certificado, data_emissao, data_validade, 
             status, organismo_certificador, nome_grupo, documento_url, declaracao_compromisso_url, 
             dados_adicionais, observacoes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $input['associado_id'],
            $input['tipo_certificacao'],
            $numero_certificado,
            $data_emissao,
            $data_validade,
            $status,
            $organismo_certificador,
            $nome_grupo,
            $documento_url,
            $declaracao_compromisso_url,
            $dados_adicionais,
            $observacoes
        ]);
        
        $id = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação criada com sucesso',
            'id' => $id
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao criar certificação',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * PUT - Atualizar certificação existente
 */
function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id']) || !is_numeric($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID é obrigatório']);
        return;
    }
    
    // Verificar se a certificação existe
    $checkSql = "SELECT id FROM certificacao_participativa WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$input['id']]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }
    
    // Construir SQL dinâmico apenas para campos fornecidos
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'tipo_certificacao', 'numero_certificado', 'data_emissao', 'data_validade',
        'status', 'organismo_certificador', 'nome_grupo', 'documento_url',
        'declaracao_compromisso_url', 'observacoes'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    // Dados adicionais (JSON)
    if (isset($input['dados_adicionais']) && is_array($input['dados_adicionais'])) {
        $fields[] = "dados_adicionais = ?";
        $values[] = json_encode($input['dados_adicionais']);
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum campo para atualizar']);
        return;
    }
    
    // Adicionar updated_at
    $fields[] = "updated_at = NOW()";
    
    // Adicionar ID ao final
    $values[] = $input['id'];
    
    $sql = "UPDATE certificacao_participativa SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação atualizada com sucesso'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao atualizar certificação',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * DELETE - Excluir certificação
 */
function handleDelete($db) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID é obrigatório']);
        return;
    }
    
    $id = $_GET['id'];
    
    // Verificar se existe
    $checkSql = "SELECT id FROM certificacao_participativa WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }
    
    // Excluir
    $sql = "DELETE FROM certificacao_participativa WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação excluída com sucesso'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao excluir certificação',
            'message' => $e->getMessage()
        ]);
    }
}
