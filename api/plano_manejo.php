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
    case 'PATCH':
        handlePatchStatus($db);
        break;
    case 'DELETE':
        handleDelete($db);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}

function normalizeStatusValue($value) {
    if ($value === null) {
        return null;
    }

    $normalized = trim((string) $value);
    if ($normalized === '') {
        return null;
    }

    $map = [
        'em análise' => 'Em Análise',
        'em analise' => 'Em Análise',
        'em-analise' => 'Em Análise',
        'em_analise' => 'Em Análise',
        'emanalise' => 'Em Análise',
        'rascunho' => 'Rascunho',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'revisao necessaria' => 'Revisão Necessária',
        'revisão necessária' => 'Revisão Necessária',
        'revisao-necessaria' => 'Revisão Necessária',
        'revisao_necessaria' => 'Revisão Necessária',
    ];

    $key = mb_strtolower($normalized, 'UTF-8');
    $key = str_replace(['_', '-'], ' ', $key);
    $key = preg_replace('/\s+/', ' ', $key);
    $key = trim($key);

    return $map[$key] ?? $normalized;
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
    $isAdminGestor = isset($_SESSION['user_nivel']) && (int) $_SESSION['user_nivel'] <= NIVEL_GESTOR;
    $associadoId = null;

    if (!$isAdminGestor) {
        if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'usuarios') {
            $stmtAssoc = $db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
            $stmtAssoc->execute([$_SESSION['user_id'] ?? 0]);
            $associadoId = (int) ($stmtAssoc->fetchColumn() ?: 0);
        } else {
            $associadoId = (int) ($_SESSION['user_id'] ?? 0);
        }
    }

    $sql = "SELECT p.id, p.ano_safra, pr.propriedade as propriedade_nome, a.nome as associado_nome, a.sobrenome as associado_sobrenome,
                p.status, p.versao, p.created_at, 'Plano' as tipo_registro
            FROM plano_manejo p
            JOIN propriedades pr ON p.propriedade_id = pr.id
            JOIN associados a ON p.associado_id = a.id";

    if (!$isAdminGestor) {
        $sql .= " WHERE p.associado_id = :associado_id";
    }

    $sql .= " ORDER BY p.ano_safra DESC, p.created_at DESC";

    $stmt = $db->prepare($sql);
    if (!$isAdminGestor) {
        $stmt->bindValue(':associado_id', $associadoId, PDO::PARAM_INT);
    }
    $stmt->execute();
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
        $sql = "INSERT INTO plano_manejo (propriedade_id, associado_id, ano_safra, titulo, versao, status, observacoes, area_organica_ha, area_conversao_ha, descricao_solo, topografia, manejo_pragas, manejo_doencas, praticas_conservacao, rotacao_culturas, compostagem, biofertilizantes, fonte_agua, clima, adubacao_verde, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 'Rascunho', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
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
            $input['manejo_doencas'] ?? '',
            $input['praticas_conservacao'] ?? '',
            $input['rotacao_culturas'] ?? '',
            $input['compostagem'] ?? '',
            $input['biofertilizantes'] ?? '',
            $input['fonte_agua'] ?? '',
            $input['clima'] ?? '',
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
 * PUT - Atualizar plano completo
 */
function handlePut($db) {
    global $user;
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID do plano obrigatório']);
        return;
    }

    $podeEditarStatus = $user->hasPermission(NIVEL_GESTOR);

    try {
        $db->beginTransaction();

        // Busca status atual do banco para preservar se usuário não tiver permissão
        $stmtStatusAtual = $db->prepare("SELECT status FROM plano_manejo WHERE id = ?");
        $stmtStatusAtual->execute([$input['id']]);
        $statusNoBanco = $stmtStatusAtual->fetchColumn();

        if ($statusNoBanco === false) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Plano não encontrado']);
            return;
        }

        // Determina o status final a ser salvo
        if ($podeEditarStatus && array_key_exists('status', $input) && $input['status'] !== null && $input['status'] !== '') {
            $statusFinal = normalizeStatusValue($input['status']);
            if (empty($statusFinal)) {
                $statusFinal = 'Rascunho';
            }
        } else {
            // Mantém o status atual do banco para usuários sem permissão
            $statusFinal = normalizeStatusValue($statusNoBanco);
            if (empty($statusFinal)) {
                $statusFinal = 'Rascunho';
            }
        }

        // Campos a atualizar — status sempre incluído
        $setFields = ['status = ?'];
        $values    = [$statusFinal];

        $opcionais = [
            'titulo'              => $input['titulo']              ?? null,
            'ano_safra'           => $input['ano_safra']           ?? null,
            'observacoes'         => $input['observacoes']         ?? null,
            'propriedade_id'      => $input['propriedade_id']      ?? null,
            'associado_id'        => $input['associado_id']        ?? null,
            'area_organica_ha'    => $input['area_organica_ha']    ?? null,
            'area_conversao_ha'   => $input['area_conversao_ha']   ?? null,
            'descricao_solo'      => $input['descricao_solo']      ?? null,
            'topografia'          => $input['topografia']          ?? null,
            'manejo_pragas'       => $input['manejo_pragas']       ?? null,
            'manejo_doencas'      => $input['manejo_doencas']      ?? null,
            'praticas_conservacao'=> $input['praticas_conservacao']?? null,
            'rotacao_culturas'    => $input['rotacao_culturas']    ?? null,
            'compostagem'         => $input['compostagem']         ?? null,
            'biofertilizantes'    => $input['biofertilizantes']    ?? null,
            'fonte_agua'          => $input['fonte_agua']          ?? null,
            'clima'               => $input['clima']               ?? null,
            'adubacao_verde'      => $input['adubacao_verde']      ?? null,
            'motivo_reprovacao'   => $input['motivo_reprovacao']   ?? null,
            'data_aprovacao'      => $input['data_aprovacao']      ?? null,
            'aprovado_por'        => $input['aprovado_por']        ?? null,
        ];

        foreach ($opcionais as $col => $val) {
            if ($val !== null) {
                $setFields[] = "$col = ?";
                $values[]    = $val;
            }
        }

        $values[] = $input['id'];
        $sql = 'UPDATE plano_manejo SET ' . implode(', ', $setFields) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        // Atualiza cultivos se fornecidos
        if (isset($input['cultivos']) && is_array($input['cultivos'])) {
            $stmtDel = $db->prepare('DELETE FROM plano_manejo_cultivos WHERE plano_manejo_id = ?');
            $stmtDel->execute([$input['id']]);

            $sqlCultivo = 'INSERT INTO plano_manejo_cultivos (plano_manejo_id, cultura, area_cultivada_ha, tipo_semente, observacoes) VALUES (?, ?, ?, ?, ?)';
            $stmtC = $db->prepare($sqlCultivo);

            foreach ($input['cultivos'] as $cultivo) {
                if (empty($cultivo['cultura'])) continue;
                $stmtC->execute([
                    $input['id'],
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
            'message' => 'Plano atualizado com sucesso',
            'status'  => $statusFinal
        ]);

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar', 'details' => $e->getMessage()]);
    }
}

/**
 * PATCH - Atualizar somente o status do plano (endpoint dedicado)
 */
function handlePatchStatus($db) {
    global $user;

    if (!$user->hasPermission(NIVEL_GESTOR)) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão para alterar o status']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID e status são obrigatórios']);
        return;
    }

    $statusFinal = normalizeStatusValue($input['status']);
    if (empty($statusFinal)) {
        http_response_code(400);
        echo json_encode(['error' => 'Status inválido']);
        return;
    }

    try {
        $stmt = $db->prepare('UPDATE plano_manejo SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$statusFinal, $input['id']]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Plano não encontrado']);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Status atualizado para: ' . $statusFinal,
            'status'  => $statusFinal
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar status', 'details' => $e->getMessage()]);
    }
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

