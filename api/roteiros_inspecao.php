<?php
/**
 * API Controller - Roteiros de Inspeção e Visitas Técnicas
 * 
 * Gerencia templates de roteiros, visitas técnicas e não-conformidades
 */

// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ApiAuth.php';
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

// Proteção CSRF para métodos de escrita
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
    require_once __DIR__ . '/../classes/CSRFProtection.php';
    if (!CSRFProtection::validateToken(CSRFProtection::tokenFromRequest())) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido ou ausente.']);
        exit();
    }
}

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
    case 'DELETE':
        handleDelete($db, $action);
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
    } elseif ($action === 'visitas_ciente_pendentes') {
        listVisitasCientePendentes($db);
    } elseif ($action === 'visitas_recusadas_pendentes') {
        listVisitasRecusadasPendentes($db);
    } elseif ($action === 'visitas_agendadas_status') {
        listVisitasAgendadasStatus($db);
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
    // Usuário comum (nivel 3) vê suas próprias visitas + as que agendou como técnico
    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
    if ($userNivel >= NIVEL_USUARIO) {
        $associadoId = $_SESSION['user_id'] ?? null;
        $userTable   = $_SESSION['user_table'] ?? 'associados';
        // Só filtra automaticamente se o login foi pela tabela de associados
        if ($userTable === 'associados' && $associadoId) {
            $sql .= " AND (v.associado_id = ? OR v.tecnico_responsavel_id = ?)";
            $params[] = $associadoId;
            $params[] = $associadoId;
        }
    }
    
    if (isset($_GET['id'])) {
        $sql .= " AND v.id = ?";
        $params[] = $_GET['id'];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $data['resultado'] = !empty($data['resultado']) ? json_decode($data['resultado'], true) : null;
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

/**
 * Retorna as visitas agendadas ainda não confirmadas (ciente = 0) para o
 * agricultor logado, cuja data ainda não expirou. Usado no modal de ciência.
 */
function listVisitasCientePendentes($db) {
    $userTable = $_SESSION['user_table'] ?? 'associados';
    $userId    = $_SESSION['user_id'] ?? null;

    // Permite visualizar a lista apenas para o próprio agricultor logado
    if ($userTable !== 'associados' || !$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao agricultor']);
        return;
    }

    $sql = "SELECT v.id, v.hashid, v.data_visita, v.hora_inicio, v.status, v.ciente,
                   v.ciente_recusada, p.propriedade as propriedade_nome,
                   r.nome as roteiro_nome,
                   CONCAT(t.nome, ' ', COALESCE(t.sobrenome, '')) AS tecnico_nome
            FROM visitas_tecnicas v
            JOIN propriedades p ON v.propriedade_id = p.id
            JOIN roteiros_inspecao r ON v.roteiro_id = r.id
            JOIN associados t ON v.tecnico_responsavel_id = t.id
            WHERE v.associado_id = ?
              AND v.status = 'Agendada'
              AND v.data_visita >= CURDATE()
              AND v.ciente = 0
              AND v.ciente_recusada = 0
            ORDER BY v.data_visita ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Retorna as visitas agendadas recusadas pelo agricultor (ciente_recusada = 1),
 * ainda não reagendadas. Usado no modal de reagendamento exibido ao gestor/técnico.
 */
function listVisitasRecusadasPendentes($db) {
    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;

    // Apenas gestor/técnico (nivel <= NIVEL_GESTOR) vê recusas de agricultores
    if ($userNivel > NIVEL_GESTOR) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao gestor/técnico']);
        return;
    }

    $sql = "SELECT v.id, v.hashid, v.data_visita, v.hora_inicio, v.status,
                   v.ciente_recusada_motivo, v.ciente_recusada_em,
                   CONCAT(a.nome, ' ', COALESCE(a.sobrenome, '')) AS associado_nome,
                   p.propriedade as propriedade_nome,
                   r.nome as roteiro_nome,
                   CONCAT(t.nome, ' ', COALESCE(t.sobrenome, '')) AS tecnico_nome
            FROM visitas_tecnicas v
            JOIN associados a ON v.associado_id = a.id
            JOIN propriedades p ON v.propriedade_id = p.id
            JOIN roteiros_inspecao r ON v.roteiro_id = r.id
            JOIN associados t ON v.tecnico_responsavel_id = t.id
            WHERE v.status = 'Agendada'
              AND v.data_visita >= CURDATE()
              AND v.ciente_recusada = 1
            ORDER BY v.ciente_recusada_em DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Retorna TODAS as visitas agendadas (data atual ou futura) com o status de
 * confirmação do agricultor: Confirmado (ciente=1), Recusou (ciente_recusada=1)
 * ou Pendente. Usado no modal do gestor/técnico para acompanhar confirmações
 * de vários agricultores e reagendar diretamente.
 */
/**
 * Resolve o id do técnico/gestor logado dentro de associados.
 * - user_table = 'associados'  -> o próprio user_id é o técnico (FK p/ associados)
 * - user_table = 'usuarios'    -> usa o associado_id vinculado ao usuário
 * Retorna null quando não há vínculo (nesse caso quem chama decide ver tudo).
 */
function resolveTecnicoId($db) {
    $userTable = $_SESSION['user_table'] ?? 'usuarios';
    $userId    = $_SESSION['user_id'] ?? null;

    if (!$userId) return null;

    if ($userTable === 'associados') {
        return (int)$userId;
    }

    $stmt = $db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $assocId = $stmt->fetchColumn();

    return $assocId ? (int)$assocId : null;
}

function listVisitasAgendadasStatus($db) {
    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;

    // Apenas gestor/técnico (nivel <= NIVEL_GESTOR)
    if ($userNivel > NIVEL_GESTOR) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao gestor/técnico']);
        return;
    }

    // Vínculo gestor/técnico ↔ visitas que agendou:
    // - se o usuário logou pela tabela associados, seu id é o técnico;
    // - se logou pela tabela usuarios, usa o associado_id vinculado;
    // - sem vínculo (ex. admins sem associado), vê todas.
    $tecnicoId = resolveTecnicoId($db);

    $sql = "SELECT v.id, v.hashid, v.data_visita, v.hora_inicio, v.status,
                   v.ciente, v.ciente_em,
                   v.ciente_recusada, v.ciente_recusada_motivo, v.ciente_recusada_em,
                   v.gestor_ciente, v.gestor_ciente_em,
                   v.gestor_ciente_user_id, v.gestor_ciente_user_table,
                   CONCAT(a.nome, ' ', COALESCE(a.sobrenome, '')) AS associado_nome,
                   p.propriedade as propriedade_nome,
                   r.nome as roteiro_nome,
                   CONCAT(t.nome, ' ', COALESCE(t.sobrenome, '')) AS tecnico_nome
            FROM visitas_tecnicas v
            JOIN associados a ON v.associado_id = a.id
            JOIN propriedades p ON v.propriedade_id = p.id
            JOIN roteiros_inspecao r ON v.roteiro_id = r.id
            JOIN associados t ON v.tecnico_responsavel_id = t.id
            WHERE v.status = 'Agendada'
              AND v.data_visita >= CURDATE()
              AND v.ciente = 0
              AND v.ciente_recusada = 0";

    $params = [];
    if ($tecnicoId) {
        $sql .= " AND v.tecnico_responsavel_id = ?";
        $params[] = $tecnicoId;
    }

    $sql .= " ORDER BY v.data_visita ASC, a.nome ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
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
    } else if ($action === 'confirmar_ciente') {
        confirmarCienteVisita($db);
        return;
    } else if ($action === 'recusar_ciente') {
        recusarCienteVisita($db);
        return;
    } else if ($action === 'reagendar_visita') {
        reagendarVisita($db);
        return;
    } else if ($action === 'gestor_ciente') {
        registrarGestorCiente($db);
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
    // Autorização: usuário comum (nível 3 sem cargo de diretoria) só agenda
    // visita para si mesmo e na própria propriedade. Admin/Gestor/Diretoria
    // Executiva podem agendar para qualquer produtor.
    if (!ApiAuth::ehStaff() && !ApiAuth::ehDiretoriaExecutiva()) {
        $meuAssociado = ApiAuth::associadoId();
        if ($meuAssociado <= 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado: usuário sem vínculo de associado']);
            return;
        }
        $input['associado_id'] = $meuAssociado;
        if (!empty($input['propriedade_id'])) {
            $stProp = $db->prepare("SELECT COUNT(*) FROM propriedades WHERE id = ? AND associado_id = ?");
            $stProp->execute([(int) $input['propriedade_id'], $meuAssociado]);
            if ((int) $stProp->fetchColumn() === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso negado: propriedade de outro produtor']);
                return;
            }
        }
        $input['tecnico_responsavel_id'] = $meuAssociado;
    }

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
            $input['data_visita'], $input['tecnico_responsavel_id'] ?? $_SESSION['user_id'],
            $input['acompanhantes'] ?? null, $input['status'] ?? 'Agendada'
        ]);
        
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao agendar visita', 'message' => $e->getMessage()]);
    }
}

/**
 * Confirma a ciência do agricultor sobre uma visita agendada.
 * Apenas o próprio associado (agricultor) pode confirmar a própria visita,
 * que deve estar 'Agendada' e com data ainda não expirada.
 */
function confirmarCienteVisita($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $visitaId = $input['visita_id'] ?? null;

    if (!$visitaId || !is_numeric($visitaId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'visita_id obrigatório']);
        return;
    }

    $userTable = $_SESSION['user_table'] ?? 'associados';
    $userId    = $_SESSION['user_id'] ?? null;

    // Apenas o agricultor dono da visita pode confirmar
    if ($userTable !== 'associados' || !$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao agricultor']);
        return;
    }

    try {
        $stmt = $db->prepare(
            "UPDATE visitas_tecnicas
             SET ciente = 1, ciente_em = NOW()
             WHERE id = ? AND associado_id = ?
               AND status = 'Agendada' AND data_visita >= CURDATE()"
        );
        $stmt->execute([$visitaId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Visita não encontrada, já confirmada ou fora do prazo']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao confirmar ciência', 'message' => $e->getMessage()]);
    }
}

/**
 * Registra a recusa do agricultor quanto à data agendada (pedido de
 * reagendamento). Apenas o próprio associado pode recusar a própria visita,
 * que deve estar 'Agendada' e com data ainda não expirada.
 */
function recusarCienteVisita($db) {
    $input  = json_decode(file_get_contents('php://input'), true);
    $visitaId = $input['visita_id'] ?? null;
    $motivo   = trim($input['motivo'] ?? '');

    if (!$visitaId || !is_numeric($visitaId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'visita_id obrigatório']);
        return;
    }

    $userTable = $_SESSION['user_table'] ?? 'associados';
    $userId    = $_SESSION['user_id'] ?? null;

    // Apenas o agricultor dono da visita pode recusar
    if ($userTable !== 'associados' || !$userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao agricultor']);
        return;
    }

    try {
        $stmt = $db->prepare(
            "UPDATE visitas_tecnicas
             SET ciente_recusada = 1,
                 ciente_recusada_motivo = ?,
                 ciente_recusada_em = NOW()
             WHERE id = ? AND associado_id = ?
               AND status = 'Agendada' AND data_visita >= CURDATE()
               AND ciente = 0 AND ciente_recusada = 0"
        );
        $stmt->execute([$motivo ?: null, $visitaId, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Visita não encontrada, já respondida ou fora do prazo']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao registrar recusa', 'message' => $e->getMessage()]);
    }
}

/**
 * Reagenda uma visita recusada pelo agricultor. Apenas gestor/técnico pode
 * reagendar. A nova data é definida, a recusa é limpa e a ciência volta a
 * ficar pendente (o agricultor verá o modal novamente).
 */
function reagendarVisita($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $visitaId  = $input['visita_id'] ?? null;
    $novaData  = $input['data_visita'] ?? null;
    $novaHora  = $input['hora_inicio'] ?? null;

    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
    $userTable = $_SESSION['user_table'] ?? 'usuarios';

    if (!$visitaId || !is_numeric($visitaId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'visita_id obrigatório']);
        return;
    }
    if (!$novaData || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $novaData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'data_visita obrigatória (AAAA-MM-DD)']);
        return;
    }
    if ($novaData < date('Y-m-d')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A nova data deve ser hoje ou futura']);
        return;
    }
    if ($novaHora !== null && $novaHora !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $novaHora)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'hora_inicio inválida']);
        return;
    }

    // Acesso: Admin/Gestor (usuarios, nivel <= NIVEL_GESTOR) ou
    // o Técnico responsável pela visita (login pela tabela associados).
    $userId = $_SESSION['user_id'] ?? null;
    $isGestor = ($userNivel <= NIVEL_GESTOR && $userTable === 'usuarios');
    $isTecnicoDaVisita = false;

    if (!$isGestor && $userTable === 'associados' && $userId) {
        $chk = $db->prepare("SELECT id FROM visitas_tecnicas WHERE id = ? AND tecnico_responsavel_id = ?");
        $chk->execute([$visitaId, $userId]);
        $isTecnicoDaVisita = (bool)$chk->fetchColumn();
    }

    if (!$isGestor && !$isTecnicoDaVisita) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao gestor/técnico']);
        return;
    }

    try {
        $stmt = $db->prepare(
            "UPDATE visitas_tecnicas
             SET data_visita = ?,
                 hora_inicio = COALESCE(?, hora_inicio),
                 ciente = 0, ciente_em = NULL,
                 ciente_recusada = 0, ciente_recusada_motivo = NULL, ciente_recusada_em = NULL
             WHERE id = ?
               AND status = 'Agendada'"
        );
        $stmt->execute([$novaData, $novaHora ?: null, $visitaId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Visita não encontrada ou não está agendada']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao reagendar visita', 'message' => $e->getMessage()]);
    }
}

/**
 * Registra a ciência do GESTOR/técnico sobre o status atual do agendamento
 * (Confirmado / Recusou / Pendente) para uma visita agendada. O registro é
 * individual por visita e fica salvo no banco para relatórios futuros.
 * Apenas gestor/técnico (nivel <= NIVEL_GESTOR) pode registrar.
 */
function registrarGestorCiente($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $visitaId = $input['visita_id'] ?? null;

    if (!$visitaId || !is_numeric($visitaId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'visita_id obrigatório']);
        return;
    }

    $userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
    $userTable = $_SESSION['user_table'] ?? 'usuarios';

    if ($userNivel > NIVEL_GESTOR) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao gestor/técnico']);
        return;
    }

    // Acesso: Admin/Gestor (usuarios, nivel <= NIVEL_GESTOR) ou
    // o Técnico responsável pela visita (login pela tabela associados).
    $userId = $_SESSION['user_id'] ?? null;
    $isGestor = ($userNivel <= NIVEL_GESTOR && $userTable === 'usuarios');
    $isTecnicoDaVisita = false;

    if (!$isGestor && $userTable === 'associados' && $userId) {
        $chk = $db->prepare("SELECT id FROM visitas_tecnicas WHERE id = ? AND tecnico_responsavel_id = ?");
        $chk->execute([$visitaId, $userId]);
        $isTecnicoDaVisita = (bool)$chk->fetchColumn();
    }

    if (!$isGestor && !$isTecnicoDaVisita) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao gestor/técnico']);
        return;
    }

    try {
        $stmt = $db->prepare(
            "UPDATE visitas_tecnicas
             SET gestor_ciente = 1,
                 gestor_ciente_em = NOW(),
                 gestor_ciente_user_id = ?,
                 gestor_ciente_user_table = ?
             WHERE id = ?
               AND status = 'Agendada'
               AND data_visita >= CURDATE()"
        );
        $stmt->execute([$userId, $userTable, $visitaId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Visita não encontrada, já finalizada ou fora do prazo']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao registrar ciência', 'message' => $e->getMessage()]);
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

/**
 * DELETE - Excluir registros (templates)
 */
function handleDelete($db, $action) {
    if ($action === 'templates') {
        $id = $_GET['id'] ?? ($_POST['id'] ?? null);
        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'id obrigatório']);
            return;
        }
        $manager = new RoteiroInspecao();
        if ($manager->delete((int)$id)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir template']);
        }
    } else {
        http_response_code(501);
        echo json_encode(['error' => 'Ação não suportada']);
    }
}

function finalizarVisitaComClasse($input) {
    if (empty($input['visita_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'visita_id obrigatório']);
        return;
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Autorização: fora da equipe/diretoria, só o associado dono da visita
        // (ou o técnico responsável) pode finalizá-la.
        if (!ApiAuth::ehStaff() && !ApiAuth::ehDiretoriaExecutiva()) {
            $meuAssociado = ApiAuth::associadoId();
            $meuUsuario  = (int) ($_SESSION['user_id'] ?? 0);
            $stVis = $db->prepare("SELECT associado_id, tecnico_responsavel_id FROM visitas_tecnicas WHERE id = ?");
            $stVis->execute([(int) $input['visita_id']]);
            $vis = $stVis->fetch(PDO::FETCH_ASSOC);
            if (!$vis || ((int) $vis['associado_id'] !== $meuAssociado && (int) $vis['tecnico_responsavel_id'] !== $meuUsuario)) {
                http_response_code(403);
                echo json_encode(['error' => 'Acesso negado: visita de outro produtor']);
                return;
            }
        }

        // Carregar checklist mesclado (roteiro + padrão) para regra de itens críticos
        require_once __DIR__ . '/../classes/ChecklistPadrao.php';
        $stmt = $db->prepare("SELECT r.checklist, r.itens_criticos, r.pontuacao_minima
                              FROM visitas_tecnicas v
                              JOIN roteiros_inspecao r ON v.roteiro_id = r.id
                              WHERE v.id = ?");
        $stmt->execute([$input['visita_id']]);
        $roteiro = $stmt->fetch(PDO::FETCH_ASSOC);

        $merge = ChecklistPadrao::mesclar(json_decode($roteiro['checklist'] ?: '[]', true));
        $checklistItens = [];
        foreach ($merge as $item) {
            $checklistItens[strval($item['id'])] = [
                'obrigatorio' => !empty($item['obrigatorio']) || in_array(strval($item['id']), (array)json_decode($roteiro['itens_criticos'] ?: '[]'), true),
                'categoria'   => $item['categoria'] ?? 'Geral'
            ];
        }

        $manager = new VisitaTecnica();
        $manager->saveResult(
            $input['visita_id'],
            $input['resultado'],
            $input['observacoes'] ?? null,
            [
                'produtor' => $input['assinatura_produtor_url'] ?? null,
                'tecnico' => $input['assinatura_tecnico_url'] ?? null
            ],
            $input['evidencias_fotos'] ?? [],
            [
                'hora_inicio'     => $input['hora_inicio']     ?? null,
                'hora_fim'        => $input['hora_fim']        ?? null,
                'condicoes_clima' => $input['condicoes_clima'] ?? null,
                'pontuacao_minima'=> isset($roteiro['pontuacao_minima']) ? (int)$roteiro['pontuacao_minima'] : 70,
                'checklist'       => $checklistItens
            ]
        );
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao finalizar visita', 'message' => $e->getMessage()]);
    }
}

