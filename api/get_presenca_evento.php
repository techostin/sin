<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erro' => 'Método inválido']);
    exit;
}

$evento_id = $_GET['evento_id'] ?? null;
if (!$evento_id || !is_numeric($evento_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erro' => 'ID do evento inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT id, nome, data_evento, local, status, confirmados FROM eventos WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        http_response_code(404);
        echo json_encode(['success' => false, 'erro' => 'Evento não encontrado']);
        exit;
    }

    $confirmados = json_decode($evento['confirmados'] ?? '[]', true);
    if (!is_array($confirmados)) $confirmados = [];

    $confirmados_associados_ids = [];
    $confirmados_usuarios_ids = [];
    foreach ($confirmados as $conf) {
        if ($conf['table'] === 'associados') {
            $confirmados_associados_ids[] = $conf['id'];
        } else {
            $confirmados_usuarios_ids[] = $conf['id'];
        }
    }

    $nomes_confirmados = [];
    if (count($confirmados_associados_ids) > 0) {
        $placeholders = str_repeat('?,', count($confirmados_associados_ids) - 1) . '?';
        $stmt_nomes = $db->prepare("SELECT id, nome, sobrenome, tipo FROM associados WHERE id IN ($placeholders)");
        $stmt_nomes->execute($confirmados_associados_ids);
        foreach ($stmt_nomes->fetchAll(PDO::FETCH_ASSOC) as $assoc) {
            $nomes_confirmados['associados'][$assoc['id']] = [
                'nome' => $assoc['nome'] . ' ' . $assoc['sobrenome'],
                'tipo' => $assoc['tipo']
            ];
        }
    }
    if (count($confirmados_usuarios_ids) > 0) {
        $placeholders = str_repeat('?,', count($confirmados_usuarios_ids) - 1) . '?';
        $stmt_nomes = $db->prepare("SELECT id, nome, tipo, associado_id FROM usuarios WHERE id IN ($placeholders)");
        $stmt_nomes->execute($confirmados_usuarios_ids);
        foreach ($stmt_nomes->fetchAll(PDO::FETCH_ASSOC) as $usr) {
            $nomes_confirmados['usuarios'][$usr['id']] = [
                'nome' => $usr['nome'],
                'tipo' => ucfirst($usr['tipo']),
                'associado_id' => $usr['associado_id']
            ];
        }
    }

    $nao_confirmados_associados = [];
    $stmt = $db->prepare("SELECT id, nome, sobrenome, tipo FROM associados WHERE status = 'ativo' ORDER BY nome");
    $stmt->execute();
    $todos_associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($todos_associados as $assoc) {
        if (!in_array($assoc['id'], $confirmados_associados_ids)) {
            $nao_confirmados_associados[] = $assoc;
        }
    }

    $cpfs_cadastrados_como_associado = [];
    $stmt = $db->prepare("SELECT cpf FROM associados WHERE cpf IS NOT NULL AND cpf != ''");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cpfs_cadastrados_como_associado[] = $row['cpf'];
    }

    $nao_confirmados_usuarios_puros = [];
    $nao_confirmados_gestores = [];
    $stmt = $db->prepare("SELECT id, nome, tipo, associado_id FROM usuarios WHERE status = 'ativo' ORDER BY nome");
    $stmt->execute();
    $todos_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($todos_usuarios as $usr) {
        if (in_array($usr['id'], $confirmados_usuarios_ids)) {
            continue;
        }
        if ($usr['tipo'] === 'gestor') {
            $nao_confirmados_gestores[] = $usr;
        } elseif (empty($usr['associado_id'])) {
            $ja_e_associado = false;
            if (!empty($cpfs_cadastrados_como_associado)) {
                $stmt_cpf = $db->prepare("SELECT cpf FROM associados WHERE user_id = ?");
                $stmt_cpf->execute([$usr['id']]);
                $result = $stmt_cpf->fetch(PDO::FETCH_ASSOC);
                if ($result && in_array($result['cpf'], $cpfs_cadastrados_como_associado)) {
                    $ja_e_associado = true;
                }
            }
            if (!$ja_e_associado) {
                $nao_confirmados_usuarios_puros[] = $usr;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'evento' => [
            'id' => $evento['id'],
            'nome' => $evento['nome'],
            'data_evento' => date('d/m/Y H:i', strtotime($evento['data_evento'])),
            'local' => $evento['local'],
            'status' => $evento['status']
        ],
        'confirmados' => $confirmados,
        'nomes_confirmados' => $nomes_confirmados,
        'nao_confirmados_associados' => $nao_confirmados_associados,
        'nao_confirmados_usuarios' => $nao_confirmados_usuarios_puros,
        'nao_confirmados_gestores' => $nao_confirmados_gestores
    ]);

} catch (Exception $e) {
    error_log("Erro ao buscar presenças: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'erro' => 'Erro ao buscar dados']);
}