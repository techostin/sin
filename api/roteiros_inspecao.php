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
require_once __DIR__ . '/../classes/VisitaTecnica.php';
require_once __DIR__ . '/../classes/RoteiroInspecao.php';
require_once __DIR__ . '/../classes/VisitaReport.php';

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
$action = $_GET['action'] ?? 'visitas'; // 'templates', 'visitas', 'nao_conformidades', 'relatorio'

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
        $manager = new RoteiroInspecao();
        echo json_encode(['success' => true, 'data' => $manager->getAll()]);
    } elseif ($action === 'nao_conformidades') {
        listNaoConformidades($db);
    } elseif ($action === 'relatorio') {
        gerarRelatorioPdf();
    } else {
        listVisitas($db);
    }
}

/**
 * Gera o relatório PDF de uma visita específica
 */
function gerarRelatorioPdf() {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        die("ID da visita não informado");
    }

    // Se não for numérico, buscar pelo hashid para obter o id real
    if (!is_numeric($id)) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM visitas_tecnicas WHERE hashid = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $id = $row['id'];
        } else {
            http_response_code(404);
            die("Visita não encontrada");
        }
    }

    try {
        $report = new VisitaReport($id);
        $pdf = $report->gerarRelatorioVisita();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $pdf['filename'] . '"');
        echo $pdf['content'];
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        die("Erro ao gerar PDF: " . $e->getMessage());
    }
}

function listTemplates($db) {
    $manager = new RoteiroInspecao();
    echo json_encode(['success' => true, 'data' => $manager->getAll()]);
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

    // Controle de acesso por nível:
    // Usuário comum (nivel 3) vê apenas suas próprias visitas
    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
    if ($userNivel >= NIVEL_USUARIO) {
        $associadoId = $_SESSION['user_id'] ?? null;
        $userTable   = $_SESSION['user_table'] ?? 'associados';
        // Só filtra automaticamente se o login foi pela tabela de associados
        if ($userTable === 'associados' && $associadoId) {
            $sql .= " AND v.associado_id = ?";
            $params[] = $associadoId;
        }
    }
    
    if (isset($_GET['id'])) {
        // Aceita tanto id numérico quanto hashid
        if (is_numeric($_GET['id'])) {
            $sql .= " AND v.id = ?";
        } else {
            $sql .= " AND v.hashid = ?";
        }
        $params[] = $_GET['id'];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $data['resultado'] = json_decode($data['resultado'], true);
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }
    
    $sql .= " ORDER BY v.data_visita DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Passa o nível do usuário para o frontend adaptar a UI
    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
    echo json_encode([
        'success'    => true,
        'data'       => $data,
        'user_nivel' => (int)$userNivel
    ]);
}

function listNaoConformidades($db) {
    $sql = "SELECT nc.*, v.data_visita, a.nome as associado_nome, a.sobrenome as associado_sobrenome,
                   p.propriedade as propriedade_nome, r.nome as roteiro_nome
            FROM nao_conformidades nc
            JOIN visitas_tecnicas v ON nc.visita_tecnica_id = v.id
            JOIN associados a ON v.associado_id = a.id
            JOIN propriedades p ON v.propriedade_id = p.id
            JOIN roteiros_inspecao r ON v.roteiro_id = r.id
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
    if ($action === 'upload_evidencia') {
        handleUploadEvidencia($db);
        return;
    } else if ($action === 'templates') {
        $input = json_decode(file_get_contents('php://input'), true);
        $manager = new RoteiroInspecao();
        if ($manager->save($input)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar template']);
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        createVisita($db, $input);
    }
}

function handleUploadEvidencia($db) {
    require_once __DIR__ . '/../classes/ImageUploader.php';
    
    if (!isset($_FILES['evidencia'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
        return;
    }

    try {
        $uploader = new ImageUploader(__DIR__ . '/../uploads/visitas_tecnicas/');
        $result = $uploader->uploadImage($_FILES['evidencia'], 'visita_evidencia');
        if ($result['success']) {
            $url = SITE_URL . '/uploads/visitas_tecnicas/' . $result['filename'];
            echo json_encode(['success' => true, 'url' => $url, 'filename' => $result['filename']]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $result['message']]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createVisita($db, $input) {
    if (empty($input['associado_id']) || empty($input['propriedade_id']) || empty($input['roteiro_id']) || empty($input['data_visita'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios ausentes']);
        return;
    }
    
    // Gerar hashid único
    $hashid = '';
    do {
        $hashid = substr(md5(uniqid(mt_rand(), true)), 0, 16);
        $stmtCheck = $db->prepare("SELECT id FROM visitas_tecnicas WHERE hashid = ?");
        $stmtCheck->execute([$hashid]);
    } while ($stmtCheck->fetch());
    
    $sql = "INSERT INTO visitas_tecnicas (hashid, associado_id, propriedade_id, roteiro_id, data_visita, 
                                        tecnico_responsavel_id, acompanhantes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $hashid,
            $input['associado_id'], $input['propriedade_id'], $input['roteiro_id'],
            $input['data_visita'], $input['tecnico_responsavel_id'] ?? $_SESSION['user_id'],
            $input['acompanhantes'] ?? null, $input['status'] ?? 'Agendada'
        ]);
        
        $insertId = $db->lastInsertId();
        echo json_encode(['success' => true, 'id' => $insertId, 'hashid' => $hashid]);
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
        finalizarVisitaComClasse($input);
    } else {
        http_response_code(501);
    }
}

function finalizarVisitaComClasse($input) {
    if (empty($input['visita_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'visita_id obrigatório']);
        return;
    }

    try {
        $manager = new VisitaTecnica();
        $manager->saveResult(
            $input['visita_id'],
            $input['resultado'],
            $input['observacoes'] ?? null,
            [
                'produtor' => $input['assinatura_produtor_url'] ?? null,
                'tecnico' => $input['assinatura_tecnico_url'] ?? null
            ],
            $input['evidencias_fotos'] ?? []
        );
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao finalizar visita', 'message' => $e->getMessage()]);
    }
}

