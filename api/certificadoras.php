<?php
// API Controller - Certificadoras (arquivo único em /api)
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ImageUploader.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user = new User();
if (!$user->isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Acesso não autorizado']); exit; }

// Suporta túnel POST(_method=PUT) para permitir envio de arquivos (multipart)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && (($_POST['_method'] ?? '') === 'PUT')) {
    $method = 'PUT';
}

// Proteção CSRF para métodos de escrita
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_once __DIR__ . '/../classes/CSRFProtection.php';
    $csrfToken = CSRFProtection::tokenFromRequest();
    if (!CSRFProtection::validateToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido ou ausente.']);
        exit;
    }
}

$db = Database::getInstance()->getConnection();

switch($method) {
    case 'GET': handleGet($db); break;
    case 'POST': handlePost($db); break;
    case 'PUT': handlePut($db); break;
    case 'DELETE': handleDelete($db); break;
    default: http_response_code(405); echo json_encode(['error'=>'Método não permitido']); break;
}

/**
 * Lê o corpo da requisição, aceitando tanto JSON (application/json)
 * quanto multipart/form-data (usado para upload de arquivos).
 */
function getInput() {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'multipart/form-data') !== false) {
        $data = $_POST;
        unset($data['_method']);
        return $data;
    }
    $raw = file_get_contents('php://input');
    $dec = json_decode($raw, true);
    return is_array($dec) ? $dec : [];
}

/**
 * Processa o upload do arquivo de logo (campo "logo") usando ImageUploader.
 * Em caso de sucesso, define $input['logo_filename'] e $input['logo_url'].
 * Se nenhum arquivo for enviado, não altera nada.
 */
function processLogo(&$input) {
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        return;
    }
    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Erro no upload do logo (código ' . $_FILES['logo']['error'] . ').']);
        exit;
    }
    $uploader = new ImageUploader(__DIR__ . '/../uploads/certificadoras/');
    // Logos são pequenas: limita a 2MB e redimensiona para no máximo 400x400
    $uploader->setConfig([
        'maxFileSize' => 2 * 1024 * 1024,
        'maxWidth'    => 400,
        'maxHeight'   => 400,
        'quality'     => 90,
    ]);
    $result = $uploader->uploadImage($_FILES['logo'], 'logo');
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode(['error' => $result['message']]);
        exit;
    }
    $input['logo_filename'] = $result['filename'];
    $input['logo_url'] = rtrim(SITE_URL, '/') . '/uploads/certificadoras/' . $result['filename'];
}

function handleGet($db) {
    // Apenas administradores e gestores podem consultar certificadoras
    if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] > NIVEL_GESTOR) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado.']);
        return;
    }

    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM certificadoras WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'Não encontrada']); return; }
        if (!empty($row['dados_adicionais'])) $row['dados_adicionais'] = json_decode($row['dados_adicionais'], true);
        echo json_encode($row);
        return;
    }
    // lista com filtros simples
    $sql = "SELECT * FROM certificadoras WHERE 1=1";
    $params = [];
    if (!empty($_GET['uf'])) { $sql .= " AND uf = ?"; $params[] = $_GET['uf']; }
    if (!empty($_GET['cidade'])) { $sql .= " AND cidade = ?"; $params[] = $_GET['cidade']; }
    $sql .= " ORDER BY nome ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) if (!empty($r['dados_adicionais'])) $r['dados_adicionais'] = json_decode($r['dados_adicionais'], true);
    echo json_encode(['data'=>$rows]);
}

function handlePost($db) {
    if (!$_SESSION['user_nivel'] || $_SESSION['user_nivel'] > NIVEL_GESTOR) { http_response_code(403); echo json_encode(['error'=>'Acesso negado']); return; }
    $input = getInput();
    if (empty($input['nome'])) { http_response_code(400); echo json_encode(['error'=>'nome é obrigatório']); return; }

    processLogo($input);

    $sql = "INSERT INTO certificadoras (nome, cnpj, endereco, cidade, uf, telefone, email, site, contato, tipo, logo_filename, logo_url, gestor_id, dados_adicionais, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    $dados = [
        $input['nome'] ?? null,
        $input['cnpj'] ?? null,
        $input['endereco'] ?? null,
        $input['cidade'] ?? null,
        $input['uf'] ?? null,
        $input['telefone'] ?? null,
        $input['email'] ?? null,
        $input['site'] ?? null,
        $input['contato'] ?? null,
        $input['tipo'] ?? null,
        $input['logo_filename'] ?? null,
        $input['logo_url'] ?? null,
        $input['gestor_id'] ?? $_SESSION['user_id'],
        isset($input['dados_adicionais']) ? json_encode($input['dados_adicionais']) : null
    ];
    try { $stmt->execute($dados); $id = $db->lastInsertId(); http_response_code(201); echo json_encode(['success'=>true,'id'=>$id]); }
    catch(PDOException $e) { http_response_code(500); echo json_encode(['error'=>'Erro ao inserir','message'=>$e->getMessage()]); }
}

function handlePut($db) {
    if (!$_SESSION['user_nivel'] || $_SESSION['user_nivel'] > NIVEL_GESTOR) { http_response_code(403); echo json_encode(['error'=>'Acesso negado']); return; }
    $input = getInput();
    if (empty($input['id']) || !is_numeric($input['id'])) { http_response_code(400); echo json_encode(['error'=>'id é obrigatório']); return; }

    processLogo($input);

    $fields = [];
    $vals = [];
    $allowed = ['nome','cnpj','endereco','cidade','uf','telefone','email','site','contato','tipo','logo_filename','logo_url','gestor_id','dados_adicionais'];
    foreach ($allowed as $f) {
        // Não sobrescreve com vazio campos de logo quando não houve upload novo
        if ($f === 'logo_filename' || $f === 'logo_url') {
            if (array_key_exists($f, $input) && $input[$f] !== '' && $input[$f] !== null) {
                $fields[] = "$f = ?";
                $vals[] = $f === 'dados_adicionais' && is_array($input[$f]) ? json_encode($input[$f]) : $input[$f];
            }
            continue;
        }
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = ?";
            $vals[] = $f === 'dados_adicionais' && is_array($input[$f]) ? json_encode($input[$f]) : $input[$f];
        }
    }
    if (empty($fields)) { http_response_code(400); echo json_encode(['error'=>'Nenhum campo para atualizar']); return; }
    $vals[] = $input['id'];
    $sql = "UPDATE certificadoras SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
    try { $stmt = $db->prepare($sql); $stmt->execute($vals); echo json_encode(['success'=>true]); }
    catch(PDOException $e) { http_response_code(500); echo json_encode(['error'=>'Erro ao atualizar','message'=>$e->getMessage()]); }
}

function handleDelete($db) {
    if (!$_SESSION['user_nivel'] || $_SESSION['user_nivel'] > NIVEL_GESTOR) { http_response_code(403); echo json_encode(['error'=>'Acesso negado']); return; }
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { http_response_code(400); echo json_encode(['error'=>'id é obrigatório']); return; }
    $id = (int)$_GET['id'];
    try { $stmt = $db->prepare("DELETE FROM certificadoras WHERE id = ?"); $stmt->execute([$id]); echo json_encode(['success'=>true]); }
    catch(PDOException $e) { http_response_code(500); echo json_encode(['error'=>'Erro ao excluir','message'=>$e->getMessage()]); }
}

?>
