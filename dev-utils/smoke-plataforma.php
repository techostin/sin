<?php

declare(strict_types=1);

/**
 * Smoke test ponta a ponta da plataforma de acessos (assoc / social / producao).
 *
 * Executa via CLI (bloqueado na web pelo .htaccess, pois dev-utils/ é negado):
 *   php dev-utils/smoke-plataforma.php
 *
 * O que valida:
 *  - login próprio por sistema, com CSRF e cookie restrito ao caminho;
 *  - sessões realmente isoladas (entrar/sair não afeta os outros sistemas);
 *  - admin com acesso total aos três sistemas e a todas as funções;
 *  - rejeições (CSRF inválido, senha errada) e bloqueio de /src/.
 *
 * Usa a conta admin.teste@sinporg.local com senha TEMPORÁRIA e a restaura no
 * finally. Nunca execute contra produção.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é CLI-only.');
}

define('SINPORG_SKIP_SESSION', true);
require dirname(__DIR__) . '/config/config.php';

if (!defined('DB_NAME') || DB_NAME !== 'sisagro') {
    exit("Banco inesperado (" . (defined('DB_NAME') ? DB_NAME : '?') . "). Abortando por segurança.\n");
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$email = 'admin.teste@sinporg.local';
$senha = 'TesteForte#2026';
$stmt = $pdo->prepare('SELECT id, senha FROM usuarios WHERE email = :e LIMIT 1');
$stmt->execute(['e' => $email]);
$conta = $stmt->fetch();
if (!$conta) {
    exit("conta de teste ausente: {$email}\n");
}
$id = (int) $conta['id'];
$hashOriginal = (string) $conta['senha'];

$jars = [];
$falhas = 0;
$total = 0;

function check(string $nome, bool $ok, string $extra = ''): void
{
    global $falhas, $total;
    $total++;
    if (!$ok) {
        $falhas++;
    }
    echo ($ok ? 'PASS ' : 'FALHA') . '  ' . $nome . ($extra !== '' ? '  [' . $extra . ']' : '') . PHP_EOL;
}

function http(string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'SINPORG-smoke/1.0',
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $resp = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['code' => $code, 'headers' => substr($resp, 0, $hs), 'body' => substr($resp, $hs)];
}

function csrfDe(string $html): string
{
    return preg_match('~name="csrf" value="([a-f0-9]{64})"~', $html, $m) === 1 ? $m[1] : '';
}

function localizacao(array $r): string
{
    return preg_match('~^Location:\s*(\S+)~mi', $r['headers'], $m) === 1 ? $m[1] : '';
}

$base = 'http://localhost/app';

try {
    $pdo->prepare('UPDATE usuarios SET senha = :h WHERE id = :id')
        ->execute(['h' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $id]);

    // 1. Formulário, CSRF e cookie isolado -----------------------------------
    $jar = dirname(__DIR__) . '/_smoke_assoc.txt';
    $jars[] = $jar;
    @unlink($jar);

    $r = http($base . '/assoc/login.php', $jar);
    check('GET /assoc/login.php responde 200', $r['code'] === 200, 'code=' . $r['code']);
    check('login traz campo CSRF', csrfDe($r['body']) !== '');
    check('cookie da sessão é SINPORG_ASSOC', str_contains($r['headers'], 'SINPORG_ASSOC='));
    check('cookie restrito a /app/assoc/', preg_match('~path=/app/assoc/~i', $r['headers']) === 1);
    check('cookie HttpOnly + SameSite=Lax', stripos($r['headers'], 'HttpOnly') !== false && stripos($r['headers'], 'SameSite=Lax') !== false);

    // 2. Rejeições ------------------------------------------------------------
    $r = http($base . '/assoc/login.php', $jar, ['csrf' => str_repeat('a', 64), 'identificador' => $email, 'senha' => $senha]);
    check('CSRF inválido é rejeitado', str_contains($r['body'], 'Sessão expirada'));

    $html = http($base . '/assoc/login.php', $jar)['body'];
    $r = http($base . '/assoc/login.php', $jar, ['csrf' => csrfDe($html), 'identificador' => $email, 'senha' => 'senha-errada']);
    check('senha errada é rejeitada', str_contains($r['body'], 'inválidos'));

    // 3. Login admin no /assoc/ ----------------------------------------------
    $html = http($base . '/assoc/login.php', $jar)['body'];
    $r = http($base . '/assoc/login.php', $jar, ['csrf' => csrfDe($html), 'identificador' => $email, 'senha' => $senha]);
    check('login válido redireciona (303)', $r['code'] === 303, 'code=' . $r['code']);
    check('redireciona para /app/assoc/index.php', str_contains(localizacao($r), '/app/assoc/index.php'), localizacao($r));

    $r = http($base . '/assoc/index.php', $jar);
    check('painel /assoc/ abre autenticado', $r['code'] === 200, 'code=' . $r['code']);
    check('topo direito tem Meu perfil e Configurações',
        str_contains($r['body'], 'meu-perfil.php') && str_contains($r['body'], 'configuracoes.php'));
    check('painel exibe a identidade visual do administrador',
        str_contains($r['body'], 'Painel do Administrador') && str_contains($r['body'], 'from-indigo-800'));
    check('admin vê selo de acesso admin', str_contains($r['body'], 'Acesso admin'));
    check('admin vê o quadro de associados no menu', str_contains($r['body'], 'Quadro de associados'));
    check('painel exibe cards de indicadores',
        str_contains($r['body'], 'Associados ativos') && str_contains($r['body'], 'Adesões pendentes'));
    check('painel de associação tem seções de acompanhamento', str_contains($r['body'], 'Próximos vencimentos'));
    check('página tem fundo lilás em degradê', str_contains($r['body'], 'fundo-lilas-pagina'));
    check('admin vê os 3 sistemas', str_contains($r['body'], 'Controle Social') && str_contains($r['body'], 'Produção do Agricultor'));
    check('admin vê todas as funções do sistema (17 de 17)', str_contains($r['body'], '17 de 17'));
    check('painel tem menu lateral', str_contains($r['body'], 'id="menu-lateral"'));
    check('menu lateral usa a cor #E8E8E8', str_contains($r['body'], 'menu-lateral-fundo'));
    check('barra superior usa a cor #E8E8E8', str_contains($r['body'], 'barra-superior-fundo'));
    check('menu lateral lista as funcionalidades do perfil',
        str_contains($r['body'], 'Liberar acessos e fichas') && str_contains($r['body'], 'Quadro de associados'));
    check('menu lateral lista os sistemas liberados',
        str_contains($r['body'], 'Controle Social') && str_contains($r['body'], 'Produção do Agricultor'));

    // 4. Isolamento entre sistemas -------------------------------------------
    $r = http($base . '/social/index.php', $jar);
    check('sem login no /social/ redireciona', $r['code'] === 303, 'code=' . $r['code']);
    check('redireciona para login do /social/', str_contains(localizacao($r), '/app/social/login.php'), localizacao($r));

    $r = http($base . '/social/login.php', $jar);
    check('login do /social/ abre com sessão própria', $r['code'] === 200 && csrfDe($r['body']) !== '', 'code=' . $r['code']);

    $jarNovo = dirname(__DIR__) . '/_smoke_social.txt';
    $jars[] = $jarNovo;
    @unlink($jarNovo);
    $r = http($base . '/social/login.php', $jarNovo);
    check('sessão do /social/ usa SINPORG_SOCIAL em /app/social/',
        str_contains($r['headers'], 'SINPORG_SOCIAL=')
        && preg_match('~path=/app/social/~i', $r['headers']) === 1);

    // 5. Admin entra em todos ------------------------------------------------
    $html = http($base . '/social/login.php', $jar)['body'];
    $r = http($base . '/social/login.php', $jar, ['csrf' => csrfDe($html), 'identificador' => $email, 'senha' => $senha]);
    check('admin entra no /social/', $r['code'] === 303, 'code=' . $r['code']);
    $r = http($base . '/social/index.php', $jar);
    check('painel /social/ abre para admin', $r['code'] === 200 && str_contains($r['body'], 'Painel do Gestor / Técnico'));

    $visitaSmoke = (int) $pdo->query('SELECT id FROM visitas_tecnicas ORDER BY id DESC LIMIT 1')->fetchColumn();
    if ($visitaSmoke > 0) {
        $rVisita = http($base . '/social/visita.php?id=' . $visitaSmoke, $jar);
        check('admin abre o detalhe da visita', $rVisita['code'] === 200 && str_contains($rVisita['body'], 'Resumo da Visita Técnica'));
    }

    $rRelatorio = http($base . '/social/relatorio.php?tipo=visitas', $jar);
    check('relatório de visitas por período abre', $rRelatorio['code'] === 200 && str_contains($rRelatorio['body'], 'Relatório de visitas e inspeções'));

    $ncSmoke = (int) $pdo->query('SELECT id FROM nao_conformidades ORDER BY id DESC LIMIT 1')->fetchColumn();
    if ($ncSmoke > 0) {
        $rNc = http($base . '/social/nc.php?id=' . $ncSmoke, $jar);
        check('admin abre o detalhe da NC', $rNc['code'] === 200 && str_contains($rNc['body'], 'Não Conformidade #'));
    }

    $rRoteiroNovo = http($base . '/social/roteiro.php?novo=1', $jar);
    check('admin abre o formulário de novo roteiro', $rRoteiroNovo['code'] === 200 && str_contains($rRoteiroNovo['body'], 'Novo roteiro de inspeção'));

    $planoAdmin = (int) $pdo->query('SELECT id FROM plano_manejo WHERE excluido = 0 ORDER BY id LIMIT 1')->fetchColumn();
    if ($planoAdmin > 0) {
        $rPlanoAdmin = http($base . '/social/modulo.php?modulo=planos&ver=' . $planoAdmin, $jar);
        check('admin consulta o plano de manejo completo',
            $rPlanoAdmin['code'] === 200 && str_contains($rPlanoAdmin['body'], 'Histórico de pareceres'));
    }

    $html = http($base . '/producao/login.php', $jar)['body'];
    $r = http($base . '/producao/login.php', $jar, ['csrf' => csrfDe($html), 'identificador' => $email, 'senha' => $senha]);
    check('admin entra no /producao/', $r['code'] === 303, 'code=' . $r['code']);
    $r = http($base . '/producao/index.php', $jar);
    check('painel /producao/ abre para admin',
        $r['code'] === 200
        && str_contains($r['body'], 'Painel da Produção')
        && str_contains($r['body'], 'Produção Vegetal')
        && str_contains($r['body'], 'from-amber-700'));

    // 6. Logout isolado ------------------------------------------------------
    $conteudoJar = (string) @file_get_contents($jar);
    check('jar guarda os cookies em caminhos distintos',
        str_contains($conteudoJar, '/app/assoc') && str_contains($conteudoJar, '/app/social'));

    $r = http($base . '/assoc/logout.php', $jar);
    check('logout do /assoc/ redireciona', $r['code'] === 303 && str_contains(localizacao($r), '/app/assoc/login.php'));
    $r = http($base . '/assoc/index.php', $jar);
    check('após logout, /assoc/ volta ao login', $r['code'] === 303);
    $r = http($base . '/social/index.php', $jar);
    check('sessão do /social/ continua ativa após logout do /assoc/', $r['code'] === 200, 'code=' . $r['code']);

    // 7. Servidor nega acesso direto ao código -------------------------------
    $r = http($base . '/src/Platform/Ui.php', $jar);
    check('src/ bloqueado pelo .htaccess', $r['code'] === 403, 'code=' . $r['code']);
} finally {
    $pdo->prepare('UPDATE usuarios SET senha = :h WHERE id = :id')->execute(['h' => $hashOriginal, 'id' => $id]);
    $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $restaurado = hash_equals($hashOriginal, (string) $stmt->fetchColumn());
    foreach ($jars as $jar) {
        @unlink($jar);
    }
    echo PHP_EOL . ($restaurado ? 'PASS ' : 'FALHA') . '  senha original restaurada' . PHP_EOL;
    if (!$restaurado) {
        $falhas++;
    }
    $ok = $total - $falhas + ($restaurado ? 1 : 0);
    echo PHP_EOL . 'resultado: ' . $ok . '/' . ($total + 1) . ' verificações OK' . PHP_EOL;
    exit($falhas > 0 ? 1 : 0);
}
