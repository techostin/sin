<?php
/**
 * API de Ocorrências
 * 
 * GET    - Listar ocorrências (com filtros)
 * PATCH  - Atualizar status / correcoes_realizadas
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Ocorrencia.php';

header('Content-Type: application/json; charset=utf-8');

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$ocorrencia = new Ocorrencia();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Gestor/Admin vê todas as ocorrências
            if ($user->hasPermission(NIVEL_GESTOR)) {
                $data = $ocorrencia->getAll();
            } else {
                $associado_id = $user->getAssociadoId();
                if (!$associado_id) $associado_id = -1;
                $data = $ocorrencia->getAllByAssociadoId($associado_id);
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'PATCH':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID não informado']);
                exit;
            }

            $id = (int)$input['id'];
            $oc = $ocorrencia->getById($id);

            if (!$oc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Ocorrência não encontrada']);
                exit;
            }

            // Verificar permissão: gestor pode tudo; usuario só suas próprias
            if (!$user->hasPermission(NIVEL_GESTOR)) {
                $assoc_id = $user->getAssociadoId();
                if (!$assoc_id || (int)$oc['associado_id'] !== $assoc_id) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
                    exit;
                }
            }

            $db = Database::getInstance()->getConnection();
            $updates = [];
            $params = [':id' => $id];

            // Gestor pode alterar status
            if ($user->hasPermission(NIVEL_GESTOR) && isset($input['status'])) {
                $status = $input['status'];
                $allowed = ['Aberta', 'Em Correção', 'Resolvida', 'Fechada'];
                if (in_array($status, $allowed)) {
                    $updates[] = "status = :status";
                    $params[':status'] = $status;
                }
            }

            // Usuário comum pode informar correcoes_realizadas
            if (isset($input['correcoes_realizadas'])) {
                $updates[] = "correcoes_realizadas = :correcoes";
                $params[':correcoes'] = $input['correcoes_realizadas'];
                // Ao adicionar correção, marcar como "Em Correção" se estiver "Aberta"
                if ($oc['status'] === 'Aberta') {
                    $updates[] = "status = 'Em Correção'";
                }
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nenhum campo para atualizar']);
                exit;
            }

            $sql = "UPDATE ocorrencias_manejo SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($params);

            echo json_encode(['success' => $result, 'message' => $result ? 'Atualizado com sucesso' : 'Erro ao atualizar']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

