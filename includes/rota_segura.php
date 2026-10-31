<?php
/**
 * B-02 (Path Injection / LFI) - Resolucao segura de arquivos em rotas dinamicas.
 *
 * O roteador amigavel do index.php montava o caminho do arquivo a incluir
 * concatenando um pedaco da URL:
 *
 *     $arquivo = __DIR__ . '/api/relatorios/' . $parts[2] . '.php';
 *     if (file_exists($arquivo)) { require $arquivo; }
 *
 * Como $parts[2] vinha do usuario sem validacao, uma URL como
 * "/api/relatorios/..%2f..%2fconfig%2fconfig" saia do diretorio pretendido e
 * incluia (executando) qualquer .php do servidor - Local File Inclusion com
 * execucao remota.
 *
 * A defesa aplicada aqui tem tres camadas:
 *   1. Formato   - o slug precisa ser um identificador simples (sem "/", "\\",
 *                  "..", ".", byte nulo ou caractere codificado).
 *   2. Whitelist - o slug precisa estar na lista explicita de arquivos que a
 *                  rota pode servir.
 *   3. Confinamento - o caminho real (realpath) precisa continuar dentro do
 *                  diretorio base, mesmo que 1 e 2 sejam burlados no futuro.
 */

/**
 * Relatorios expostos pela rota /api/relatorios/<slug>.
 * Arquivos de apoio (bootstrap.php, _config.php) e temporarios ficam de fora
 * de proposito: eles sao includes internos, nao endpoints.
 */
if (!defined('ROTAS_API_RELATORIOS_PERMITIDAS')) {
    define('ROTAS_API_RELATORIOS_PERMITIDAS', [
        'admin_export',
        'atividades-diarias',
        'contas-pagar',
        'contas-receber',
        'estoque-atual',
        'fluxo-caixa',
        'fluxo-caixa-realizado-previsto',
        'gerar',
        'inadimplencia',
        'lista-associados',
        'movimentacoes',
        'pagamentos-por-forma',
        'previsao-receita',
        'produtividade',
        'rapido',
        'relatorio_associados',
        'relatorio_atividades_usuario',
        'status-associados',
        'visao-geral',
    ]);
}

/**
 * Provedores PIX aceitos pela rota /api/pix/webhook/<provedor>.
 */
if (!defined('ROTAS_PIX_WEBHOOK_PERMITIDAS')) {
    define('ROTAS_PIX_WEBHOOK_PERMITIDAS', [
        'asaas',
        'gerencianet',
        'mercadopago',
    ]);
}

if (!function_exists('rota_arquivo_seguro')) {
    /**
     * Resolve o caminho absoluto de um arquivo .php de rota dinamica.
     *
     * @param string $baseDir    Diretorio base permitido (caminho absoluto).
     * @param mixed  $slug       Pedaco da URL informado pelo usuario.
     * @param array  $permitidos Whitelist de slugs aceitos (obrigatoria).
     * @return string|null Caminho absoluto validado, ou null se nao for permitido.
     */
    function rota_arquivo_seguro($baseDir, $slug, array $permitidos)
    {
        // Camada 1 - formato do slug.
        if (!is_string($slug) || $slug === '' || strlen($slug) > 64) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $slug)) {
            return null;
        }

        // Camada 2 - whitelist explicita.
        if ($permitidos === [] || !in_array($slug, $permitidos, true)) {
            return null;
        }

        // Camada 3 - confinamento no diretorio base.
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return null;
        }
        $prefixo = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $alvoReal = realpath($prefixo . $slug . '.php');
        if ($alvoReal === false || !is_file($alvoReal)) {
            return null;
        }
        if (strncmp($alvoReal, $prefixo, strlen($prefixo)) !== 0) {
            return null;
        }

        return $alvoReal;
    }
}

if (!function_exists('rota_responder_404')) {
    /**
     * Resposta padrao (JSON) para rota dinamica nao permitida/inexistente.
     * Nao revela se o arquivo existe: nega por formato, whitelist ou ausencia
     * sempre com a mesma mensagem.
     */
    function rota_responder_404($mensagem = 'Recurso nao encontrado')
    {
        if (!headers_sent()) {
            header('HTTP/1.0 404 Not Found');
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    }
}
