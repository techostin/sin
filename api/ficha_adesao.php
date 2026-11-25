<?php
/**
 * API da Ficha de Adesão ao Quadro Associativo.
 *
 * GET  ?action=status            -> status da ficha do usuário logado (para o modal)
 * POST ?action=salvar            -> salva rascunho (corpo: form-encoded)
 * POST ?action=submeter          -> envia a ficha para análise da Diretoria
 * POST ?action=aprovar           -> Diretoria/Admin aprova a ficha (ficha_id, motivo opcional)
 * POST ?action=recusar           -> Diretoria/Admin recusa a ficha (ficha_id, motivo)
 *
 * Todos os POSTs exigem token CSRF (campo csrf_token ou header X-CSRF-Token).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/FichaAdesao.php';
require_once __DIR__ . '/../classes/CSRFProtection.php';

header('Content-Type: application/json; charset=utf-8');

function responder($payload, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Identifica QUEM está preenchendo a ficha: a conta de sistema (usuarios) do
 * pedido de acesso, o login direto na tabela associados ou a sessão do Portal.
 *
 * @return array ['usuario_id' => int, 'associado_id' => int, 'origem' => string]
 */
function contaDaSessao(): array
{
    $conta = ['usuario_id' => 0, 'associado_id' => 0, 'origem' => ''];

    // 1) Pedido de acesso / conta de sistema
    if (!empty($_SESSION['user_id']) && ($_SESSION['user_table'] ?? '') === 'usuarios') {
        $conta['usuario_id'] = (int)$_SESSION['user_id'];
        $conta['origem'] = 'usuarios';
        // Se a conta já tem cadastro de associado vinculado, usa o mesmo registro
        try {
            require_once __DIR__ . '/../classes/FichaAdesao.php';
            $conta['associado_id'] = (new FichaAdesao())->associadoIdDoUsuario($conta['usuario_id']);
        } catch (Throwable $e) {
            $conta['associado_id'] = 0;
        }
        return $conta;
    }

    // 2) Login direto na tabela associados (nível usuário)
    if (!empty($_SESSION['user_id']) && ($_SESSION['user_table'] ?? '') === 'associados'
        && (int)($_SESSION['user_nivel'] ?? 0) === NIVEL_USUARIO) {
        $conta['associado_id'] = (int)$_SESSION['user_id'];
        $conta['origem'] = 'associados';
        return $conta;
    }

    // 3) Portal do Associado
    if (!empty($_SESSION['associado_id'])) {
        $conta['associado_id'] = (int)$_SESSION['associado_id'];
        $conta['origem'] = 'portal';
        return $conta;
    }

    return $conta;
}

/** Compatibilidade: ID do associado (0 quando a sessão é só de conta). */
function associadoDaSessao(): int
{
    $c = contaDaSessao();
    return (int)$c['associado_id'];
}

if ($action === 'status') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $conta = contaDaSessao();
    if ($conta['usuario_id'] <= 0 && $conta['associado_id'] <= 0) {
        responder(['success' => true, 'mostrar' => false, 'status' => 'aprovada']);
    }

    $model = new FichaAdesao();
    // Ficha do associado (quando já existe cadastro) ou da CONTA (pedido de acesso)
    $status = $conta['associado_id'] > 0
        ? $model->statusDoAssociado($conta['associado_id'])
        : $model->statusDoUsuario($conta['usuario_id']);

    // Dados para pré-preenchimento: cadastro do associado quando existir,
    // senão os da própria conta (pedido de acesso).
    $usuario = ['nome' => '', 'email' => '', 'telefone' => '', 'cpf' => ''];
    try {
        $db = Database::getInstance()->getConnection();
        $row = null;
        if ($conta['associado_id'] > 0) {
            $stmt = $db->prepare("SELECT nome, sobrenome, email, telefone, cpf FROM associados WHERE id = ? LIMIT 1");
            $stmt->execute([$conta['associado_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$row && $conta['usuario_id'] > 0) {
            $stmt = $db->prepare("SELECT nome, sobrenome, email, telefone, cpf FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$conta['usuario_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($row) {
            $usuario = [
                'nome'     => trim(($row['nome'] ?? '') . ' ' . ($row['sobrenome'] ?? '')),
                'email'    => $row['email'] ?? '',
                'telefone' => $row['telefone'] ?? '',
                'cpf'      => $row['cpf'] ?? '',
            ];
        }
    } catch (PDOException $e) {
        // sem dados de prefill
    }

    responder([
        'success'  => true,
        'mostrar'  => $status['status'] !== FichaAdesao::STATUS_APROVADA,
        'status'   => $status['status'],
        'ficha'    => $status['ficha'],
        'produtos' => $status['produtos'],
        'usuario'  => $usuario,
    ]);
}

// ── POSTs: exigem CSRF ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['success' => false, 'message' => 'Método não permitido.'], 405);
}

if (!CSRFProtection::validateToken(CSRFProtection::tokenFromRequest())) {
    responder(['success' => false, 'message' => 'Erro de segurança: token CSRF inválido.'], 403);
}

if ($action === 'salvar' || $action === 'submeter') {
    $conta = contaDaSessao();
    if ($conta['usuario_id'] <= 0 && $conta['associado_id'] <= 0) {
        responder(['success' => false, 'message' => 'Sessão inválida. Faça login novamente.'], 401);
    }
    $model = new FichaAdesao();

    // Ficha atual (pelo cadastro quando existir; senão pela conta)
    $fichaAtual = $conta['associado_id'] > 0
        ? $model->getByAssociadoId($conta['associado_id'])
        : $model->getByUsuarioId($conta['usuario_id']);
    $statusAnterior = $fichaAtual ? $fichaAtual['status'] : null;

    // Pedido de acesso (sem cadastro de associado) grava a ficha na CONTA
    $res = $conta['usuario_id'] > 0
        ? $model->salvarPorUsuario($conta['usuario_id'], $_POST, $action === 'submeter')
        : $model->salvar($conta['associado_id'], $_POST, $action === 'submeter');

    // Se foi submetida com sucesso (nova ou reenviada após recusa), notificar
    if ($res['success'] && $action === 'submeter' && $res['ficha_id']) {
        if (in_array($statusAnterior, [FichaAdesao::STATUS_RASCUNHO, FichaAdesao::STATUS_RECUSADA], true) || $statusAnterior === null) {
            $db = Database::getInstance()->getConnection();
            $nomeAssociado = '';
            if ($conta['associado_id'] > 0) {
                $stmt = $db->prepare("SELECT nome, sobrenome FROM associados WHERE id = ? LIMIT 1");
                $stmt->execute([$conta['associado_id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $nomeAssociado = $row ? trim($row['nome'] . ' ' . $row['sobrenome']) : '';
            }
            if ($nomeAssociado === '' && $conta['usuario_id'] > 0) {
                $stmt = $db->prepare("SELECT nome, sobrenome FROM usuarios WHERE id = ? LIMIT 1");
                $stmt->execute([$conta['usuario_id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $nomeAssociado = $row ? trim($row['nome'] . ' ' . $row['sobrenome']) : '';
            }
            if ($nomeAssociado === '') {
                $nomeAssociado = 'Solicitante #' . (int)($conta['usuario_id'] ?: $conta['associado_id']);
            }

            $model->notificarNovaFichaPendente(
                $res['ficha_id'],
                (int)$conta['associado_id'],
                $nomeAssociado,
                (int)$conta['usuario_id']
            );
        }
    }

    responder($res, $res['success'] ? 200 : 422);
}

if ($action === 'aprovar' || $action === 'recusar') {
    $user = new User();
    if (!$user->podeAcessarDiretoria()) {
        responder(['success' => false, 'message' => 'Acesso negado. Apenas a Diretoria/Presidência pode avaliar fichas.'], 403);
    }

    $fichaId = (int)($_POST['ficha_id'] ?? 0);
    if ($fichaId <= 0) {
        responder(['success' => false, 'message' => 'Ficha não informada.'], 422);
    }

    $analisadoPor = (int)($_SESSION['user_id'] ?? $_SESSION['associado_id'] ?? 0);

    $model = new FichaAdesao();
    $res = $action === 'aprovar'
        ? $model->aprovar($fichaId, $analisadoPor)
        : $model->recusar($fichaId, $analisadoPor, (string)($_POST['motivo'] ?? ''));
    responder($res, $res['success'] ? 200 : 422);
}

responder(['success' => false, 'message' => 'Ação desconhecida.'], 400);
