<?php
/*
 * Arquivo de configuração do banco de dados
 * Defina as constantes para conexão com o MySQL.
 */

// B-03: exibição de erros controlada por APP_DEBUG (produção: apenas log).
require_once __DIR__ . '/error_config.php';

// WAF de aplicação (item 5 do P0): roda antes de sessão e das regras.
require_once __DIR__ . '/request_guard.php';

// ---------------------------------------------------------------------------
// SEGREDOS: config/secrets.php (não versionado) e variáveis de ambiente.
// Precedência: ambiente > secrets.php > padrão seguro. Nunca usar root.
// ---------------------------------------------------------------------------
$envDbHost = getenv('DB_HOST');
if ($envDbHost !== false && $envDbHost !== '') {
    define('DB_HOST', $envDbHost);
}
$envDbName = getenv('DB_NAME');
if ($envDbName !== false && $envDbName !== '') {
    define('DB_NAME', $envDbName);
}
$envDbUser = getenv('DB_USER');
if ($envDbUser !== false && $envDbUser !== '') {
    define('DB_USER', $envDbUser);
}
$envDbPass = getenv('DB_PASS');
if ($envDbPass !== false && $envDbPass !== '') {
    define('DB_PASS', $envDbPass);
}
$envResend = getenv('RESEND_API_KEY');
if ($envResend !== false && $envResend !== '') {
    define('RESEND_API_KEY', $envResend);
}
$envIdKey = getenv('ID_ENCODE_KEY');
if ($envIdKey !== false && $envIdKey !== '') {
    define('ID_ENCODE_KEY', $envIdKey);
}
if (is_file(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

// Configurações do Banco de Dados (fallback seguro; nunca root)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'sinporg_app');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'sisagro');

// Configurações do Sistema
 // URL base do seu site
#define('SITE_URL', 'https://sinporg.com.br/app');//
define('SITE_URL', 'http://localhost/app'); // URL base do seu site
define('SITE_TITLE', 'SINPORG');             // Título do seu site

// Configurações de Nível de Acesso
define('NIVEL_ADMIN', 1);
define('NIVEL_GESTOR', 2);
define('NIVEL_USUARIO', 3);

// Caminho absoluto da logo SINPORG usada nos relatórios (PDF/mPDF)
define('SINPORG_LOGO_PATH', __DIR__ . '/../img/logo_Sinporg-2.png');
define('SINPORG_LOGO_URL', SITE_URL . '/img/logo_Sinporg-2.png');

// E-mail transacional (Resend)
if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');
}
define('EMAIL_FROM_ADDRESS', 'sinporg@sinporg.organicosrs.com.br'); // Remetente (domínio verificado: sinporg.organicosrs.com.br)
define('EMAIL_FROM_NAME', 'SINPORG - Sistema de Gestão da Produção Orgânica');

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');

// Chave secreta para codificação de IDs em URLs.
// ATENCAO: trocar esta chave invalida todos os links ja emitidos.
if (!defined('ID_ENCODE_KEY')) {
    define('ID_ENCODE_KEY', getenv('ID_ENCODE_KEY') ?: 'troque-esta-chave-em-producao');
}

/**
 * Codifica um ID numérico em string segura para URL
 */
function encodeId($id) {
    $data = (string) (int) $id;
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', ID_ENCODE_KEY, 0, $iv);
    return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
}

/**
 * Decodifica string de URL de volta para ID numérico
 */
function decodeId($encoded) {
    $encoded = strtr($encoded, '-_', '+/');
    $decoded = base64_decode(str_pad($encoded, strlen($encoded) % 4, '=', STR_PAD_RIGHT));
    if ($decoded === false || strlen($decoded) < 16) {
        return 0;
    }
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', ID_ENCODE_KEY, 0, $iv);
    if ($decrypted === false || !ctype_digit($decrypted)) {
        return 0;
    }
    return (int) $decrypted;
}

// ---------------------------------------------------------------------------
// SESSÃO: cookies HttpOnly/SameSite + modo estrito (anti fixação de sessão).
// Secure é ativado automaticamente quando a requisição é HTTPS, para não
// quebrar o ambiente local (http://localhost).
// ---------------------------------------------------------------------------
if (!defined('SINPORG_SKIP_SESSION') && session_status() == PHP_SESSION_NONE) {
    if (!headers_sent()) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}
?>
