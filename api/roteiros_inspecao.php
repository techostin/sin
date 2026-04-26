<?php
/**
 * API Controller - Roteiros de Inspeção e Visitas Técnicas
 * 
 * Gerencia templates de roteiros, visitas técnicas e não-conformidades
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
$action = $_GET['action'] ?? 'visitas'; // 'templates', 'visitas', 'nao_conformidades'

// Roteador de métodos e ações
switch ($method) {
    case 'GET':
        handleGet($db, $action);
        break;
    case 'POST':
        handlePost($db, $action);
        break;
    case 'PUT':
        handlePut($db, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}

/**
 * GET - Listagem baseada na ação
 */
function handleGet($db, $action) {
    if ($action === 'templates') {
        listTemplates($db);
    } elseif ($action === 'nao_conformidades') {
        listNaoConformidades($db);
    } else {
        listVisitas($db);
    }
}

function listTemplates($db) {
    $sql = "SELECT * FROM roteiros_inspecao WHERE ativo = 1 ORDER BY nome";
    $stmt = $db->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON
    foreach ($data as &$item) {
        $item['checklist'] = json_decode($item['checklist'], true);
        if (!empty($item['itens_criticos'])) {
            $item['itens_criticos'] = json_decode($item['itens_criticos'], true);
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function listVisitas($db) {
    $sql = "SELECT v.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome, 
                   p.propriedade as propriedade_nome, r.nome as roteiro_nome
            FROM visitas_tecnicas v
            JOIN associados a ON v.associado_id = a.id
            JOIN propriedades p ON v.propriedade_id = p.id
            JOIN roteiros_inspecao r ON v.roteiro_id = r.id
            WHERE 1=1";
    
    $params = [];
    if (isset($_GET['associado_id'])) {
        $sql .= " AND v.associado_id = ?";
        $params[] = $_GET['associado_id'];
    }
    
    $sql .= " ORDER BY v.data_visita DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function listNaoConformidades($db) {
    $sql = "SELECT nc.*, v.data_visita, a.nome as associado_nome, a.sobrenome as associado_sobrenome,
                   p.propriedade as propriedade_nome
            FROM nao_conformidades nc
            JOIN visitas_tecnicas v ON nc.visita_tecnica_id = v.id
            JOIN associados a ON v.associado_id = a.id
            JOIN propriedades p ON v.propriedade_id = p.id
            WHERE 1=1";
    
    $params = [];
    if (isset($_GET['status'])) {
        $sql .= " AND nc.status = ?";
        $params[] = $_GET['status'];
    }
    
    $sql .= " ORDER BY nc.prazo_correcao ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * POST - Criar novos registros
 */
function handlePost($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'templates') {
        createTemplate($db, $input);
    } elseif ($action === 'nao_conformidades') {
        http_response_code(501); // Implementar se necessário
    } else {
        createVisita($db, $input);
    }
}

function createTemplate($db, $input) {
    if (empty($input['nome']) || empty($input['checklist'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome e checklist são obrigatórios']);
        return;
    }
    
    $sql = "INSERT INTO roteiros_inspecao (nome, codigo, tipo, descricao, objetivo, checklist, itens_criticos)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $input['nome'], $input['codigo'] ?? null, $input['tipo'] ?? 'Visita Regular',
            $input['descricao'] ?? null, $input['objetivo'] ?? null,
            json_encode($input['checklist']), 
            isset($input['itens_criticos']) ? json_encode($input['itens_criticos']) : null
        ]);
        
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao criar template', 'message' => $e->getMessage()]);
    }
}

function createVisita($db, $input) {
    if (empty($input['associado_id']) || empty($input['propriedade_id']) || empty($input['roteiro_id']) || empty($input['data_visita'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios ausentes']);
        return;
    }
    
    $sql = "INSERT INTO visitas_tecnicas (associado_id, propriedade_id, roteiro_id, data_visita, 
                                        tecnico_responsavel_id, acompanhantes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $input['associado_id'], $input['propriedade_id'], $input['roteiro_id'],
            $input['data_visita'], $input['tecnico_responsavel_id'] ?? null,
            $input['acompanhantes'] ?? null, $input['status'] ?? 'Agendada'
        ]);
        
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao agendar visita', 'message' => $e->getMessage()]);
    }
}

/**
 * PUT - Atualizar registros (Finalizar Visita)
 */
function handlePut($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'visitas') {
        finalizarVisita($db, $input);
    } else {
        http_response_code(501);
    }
}

function finalizarVisita($db, $input) {
    if (empty($input['visita_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'visita_id obrigatório']);
        return;
    }

    try {
        $db->beginTransaction();

        $sql = "UPDATE visitas_tecnicas SET 
                resultado = ?, 
                status = ?, 
                observacoes = ?, 
                evidencias_fotos = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            json_encode($input['resultado']),
            $input['status'] ?? 'Realizada',
            $input['parecer_geral'] ?? null,
            $input['evidencias_fotos'] ?? null,
            $input['visita_id']
        ]);

        // Gerar Não Conformidades automaticamente
        if (!empty($input['resultado'])) {
            foreach ($input['resultado'] as $itemId => $res) {
                if (isset($res['conforme']) && $res['conforme'] === false) {
                    $sqlNC = "INSERT INTO nao_conformidades (visita_tecnica_id, item_checklist_id, descricao, status)
                             VALUES (?, ?, ?, ?)";
                    $stmtNC = $db->prepare($sqlNC);
                    $stmtNC->execute([
                        $input['visita_id'], 
                        $itemId, 
                        $res['observacao'] ?? 'Não conformidade identificada na inspeção.',
                        'Aberta'
                    ]);
                }
            }
        }

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao finalizar visita', 'message' => $e->getMessage()]);
    }
}
