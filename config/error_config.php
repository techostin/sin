<?php
/**
 * B-03 - Configuracao central de exibicao de erros.
 *
 * Espalhados pelo sistema havia ~25 arquivos com:
 *
 *     ini_set('display_errors', 1);
 *     ini_set('display_startup_errors', 1);
 *     error_reporting(E_ALL);
 *
 * Em producao isso expoe ao visitante caminhos absolutos do servidor, trechos
 * de SQL, nomes de colunas e credenciais que aparecem em stack traces - e ainda
 * corrompe respostas JSON e downloads (PDF/Excel), porque o warning e impresso
 * antes do conteudo. Agora existe um unico ponto de controle: a flag APP_DEBUG.
 *
 *   APP_DEBUG = false (PADRAO, producao) -> nada e exibido; tudo vai para o log.
 *   APP_DEBUG = true  (desenvolvimento)  -> erros exibidos na tela.
 *
 * Como ligar o debug em desenvolvimento (escolha UMA opcao):
 *
 *   1) Variavel de ambiente (recomendado). No Apache/.htaccess:
 *          SetEnv APP_DEBUG 1
 *
 *   2) Arquivo local nao versionado config/local.php:
 *          <?php define('APP_DEBUG', true);
 *
 * Este arquivo e idempotente: pode ser incluido varias vezes com seguranca.
 */

if (!defined('APP_DEBUG')) {
    // 1) Override local, fora do versionamento (opcional).
    if (is_file(__DIR__ . '/local.php')) {
        require_once __DIR__ . '/local.php';
    }
}

if (!defined('APP_DEBUG')) {
    // 2) Variavel de ambiente APP_DEBUG.
    $appDebugEnv = getenv('APP_DEBUG');
    if ($appDebugEnv === false && isset($_SERVER['APP_DEBUG'])) {
        $appDebugEnv = $_SERVER['APP_DEBUG'];
    }

    // 3) Padrao seguro: producao (debug desligado).
    define(
        'APP_DEBUG',
        is_string($appDebugEnv)
            && in_array(strtolower(trim($appDebugEnv)), ['1', 'true', 'on', 'yes'], true)
    );
}

// Erros nunca sao exibidos fora do modo debug; sempre vao para o log do PHP.
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
