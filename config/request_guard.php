<?php
/**
 * WAF de aplicação (item 5 do P0, 2026-10-27).
 *
 * Executa no início do bootstrap, antes de sessão e de qualquer regra de
 * negócio. Complementa o WAF de borda (Cloudflare/AWS/Fastly) e o .htaccess:
 * bloqueia métodos de diagnóstico, travessia de caminho, scanners conhecidos,
 * corpo excessivo e cookie de sessão malformado (vetor do CVE-2026-109619).
 *
 * Não registra corpo, query nem dados pessoais: apenas IP, método, caminho,
 * motivo e user-agent truncado. Log em logs/security.log (negado pela web).
 */
if (PHP_SAPI === 'cli') {
    return;
}

if (!function_exists('sinporg_waf_log')) {
    function sinporg_waf_log(string $motivo): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
        $metodo = $_SERVER['REQUEST_METHOD'] ?? '-';
        $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 200);
        $ua = str_replace(["\r", "\n"], ' ', $ua);
        $linha = sprintf(
            "[%s] ip=%s metodo=%s caminho=%s motivo=%s ua=%s\n",
            date('c'),
            $ip,
            $metodo,
            $caminho,
            $motivo,
            $ua
        );
        @file_put_contents(__DIR__ . '/../logs/security.log', $linha, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('sinporg_waf_negar')) {
    function sinporg_waf_negar(int $status, string $motivo): void
    {
        sinporg_waf_log($motivo);
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $status === 413 ? 'Requisicao muito grande.' : 'Requisicao bloqueada.';
        exit;
    }
}

$sinporgMetodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($sinporgMetodo, ['TRACE', 'TRACK'], true)) {
    sinporg_waf_negar(403, 'metodo_trace');
}

$sinporgUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($sinporgUri, "\0") !== false || preg_match('/(\.\.\/|%2e%2e|%00)/i', $sinporgUri)) {
    sinporg_waf_negar(403, 'travessia');
}

$sinporgUa = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if (preg_match('/(sqlmap|nikto|nmap|masscan|zgrab|acunetix|nessus|dirbuster|gobuster|wpscan)/i', $sinporgUa)) {
    sinporg_waf_negar(403, 'scanner_ua');
}

$sinporgTamanho = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($sinporgTamanho > 33554432) {
    sinporg_waf_negar(413, 'corpo_grande');
}

$sinporgSessao = session_name();
if (isset($_COOKIE[$sinporgSessao])) {
    $sinporgSid = (string) $_COOKIE[$sinporgSessao];
    if (!preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sinporgSid)) {
        unset($_COOKIE[$sinporgSessao]);
        sinporg_waf_log('cookie_sessao_invalido');
    }
}

if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

unset($sinporgMetodo, $sinporgUri, $sinporgUa, $sinporgTamanho, $sinporgSessao, $sinporgSid);
