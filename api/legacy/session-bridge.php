<?php
/**
 * PONTE DE SESSAO PHP -> JWT  (Fase 2)
 *
 * TEMPORARIA. Existe apenas para que, durante a coexistencia, um usuario ja
 * autenticado no app PHP consiga obter um token da API Node sem relogar.
 * DEVE SER REMOVIDA NA FASE 7 (desligamento do PHP).
 *
 * Seguranca:
 *  - exige sessao PHP ativa (config.php chama session_start());
 *  - o segredo e lido do .env do monorepo, nunca versionado aqui;
 *  - o token emitido e de curta duracao.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

function ler_env(string $caminho): array {
    $valores = [];
    if (!is_file($caminho)) {
        return $valores;
    }
    foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#') {
            continue;
        }
        $pos = strpos($linha, '=');
        if ($pos === false) {
            continue;
        }
        $chave = trim(substr($linha, 0, $pos));
        $valor = trim(substr($linha, $pos + 1));
        $valores[$chave] = trim($valor, "\"'");
    }
    return $valores;
}

function base64url(string $dados): string {
    return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
}

function gerar_jwt(array $payload, string $segredo): string {
    $header = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $corpo  = base64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
    $assinatura = hash_hmac('sha256', $header . '.' . $corpo, $segredo, true);
    return $header . '.' . $corpo . '.' . base64url($assinatura);
}

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

$env = ler_env(dirname(__DIR__, 2) . '/sinporg/.env');
$segredo = getenv('JWT_ACCESS_SECRET') ?: ($env['JWT_ACCESS_SECRET'] ?? '');
if ($segredo === '' || $segredo === 'dev-apenas-trocar') {
    // O valor de exemplo so e aceito em ambiente de desenvolvimento.
    if ($segredo === '') {
        responder(500, ['error' => 'JWT_ACCESS_SECRET nao configurado']);
    }
}
$ttl = (int) ($env['JWT_ACCESS_TTL_SECONDS'] ?? 900);

$db = Database::getInstance()->getConnection();

// --- Identidade: staff (usuarios) ou agricultor (associados) ---
if (!empty($_SESSION['associado_id']) && empty($_SESSION['user_id'])) {
    $id = (int) $_SESSION['associado_id'];
    $stmt = $db->prepare('SELECT id, nome, sobrenome, email, nivel, ativo, certificadora_id FROM associados WHERE id = ?');
    $stmt->execute([$id]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$a || (int) $a['ativo'] === 0) {
        responder(401, ['error' => 'Associado invalido ou inativo']);
    }
    $payload = [
        'sub'             => (int) $a['id'],
        'origem'          => 'associado',
        'nome'            => trim($a['nome'] . ' ' . $a['sobrenome']),
        'email'           => (string) ($a['email'] ?? ''),
        'nivel'           => (int) $a['nivel'],
        'tipo'            => 'associado',
        'certificadoraId' => $a['certificadora_id'] !== null ? (int) $a['certificadora_id'] : null,
        'associadoId'     => (int) $a['id'],
    ];
} elseif (!empty($_SESSION['user_id'])) {
    $id = (int) $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT id, nome, email, nivel, tipo, status, certificadora_id, associado_id FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || $u['status'] !== 'ativo') {
        responder(401, ['error' => 'Usuario invalido ou inativo']);
    }
    $payload = [
        'sub'             => (int) $u['id'],
        'origem'          => 'staff',
        'nome'            => (string) $u['nome'],
        'email'           => (string) $u['email'],
        'nivel'           => (int) $u['nivel'],
        'tipo'            => (string) $u['tipo'],
        'certificadoraId' => $u['certificadora_id'] !== null ? (int) $u['certificadora_id'] : null,
        'associadoId'     => $u['associado_id'] !== null ? (int) $u['associado_id'] : null,
    ];
} else {
    responder(401, ['error' => 'Sem sessao ativa']);
}

$agora = time();
$payload['iat'] = $agora;
$payload['exp'] = $agora + $ttl;

responder(200, [
    'accessToken' => gerar_jwt($payload, $segredo),
    'expiresIn'   => $ttl,
    'origem'      => $payload['origem'],
]);
