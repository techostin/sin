<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$db        = Database::getInstance()->getConnection();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = $_GET['action'] ?? 'list';
$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$isAdmin   = $userNivel <= NIVEL_GESTOR;
$userId    = $_SESSION['user_id']   ?? null;
$userTable = $_SESSION['user_table'] ?? 'associados';

try {
    match ($method) {
        'GET'  => handleGet($db, $action, $isAdmin, $userId, $userTable),
        'PUT'  => handlePut($db, $isAdmin),
        'POST' => handlePost($db, $action),
        default => jsonError(405, 'Método não permitido'),
    };
} catch (Exception $e) {
    jsonError(500, $e->getMessage());
}

// ─── GET ──────────────────────────────────────────────────────────────────────

function handleGet($db, $action, $isAdmin, $userId, $userTable) {
    if ($action === 'detail' && isset($_GET['id'])) {
        return getDetail($db, (int)$_GET['id'], $isAdmin, $userId, $userTable);
    }
    listNCs($db, $isAdmin, $userId, $userTable);
}

function listNCs($db, $isAdmin, $userId, $userTable) {
    $sql = "SELECT nc.*,
                   vt.data_visita,
                   a.nome    AS associado_nome,
                   a.sobrenome AS associado_sobrenome,
                   p.propriedade AS propriedade_nome,
                   r.nome    AS roteiro_nome,
                   r.checklist AS roteiro_checklist
            FROM nao_conformidades nc
            JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id
            JOIN associados a  ON vt.associado_id   = a.id
            JOIN propriedades p ON vt.propriedade_id = p.id
            JOIN roteiros_inspecao r ON vt.roteiro_id = r.id
            WHERE 1=1";

    $params = [];

    // Usuário comum vê apenas as NCs vinculadas às suas visitas
    if (!$isAdmin && $userTable === 'associados') {
        $sql    .= " AND vt.associado_id = ?";
        $params[] = $userId;
    }

    // Filtros opcionais via query string
    if (!empty($_GET['status'])) {
        $sql    .= " AND nc.status = ?";
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['gravidade'])) {
        $sql    .= " AND nc.gravidade = ?";
        $params[] = $_GET['gravidade'];
    }

    $sql .= " ORDER BY nc.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Enriquecer cada NC com o texto real do item do checklist
    foreach ($data as &$row) {
        $row['item_texto'] = resolveItemText($row['roteiro_checklist'] ?? null, $row['item_checklist_id']);
        unset($row['roteiro_checklist']); // não enviar JSON bruto ao cliente
        if (empty($row['descricao'])) {
            $row['descricao'] = $row['item_texto'];
        }
    }
    unset($row);

    // KPIs para os cards do dashboard
    $kpis = [
        'total'      => count($data),
        'abertas'    => count(array_filter($data, fn($r) => $r['status'] === 'Aberta')),
        'em_correcao'=> count(array_filter($data, fn($r) => $r['status'] === 'Em Correção')),
        'resolvidas' => count(array_filter($data, fn($r) => $r['status'] === 'Resolvida')),
        'vencidas'   => count(array_filter($data, fn($r) =>
            $r['prazo_correcao'] && $r['status'] !== 'Resolvida'
            && strtotime($r['prazo_correcao']) < time()
        )),
    ];

    echo json_encode(['success' => true, 'data' => $data, 'kpis' => $kpis, 'is_admin' => $isAdmin]);
}

function getDetail($db, $id, $isAdmin, $userId, $userTable) {
    $sql = "SELECT nc.*,
                   vt.data_visita,
                   a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                   p.propriedade AS propriedade_nome,
                   r.nome AS roteiro_nome,
                   r.checklist AS roteiro_checklist
            FROM nao_conformidades nc
            JOIN visitas_tecnicas vt ON nc.visita_tecnica_id = vt.id
            JOIN associados a  ON vt.associado_id   = a.id
            JOIN propriedades p ON vt.propriedade_id = p.id
            JOIN roteiros_inspecao r ON vt.roteiro_id = r.id
            WHERE nc.id = ?";
    $params = [$id];

    if (!$isAdmin && $userTable === 'associados') {
        $sql    .= " AND vt.associado_id = ?";
        $params[] = $userId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonError(404, 'Não conformidade não encontrada');
        return;
    }
    $row['item_texto'] = resolveItemText($row['roteiro_checklist'] ?? null, $row['item_checklist_id']);
    unset($row['roteiro_checklist']);
    if (empty($row['descricao'])) {
        $row['descricao'] = $row['item_texto'];
    }
    echo json_encode(['success' => true, 'data' => $row]);
}

/**
 * Resolve o texto da pergunta de um item do checklist a partir do JSON do roteiro.
 */
function resolveItemText(?string $checklistJson, $itemId): string {
    if (!$checklistJson) return "Item #{$itemId}";
    $items = json_decode($checklistJson, true);
    if (!is_array($items)) return "Item #{$itemId}";
    foreach ($items as $item) {
        if ((string)($item['id'] ?? '') === (string)$itemId) {
            return $item['item'] ?? $item['pergunta'] ?? "Item #{$itemId}";
        }
    }
    return "Item #{$itemId}";
}

// ─── PUT (resolver / atualizar status) ────────────────────────────────────────

function handlePut($db, $isAdmin) {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($body['id'] ?? 0);

    if (!$id) { jsonError(400, 'ID inválido'); return; }

    // Campos atualizáveis
    $status          = $body['status']           ?? null;
    $acaoCorretiva   = $body['acao_corretiva']   ?? null;
    $responsavel     = $body['responsavel_correcao'] ?? null;
    $prazo           = $body['prazo_correcao']   ?? null;
    $observacoes     = $body['observacoes']       ?? null;

    $setParts  = [];
    $params    = [];

    if ($status)        { $setParts[] = 'status = ?';               $params[] = $status; }
    if ($acaoCorretiva) { $setParts[] = 'acao_corretiva = ?';       $params[] = $acaoCorretiva; }
    if ($responsavel)   { $setParts[] = 'responsavel_correcao = ?'; $params[] = $responsavel; }
    if ($prazo)         { $setParts[] = 'prazo_correcao = ?';       $params[] = $prazo; }
    if ($observacoes !== null) { $setParts[] = 'observacoes = ?';   $params[] = $observacoes; }

    // Se resolvido, marcar data
    if ($status === 'Resolvida') {
        $setParts[] = 'data_resolucao = NOW()';
        $setParts[] = 'verificado_por = ?';
        $params[]   = $_SESSION['user_nome'] ?? 'Sistema';
    }

    $setParts[] = 'updated_at = NOW()';
    $params[]   = $id;

    $sql  = "UPDATE nao_conformidades SET " . implode(', ', $setParts) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Não conformidade atualizada.']);
}

// ─── POST (upload de evidências) ──────────────────────────────────────────────

function handlePost($db, $action) {
    if ($action === 'upload_evidencia') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            jsonError(400, 'ID inválido');
            return;
        }

        if (empty($_FILES['evidencia_foto'])) {
            jsonError(400, 'Nenhum arquivo enviado');
            return;
        }

        require_once __DIR__ . '/../classes/ImageUploader.php';
        
        // Configurar uploader para salvar em uploads/evidencias/
        $uploader = new ImageUploader('uploads/evidencias/');
        $uploadResult = $uploader->uploadImage($_FILES['evidencia_foto'], 'evidencia_nc_' . $id);

        if ($uploadResult['success']) {
            $filepath = $uploadResult['filepath'];
            
            // Salvar no banco de dados
            $stmt = $db->prepare("UPDATE nao_conformidades SET evidencia_correcao_url = ?, status = 'Em Correção', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$filepath, $id]);

            echo json_encode(['success' => true, 'filepath' => $filepath]);
        } else {
            jsonError(400, $uploadResult['message']);
        }
        return;
    }
    jsonError(400, 'Ação inválida');
}

function jsonError($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
