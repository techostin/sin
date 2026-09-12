<?php
/*
 * Arquivo de configuração do banco de dados
 * Defina as constantes para conexão com o MySQL.
 */

// B-03: exibição de erros controlada por APP_DEBUG (produção: apenas log).
require_once __DIR__ . '/error_config.php';

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');       // Host do banco de dados
define('DB_USER', 'root');            // Usuário do banco de dados
define('DB_PASS', '');               // Senha do banco de dados
define('DB_NAME', 'sisagro');          // Nome do banco de dados

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

// ---------------------------------------------------------------------------
// SEGREDOS: carregados de config/secrets.php (NAO versionado) ou do ambiente.
// Ver config/secrets.example.php. Nunca colocar chaves reais neste arquivo.
// ---------------------------------------------------------------------------
if (is_file(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

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

// Iniciar a sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
