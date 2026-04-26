<?php
/**
 * API Controller - Plano de Manejo Orgânico 
 * 
 * Gerencia o Plano de Manejo conforme Portaria MAPA 52/2021
 */

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

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
 * GET - Listar planos ou obter um específico
 */
function handleGet($db) {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        getPlanoById($db, $_GET['id']);
        return;
    }
    
    listPlanos($db);
}

function getPlanoById($db, $id) {
    $sql = "SELECT p.*, pr.propriedade as propriedade_nome, a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM plano_manejo p
            JOIN propriedades pr ON p.propriedade_id = pr.id
            JOIN associados a ON p.associado_id = a.id
            WHERE p.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plano) {
        http_response_code(404);
        echo json_encode(['error' => 'Plano de manejo não encontrado']);
        return;
    }
    
    // Cultivos
    $sqlCultivos = "SELECT * FROM plano_manejo_cultivos WHERE plano_manejo_id = ?";
    $stmtC = $db->prepare($sqlCultivos);
    $stmtC->execute([$id]);
    $plano['cultivos'] = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $plano]);
}

function listPlanos($db) {
    $sql = "SELECT p.id, p.ano_safra, pr.propriedade as propriedade_nome, a.nome as associado_nome, a.sobrenome as associado_sobrenome,
                p.status, p.versao, p.created_at, 'Plano' as tipo_registro
            FROM plano_manejo p
            JOIN propriedades pr ON p.propriedade_id = pr.id
            JOIN associados a ON p.associado_id = a.id 
            ORDER BY p.ano_safra DESC, p.created_at DESC";
    
    $stmt = $db->query($sql);
    $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $planos]);
}

/**
 * POST - Criar novo plano
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validações básicas
    $required = ['propriedade_id', 'associado_id', 'ano_safra', 'titulo'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "$field é obrigatório"]);
            return;
        }
    }
    
    try {
        $db->beginTransaction();
        
        // Inserir plano principal
        $sql = "INSERT INTO plano_manejo (propriedade_id, associado_id, ano_safra, titulo, versao, status, observacoes, area_organica_ha, area_conversao_ha, descricao_solo, topografia, manejo_pragas, adubacao_verde, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 'Rascunho', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $input['propriedade_id'],
            $input['associado_id'],
            $input['ano_safra'],
            $input['titulo'],
            $input['observacoes'] ?? '',
            $input['area_organica_ha'] ?? 0,
            $input['area_conversao_ha'] ?? 0,
            $input['descricao_solo'] ?? '',
            $input['topografia'] ?? 'Plana',
            $input['manejo_pragas'] ?? '',
            $input['adubacao_verde'] ?? ''
        ]);
        
        $plano_id = $db->lastInsertId();
        
        // Cultivos
        if (!empty($input['cultivos']) && is_array($input['cultivos'])) {
            $sqlCultivo = "INSERT INTO plano_manejo_cultivos (plano_manejo_id, cultura, area_cultivada_ha, tipo_semente, observacoes) VALUES (?, ?, ?, ?, ?)";
            $stmtC = $db->prepare($sqlCultivo);
            foreach ($input['cultivos'] as $cultivo) {
                $stmtC->execute([
                    $plano_id,
                    $cultivo['cultura'],
                    $cultivo['area_cultivada_ha'] ?? 0,
                    $cultivo['tipo_semente'] ?? 'Orgânica Certificada',
                    $cultivo['observacoes'] ?? ''
                ]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'id' => $plano_id, 
            'message' => 'Plano de manejo criado com sucesso!'
        ]);
        
    } catch (PDOException $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar', 'details' => $e->getMessage()]);
    }
}

/**
 * PUT - Atualizar plano
 */
function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do plano obrigatório']);
        return;
    }
    
    $setFields = ['titulo = ?', 'ano_safra = ?', 'status = ?', 'observacoes = ?'];
    $values = [$input['titulo'], $input['ano_safra'], $input['status'], $input['observacoes'] ?? ''];
    $values[] = $input['id'];
    
    $sql = "UPDATE plano_manejo SET " . implode(', ', $setFields) . ", updated_at = NOW() WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    
    echo json_encode(['success' => true, 'message' => 'Plano atualizado']);
}

/**
 * DELETE - Excluir plano
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido']);
        return;
    }
    
    $sql = "DELETE FROM plano_manejo WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}
?>

