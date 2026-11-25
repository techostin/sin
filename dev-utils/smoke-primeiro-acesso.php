<?php

declare(strict_types=1);

/**
 * Smoke test do PRIMEIRO ACESSO (mesma lógica do sistema atual):
 *  - pré-cadastro escolhendo a situação (perfil de acesso);
 *  - Fiscal/Técnico informando vínculo (órgão/especialidade) e dados essenciais;
 *  - produtor preenchendo a ficha de adesão (tabela ficha_adesao).
 *
 * Cria contas temporárias e as remove no finally.
 * Uso: php dev-utils/smoke-primeiro-acesso.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é CLI-only.');
}

define('SINPORG_SKIP_SESSION', true);
require dirname(__DIR__) . '/config/config.php';

if (!defined('DB_NAME') || DB_NAME !== 'sisagro') {
    exit("Banco inesperado. Abortando por segurança.\n");
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$base = 'http://localhost/app';
$senha = 'Smoke#2026';
$emails = [
    'pre' => 'precadastro.smoke@sinporg.local',
    'fiscal' => 'fiscal.smoke@sinporg.local',
    'produtor' => 'produtor.smoke@sinporg.local',
];
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
        CURLOPT_USERAGENT => 'SINPORG-onboarding-smoke/1.0',
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

function limparConta(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e');
    $stmt->execute(['e' => $email]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id <= 0) {
        return;
    }

    $fichas = $pdo->prepare('SELECT id FROM ficha_adesao WHERE usuario_id = :id');
    $fichas->execute(['id' => $id]);
    foreach ($fichas->fetchAll(PDO::FETCH_COLUMN) as $fichaId) {
        $pdo->prepare('DELETE FROM ficha_adesao_produtos WHERE ficha_id = :f')->execute(['f' => (int) $fichaId]);
        $pdo->prepare('DELETE FROM ficha_adesao WHERE id = :f')->execute(['f' => (int) $fichaId]);
    }

    $pdo->prepare('DELETE FROM usuario_papel WHERE usuario_id = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $id]);
}

/** @param array<string, mixed> $dados */
function criarUsuario(PDO $pdo, array $dados): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios
            (nome, sobrenome, email, senha, nivel, tipo, tipo_usuario, status,
             telefone, cpf, orgao, especialidade, setor, area_producao, tem_agroindustria, created_at)
         VALUES
            (:nome, :sobrenome, :email, :senha, :nivel, :tipo, :tipo_usuario, :status,
             :telefone, :cpf, :orgao, :especialidade, :setor, :area_producao, :tem_agroindustria, NOW())'
    );
    $stmt->execute([
        'nome' => $dados['nome'],
        'sobrenome' => $dados['sobrenome'] ?? 'Smoke',
        'email' => $dados['email'],
        'senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
        'nivel' => $dados['nivel'],
        'tipo' => $dados['tipo'],
        'tipo_usuario' => $dados['tipo_usuario'] ?? 'gestor',
        'status' => 'ativo',
        'telefone' => $dados['telefone'] ?? null,
        'cpf' => $dados['cpf'] ?? null,
        'orgao' => $dados['orgao'] ?? null,
        'especialidade' => $dados['especialidade'] ?? null,
        'setor' => $dados['setor'] ?? null,
        'area_producao' => $dados['area_producao'] ?? '',
        'tem_agroindustria' => $dados['tem_agroindustria'] ?? 0,
    ]);

    return (int) $pdo->lastInsertId();
}

try {
    foreach ($emails as $email) {
        limparConta($pdo, $email);
    }

    // Conta admin usada para liberar o acesso na tela de pendências.
    $adminEmail = 'admin.teste@sinporg.local';
    $stmtAdmin = $pdo->prepare('SELECT id, senha FROM usuarios WHERE email = :e LIMIT 1');
    $stmtAdmin->execute(['e' => $adminEmail]);
    $adminConta = $stmtAdmin->fetch() ?: null;
    if (!$adminConta) {
        throw new RuntimeException('Conta admin de teste ausente: ' . $adminEmail);
    }
    $adminId = (int) $adminConta['id'];
    $adminHashOriginal = (string) $adminConta['senha'];
    $pdo->prepare('UPDATE usuarios SET senha = :h WHERE id = :id')
        ->execute(['h' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $adminId]);

    // -------------------------------------------------------------
    // 1. Página de login: imagem de fundo e abas do primeiro acesso
    // -------------------------------------------------------------
    $jarPre = dirname(__DIR__) . '/_smoke_pre.txt';
    $jars[] = $jarPre;
    @unlink($jarPre);

    $r = http($base . '/assoc/login.php', $jarPre);
    check('login responde 200', $r['code'] === 200, 'code=' . $r['code']);

    $rLegado = http($base . '/login', $jarPre);
    check('página de login permanece sem os portais', $rLegado['code'] === 200 && !str_contains($rLegado['body'], 'Portal do Técnico'));

    $rLanding = http($base . '/inicio.php', $jarPre);
    check('landing oferece os três portais de acesso no header',
        $rLanding['code'] === 200
        && str_contains($rLanding['body'], 'Área do Associado')
        && str_contains($rLanding['body'], 'Área do Técnico')
        && str_contains($rLanding['body'], 'Área do Agricultor')
        && str_contains($rLanding['body'], 'assoc/login.php')
        && str_contains($rLanding['body'], 'social/login.php')
        && str_contains($rLanding['body'], 'producao/login.php'));

    $rSite = http($base . '/site', $jarPre);
    check('landing também abre pela URL amigável /app/site',
        $rSite['code'] === 200 && str_contains($rSite['body'], 'Área do Associado'));
    check('/app/site tem botões de acesso das três áreas e do admin',
        str_contains($rSite['body'], 'Área do Associado')
        && str_contains($rSite['body'], 'Área do Técnico')
        && str_contains($rSite['body'], 'Área do Agricultor')
        && str_contains($rSite['body'], 'Área do Administrador')
        && str_contains($rSite['body'], 'assoc/login.php')
        && str_contains($rSite['body'], 'social/login.php')
        && str_contains($rSite['body'], 'producao/login.php')
        && str_contains($rSite['body'], 'href="login"'));
    check('login usa a imagem de fundo do sistema atual', str_contains($r['body'], 'assets/img/login-bg.jpg'));
    check('login tem abas Entrar/Primeiro Acesso/Perfil de Acesso',
        str_contains($r['body'], 'Primeiro Acesso') && str_contains($r['body'], 'Perfil de Acesso'));
    check('login tem as etapas do primeiro acesso',
        str_contains($r['body'], 'Continuar')
        && str_contains($r['body'], 'Solicitar Acesso')
        && str_contains($r['body'], 'name="perfil"')
        && str_contains($r['body'], 'Consumidor / Cliente'));

    // -------------------------------------------------------------
    // 2. Pré-cadastro: usuário informa a situação (perfil de acesso)
    // -------------------------------------------------------------
    // Sem escolher a situação, o servidor devolve o passo 2 com erro.
    $rSemPerfil = http($base . '/assoc/login.php', $jarPre, [
        'acao' => 'pre_cadastro',
        'csrf' => csrfDe($r['body']),
        'nome' => 'Pre',
        'sobrenome' => 'Cadastro',
        'email' => $emails['pre'],
        'senha' => $senha,
        'confirma_senha' => $senha,
    ]);
    check('pré-cadastro exige a situação (perfil)',
        str_contains($rSemPerfil['body'], 'Selecione um perfil de acesso')
        && str_contains($rSemPerfil['body'], 'name="perfil"'));

    $r = http($base . '/assoc/login.php', $jarPre, [
        'acao' => 'pre_cadastro',
        'csrf' => csrfDe($rSemPerfil['body']),
        'nome' => 'Pre',
        'sobrenome' => 'Cadastro',
        'email' => $emails['pre'],
        'senha' => $senha,
        'confirma_senha' => $senha,
        'perfil' => 'vegetal',
    ]);
    check('pré-cadastro é aceito', $r['code'] === 200 && str_contains($r['body'], 'Cadastro enviado!'));
    check('sucesso do pré-cadastro oferece voltar ao login',
        str_contains($r['body'], 'Ir para Login') && !str_contains($r['body'], 'name="acao" value="pre_cadastro"'));

    $stmt = $pdo->prepare('SELECT nivel, tipo, tipo_usuario, area_producao, perfil_solicitado, status FROM usuarios WHERE email = :e');
    $stmt->execute(['e' => $emails['pre']]);
    $pre = $stmt->fetch() ?: [];
    check('conta nasce pendente com perfil solicitado',
        (string) ($pre['status'] ?? '') === 'pendente'
        && (int) ($pre['nivel'] ?? 0) === 3
        && (string) ($pre['tipo_usuario'] ?? '') === 'agricultor'
        && (string) ($pre['area_producao'] ?? '') === 'vegetal'
        && (string) ($pre['perfil_solicitado'] ?? '') === 'vegetal');

    // -------------------------------------------------------------
    // 2b. Conta pendente não entra: aguarda liberação da diretoria
    // -------------------------------------------------------------
    $jarPrePendente = dirname(__DIR__) . '/_smoke_pre_pendente.txt';
    $jars[] = $jarPrePendente;
    @unlink($jarPrePendente);
    $r = http($base . '/assoc/login.php', $jarPrePendente);
    $r = http($base . '/assoc/login.php', $jarPrePendente, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['pre'],
        'senha' => $senha,
    ]);
    check('conta pendente aguarda liberação', $r['code'] === 200 && str_contains($r['body'], 'Aguardando liberação'));

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e');
    $stmt->execute(['e' => $emails['pre']]);
    $preId = (int) ($stmt->fetchColumn() ?: 0);

    // -------------------------------------------------------------
    // 2c. Diretoria libera o acesso pela tela de pendências
    // -------------------------------------------------------------
    $jarAdmin = dirname(__DIR__) . '/_smoke_admin_pendencias.txt';
    $jars[] = $jarAdmin;
    @unlink($jarAdmin);
    $r = http($base . '/assoc/login.php', $jarAdmin);
    $r = http($base . '/assoc/login.php', $jarAdmin, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $adminEmail,
        'senha' => $senha,
    ]);
    check('admin entra para liberar acessos', $r['code'] === 303, 'code=' . $r['code']);

    $r = http($base . '/assoc/pendencias.php', $jarAdmin);
    check('tela de pendências abre para a diretoria', $r['code'] === 200 && str_contains($r['body'], 'Contas aguardando liberação'));
    check('menu lateral presente nas pendências', str_contains($r['body'], 'id="menu-lateral"'));
    check('tela lista a conta pendente', str_contains($r['body'], $emails['pre']));

    $r = http($base . '/assoc/pendencias.php', $jarAdmin, [
        'csrf' => csrfDe($r['body']),
        'tipo' => 'acesso',
        'acao' => 'liberar',
        'id' => (string) $preId,
        'nivel_concedido' => '3',
    ]);
    check('liberação redireciona (303)', $r['code'] === 303, 'code=' . $r['code']);

    $r = http($base . '/assoc/pendencias.php', $jarAdmin);
    check('liberação confirma na tela', str_contains($r['body'], 'Acesso liberado'));

    $stmt = $pdo->prepare('SELECT status FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $preId]);
    check('conta passa a ativa após liberação', (string) $stmt->fetchColumn() === 'ativo');

    // 2d. Depois da liberação, o primeiro acesso cai na ficha de adesão
    $jarPreLiberado = dirname(__DIR__) . '/_smoke_pre_liberado.txt';
    $jars[] = $jarPreLiberado;
    @unlink($jarPreLiberado);
    $r = http($base . '/assoc/login.php', $jarPreLiberado);
    $r = http($base . '/assoc/login.php', $jarPreLiberado, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['pre'],
        'senha' => $senha,
    ]);
    check('conta liberada vai ao dashboard do associado', $r['code'] === 303 && str_contains(localizacao($r), 'index.php'), localizacao($r));
    $rDash = http($base . '/assoc/index.php', $jarPreLiberado);
    check('dashboard do associado abre com o modal da ficha',
        $rDash['code'] === 200
        && str_contains($rDash['body'], 'modal-ficha-adesao')
        && str_contains($rDash['body'], 'Ficha de adesão'));

    // -------------------------------------------------------------
    // 3. Fiscal/Técnico: vínculo + dados essenciais no primeiro acesso
    // -------------------------------------------------------------
    criarUsuario($pdo, [
        'nome' => 'Fiscal',
        'email' => $emails['fiscal'],
        'senha' => $senha,
        'nivel' => 2,
        'tipo' => 'gestor',
        'tipo_usuario' => 'gestor',
        'telefone' => null,
        'cpf' => null,
        'orgao' => null,
        'especialidade' => null,
    ]);

    $jarFiscal = dirname(__DIR__) . '/_smoke_fiscal.txt';
    $jars[] = $jarFiscal;
    @unlink($jarFiscal);

    $r = http($base . '/social/login.php', $jarFiscal);
    $r = http($base . '/social/login.php', $jarFiscal, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['fiscal'],
        'senha' => $senha,
    ]);
    check('fiscal cai na etapa de vínculo', str_contains($r['body'], 'Confirmar vínculo'));

    $r = http($base . '/social/login.php', $jarFiscal, [
        'csrf' => csrfDe($r['body']),
        'etapa' => 'vinculo',
        'orgao' => 'MAPA',
        'especialidade' => 'Fiscal Agropecuário',
        'setor' => 'Certificação',
    ]);
    check('após vínculo, pede dados essenciais (perfil)', str_contains($r['body'], 'Complete seu perfil'));

    $r = http($base . '/social/login.php', $jarFiscal, [
        'csrf' => csrfDe($r['body']),
        'etapa' => 'perfil',
        'telefone' => '51999990000',
        'cpf' => '39053344705',
    ]);
    check('concluir perfil autentica (303)', $r['code'] === 303, 'code=' . $r['code']);

    $r = http($base . '/social/index.php', $jarFiscal);
    check('fiscal entra no sistema de controle social', $r['code'] === 200, 'code=' . $r['code']);
    check('painel identifica o perfil Fiscal/Técnico', str_contains($r['body'], 'Fiscal / Técnico'));
    check('menu lateral do fiscal tem os 13 itens de fiscalização',
        str_contains($r['body'], 'id="menu-lateral"')
        && str_contains($r['body'], '13 de 13')
        && str_contains($r['body'], 'Painel do Gestor / Técnico')
        && str_contains($r['body'], 'Certificações (SPG/OCS)')
        && str_contains($r['body'], 'Certificadoras')
        && str_contains($r['body'], 'Planos de Manejo — Análise')
        && str_contains($r['body'], 'Insumos Aprovados')
        && str_contains($r['body'], 'Visitas e Inspeções')
        && str_contains($r['body'], 'Agendar Visita')
        && str_contains($r['body'], 'Roteiros (Templates)')
        && str_contains($r['body'], 'Não Conformidades')
        && str_contains($r['body'], 'Validação de Insumos')
        && str_contains($r['body'], 'Relatórios Administrativos')
        && str_contains($r['body'], 'Relatórios de Conformidade')
        && str_contains($r['body'], 'Consulta CNPO')
        && !str_contains($r['body'], 'Mensalidades e caixa'));

    $rAgendar = http($base . '/social/agendar-visita.php', $jarFiscal);
    check('fiscal abre o formulário Agendar Visita',
        $rAgendar['code'] === 200
        && str_contains($rAgendar['body'], 'Agendar visita técnica')
        && str_contains($rAgendar['body'], 'name="roteiro_id"')
        && str_contains($rAgendar['body'], 'name="associado_id"'));
    check('menu lateral presente no agendamento de visita', str_contains($rAgendar['body'], 'id="menu-lateral"'));

    // As funcionalidades do menu abrem em tela própria ou formulário.
    $chavesFiscal = [
        'painel.gestor', 'certificacoes.listar', 'certificadoras.listar', 'planos.analise', 'insumos.aprovados',
        'visitas.inspecoes', 'agendar.visita', 'roteiros.templates', 'nao.conformidades',
        'validacao.insumos', 'relatorios.admin', 'relatorios.conformidade', 'consulta.cnpo',
    ];
    $falhasTabela = [];
    foreach ($chavesFiscal as $chaveFiscal) {
        $rT = http($base . '/social/index.php?funcao=' . rawurlencode($chaveFiscal), $jarFiscal);
        $okCodigo = in_array($rT['code'], [200, 303], true);
        $okConteudo = $rT['code'] === 303
            || (str_contains($rT['body'], 'id="tabela-funcao"') && $rT['code'] === 200);
        if (!$okCodigo || !$okConteudo || str_contains($rT['body'], 'Fatal error')) {
            $falhasTabela[] = $chaveFiscal . ' (' . $rT['code'] . ')';
        }
    }
    check('as funcionalidades do fiscal abrem (tela própria ou formulário)', $falhasTabela === [], implode(', ', $falhasTabela));

    $rPainelGestor = http($base . '/social/painel.php', $jarFiscal);
    check('painel do gestor/técnico abre com indicadores',
        $rPainelGestor['code'] === 200
        && str_contains($rPainelGestor['body'], 'Painel do Gestor / Técnico')
        && str_contains($rPainelGestor['body'], 'associados ativos')
        && str_contains($rPainelGestor['body'], 'visitas agendadas')
        && str_contains($rPainelGestor['body'], 'NCs abertas')
        && str_contains($rPainelGestor['body'], 'from-sky-800'));

    $rPlanos = http($base . '/social/modulo.php?modulo=planos', $jarFiscal);
    check('módulo de planos de manejo abre para o técnico',
        $rPlanos['code'] === 200 && str_contains($rPlanos['body'], 'Planos de Manejo — Análise'));
    $rRelSocial = http($base . '/social/relatorios.php', $jarFiscal);
    check('relatórios da fiscalização abrem',
        $rRelSocial['code'] === 200 && str_contains($rRelSocial['body'], 'Relatórios Administrativos e de Conformidade'));

    // ===== Plano de manejo: consulta completa e sugestões do técnico =====
    $planoTeste = (int) $pdo->query('SELECT id FROM plano_manejo WHERE excluido = 0 ORDER BY id LIMIT 1')->fetchColumn();
    if ($planoTeste > 0) {
        $rPlanoDetalhe = http($base . '/social/modulo.php?modulo=planos&ver=' . $planoTeste, $jarFiscal);
        check('técnico consulta o plano de manejo completo',
            $rPlanoDetalhe['code'] === 200
            && str_contains($rPlanoDetalhe['body'], 'Parecer e sugestões de ajuste')
            && str_contains($rPlanoDetalhe['body'], 'Planejamento de cultivos'));

        $pdo->prepare("DELETE FROM plano_manejo_pareceres WHERE parecer LIKE 'Smoke parecer%'")->execute();
        $rParecer = http($base . '/social/modulo.php?modulo=planos', $jarFiscal, [
            'csrf' => csrfDe($rPlanoDetalhe['body']),
            'acao' => 'plano_parecer',
            'id' => $planoTeste,
            'status' => 'Sugestão',
            'parecer' => 'Smoke parecer técnico',
            'sugestoes' => 'Smoke sugestão de ajuste',
        ]);
        $stmtParecer = $pdo->prepare('SELECT COUNT(*) FROM plano_manejo_pareceres WHERE plano_manejo_id = :id AND parecer = :parecer');
        $stmtParecer->execute(['id' => $planoTeste, 'parecer' => 'Smoke parecer técnico']);
        check('técnico registra sugestão de ajuste no plano',
            $rParecer['code'] === 303 && (int) $stmtParecer->fetchColumn() === 1);
        $pdo->prepare("DELETE FROM plano_manejo_pareceres WHERE parecer LIKE 'Smoke parecer%'")->execute();
    }

    // Agendamento real: grava em visitas_tecnicas e limpa o registro de teste.
    $associadoTeste = (int) $pdo->query("SELECT id FROM associados WHERE excluido_em IS NULL AND status = 'ativo' ORDER BY id LIMIT 1")->fetchColumn();
    $propriedadeTeste = (int) $pdo->query("SELECT id FROM propriedades WHERE associado_id = {$associadoTeste} ORDER BY id LIMIT 1")->fetchColumn();
    $roteiroTeste = (int) $pdo->query('SELECT id FROM roteiros_inspecao WHERE ativo = 1 ORDER BY id LIMIT 1')->fetchColumn();
    $dataTeste = '2099-12-31';
    $pdo->exec("DELETE FROM visitas_tecnicas WHERE data_visita = '{$dataTeste}'");

    $rAgendarPost = http($base . '/social/agendar-visita.php', $jarFiscal, [
        'csrf' => csrfDe($rAgendar['body']),
        'associado_id' => $associadoTeste,
        'propriedade_id' => $propriedadeTeste,
        'roteiro_id' => $roteiroTeste,
        'data_visita' => $dataTeste,
        'hora_inicio' => '09:00',
    ]);
    $gravadas = (int) $pdo->query("SELECT COUNT(*) FROM visitas_tecnicas WHERE associado_id = {$associadoTeste} AND data_visita = '{$dataTeste}'")->fetchColumn();
    check('agendamento de visita grava em visitas_tecnicas', $rAgendarPost['code'] === 303 && $gravadas === 1, 'code=' . $rAgendarPost['code'] . ' gravadas=' . $gravadas);

    $visitaTesteId = (int) $pdo->query("SELECT id FROM visitas_tecnicas WHERE associado_id = {$associadoTeste} AND data_visita = '{$dataTeste}' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $rModalVisitas = http($base . '/social/painel.php', $jarFiscal);
    check('modal de confirmações de visitas é exibido ao gestor/técnico',
        $rModalVisitas['code'] === 200
        && str_contains($rModalVisitas['body'], 'Confirmações de Visitas Agendadas')
        && str_contains($rModalVisitas['body'], 'visita_ciente')
        && str_contains($rModalVisitas['body'], 'visita_reagendar'));

    $rCienteVisita = http($base . '/social/painel.php', $jarFiscal, [
        'csrf' => csrfDe($rModalVisitas['body']),
        'acao' => 'visita_ciente',
        'visita_id' => $visitaTesteId,
    ]);
    $stmtCiente = $pdo->prepare('SELECT gestor_ciente FROM visitas_tecnicas WHERE id = :id');
    $stmtCiente->execute(['id' => $visitaTesteId]);
    check('ciência do gestor é gravada na visita',
        $rCienteVisita['code'] === 303 && (int) $stmtCiente->fetchColumn() === 1);
    $pdo->exec("DELETE FROM visitas_tecnicas WHERE data_visita = '{$dataTeste}'");

    // ===== Certificações (SPG/OCS): lista, filtros e todas as ações =====
    $prefixoCert = 'SMOKE-CERT-' . date('His');
    $pdo->exec("DELETE FROM certificacao_participativa WHERE numero_certificado LIKE 'SMOKE-CERT-%'");
    $certAssoc = (int) $pdo->query("SELECT id FROM associados WHERE excluido_em IS NULL ORDER BY id LIMIT 1")->fetchColumn();
    $certOrganismo = (int) $pdo->query('SELECT id FROM certificadoras ORDER BY id LIMIT 1')->fetchColumn();

    $rCertLista = http($base . '/social/certificacoes.php', $jarFiscal);
    check('módulo de certificações abre', $rCertLista['code'] === 200 && str_contains($rCertLista['body'], 'Aplicar filtros'), 'code=' . $rCertLista['code']);
    check('menu lateral presente nas certificações', str_contains($rCertLista['body'], 'id="menu-lateral"'));

    $rCertFiltro = http($base . '/social/certificacoes.php?tipo=SPG&validade=proximas', $jarFiscal);
    check('filtros de certificações respondem', $rCertFiltro['code'] === 200 && str_contains($rCertFiltro['body'], 'name="tipo"'));

    $rCertNova = http($base . '/social/certificacoes.php?acao=nova', $jarFiscal);
    check('formulário de nova certificação abre', $rCertNova['code'] === 200 && str_contains($rCertNova['body'], 'name="numero_certificado"'));

    $hoje = date('Y-m-d');
    $validade = date('Y-m-d', strtotime('+1 year'));
    $camposCert = [
        'associado_id' => $certAssoc,
        'certificadora_id' => $certOrganismo,
        'tipo_certificacao' => 'SPG',
        'data_emissao' => $hoje,
        'data_validade' => $validade,
        'status' => 'Ativa',
        'organismo_certificador' => 'OPAC Smoke',
        'nome_grupo' => 'Grupo Smoke',
        'observacoes' => 'criado pelo smoke',
    ];

    $rCertPost = http($base . '/social/certificacoes.php', $jarFiscal, $camposCert + [
        'csrf' => csrfDe($rCertNova['body']),
        'acao' => 'salvar',
        'modo' => 'nova',
        'numero_certificado' => $prefixoCert,
    ]);
    $certId = (int) $pdo->query("SELECT id FROM certificacao_participativa WHERE numero_certificado = '{$prefixoCert}' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check('certificação é criada', $rCertPost['code'] === 303 && $certId > 0, 'code=' . $rCertPost['code'] . ' id=' . $certId);

    $rCertDet = http($base . '/social/certificacoes.php?acao=detalhes&id=' . $certId, $jarFiscal);
    check('detalhes da certificação abrem', $rCertDet['code'] === 200 && str_contains($rCertDet['body'], $prefixoCert));

    $rCertEditar = http($base . '/social/certificacoes.php?acao=editar&id=' . $certId, $jarFiscal);
    $rCertPut = http($base . '/social/certificacoes.php', $jarFiscal, array_merge($camposCert, [
        'csrf' => csrfDe($rCertEditar['body']),
        'acao' => 'salvar',
        'modo' => 'editar',
        'id' => $certId,
        'numero_certificado' => $prefixoCert,
        'observacoes' => 'editado pelo smoke',
    ]));
    $obsCert = (string) $pdo->query("SELECT observacoes FROM certificacao_participativa WHERE id = {$certId}")->fetchColumn();
    check('certificação é editada', $rCertPut['code'] === 303 && $obsCert === 'editado pelo smoke', 'code=' . $rCertPut['code'] . ' obs=' . $obsCert);

    $rCertRenovar = http($base . '/social/certificacoes.php?acao=renovar&id=' . $certId, $jarFiscal);
    check('formulário de renovação abre', $rCertRenovar['code'] === 200 && str_contains($rCertRenovar['body'], 'Renovar certificação'));

    $rCertRen = http($base . '/social/certificacoes.php', $jarFiscal, [
        'csrf' => csrfDe($rCertRenovar['body']),
        'acao' => 'salvar',
        'modo' => 'renovar',
        'id' => $certId,
        'numero_certificado' => $prefixoCert,
        'data_emissao' => $hoje,
        'data_validade' => date('Y-m-d', strtotime('+2 year')),
        'observacoes' => 'renovado pelo smoke',
    ]);
    $renovada = (string) $pdo->query("SELECT data_renovacao FROM certificacao_participativa WHERE id = {$certId}")->fetchColumn();
    check('certificação é renovada', $rCertRen['code'] === 303 && $renovada !== '', 'code=' . $rCertRen['code'] . ' renovacao=' . $renovada);

    $rCertHist = http($base . '/social/certificacoes.php?acao=historico&associado_id=' . $certAssoc, $jarFiscal);
    check('histórico de certificações abre', $rCertHist['code'] === 200 && str_contains($rCertHist['body'], 'Histórico de certificações'));

    $rCertImprimir = http($base . '/social/certificacoes.php?acao=certificado&id=' . $certId, $jarFiscal);
    check('certificado para impressão é gerado', $rCertImprimir['code'] === 200 && str_contains($rCertImprimir['body'], 'CERTIFICADO DE CONFORMIDADE ORGÂNICA'));

    $rCertExcluir = http($base . '/social/certificacoes.php', $jarFiscal, [
        'csrf' => csrfDe($rCertLista['body']),
        'acao' => 'excluir',
        'id' => $certId,
    ]);
    $excluida = (int) $pdo->query("SELECT excluido FROM certificacao_participativa WHERE id = {$certId}")->fetchColumn();
    check('certificação é excluída (soft delete)', $rCertExcluir['code'] === 303 && $excluida === 1, 'code=' . $rCertExcluir['code'] . ' excluido=' . $excluida);
    $pdo->exec("DELETE FROM certificacao_participativa WHERE numero_certificado LIKE 'SMOKE-CERT-%'");

    // ===== Meu perfil e Configurações (topo direito) =====
    $rPerfil = http($base . '/social/meu-perfil.php', $jarFiscal);
    check('meu perfil abre pela barra superior',
        $rPerfil['code'] === 200
        && str_contains($rPerfil['body'], 'Meu perfil')
        && str_contains($rPerfil['body'], 'id="menu-lateral"'));
    $fimAside = strpos($rPerfil['body'], '</aside>');
    $inicioAside = strpos($rPerfil['body'], 'id="menu-lateral"');
    $aside = ($inicioAside !== false && $fimAside !== false) ? substr($rPerfil['body'], $inicioAside, $fimAside - $inicioAside) : '';
    check('nome omitido no topo do menu lateral', $aside !== '' && !str_contains($aside, 'truncate text-sm font-medium text-gray-900'));

    $rConfig = http($base . '/social/configuracoes.php', $jarFiscal);
    check('configurações abrem pela barra superior',
        $rConfig['code'] === 200
        && str_contains($rConfig['body'], 'Notificações de eventos')
        && str_contains($rConfig['body'], 'Segurança'));

    $rPerfilErro = http($base . '/social/meu-perfil.php', $jarFiscal, [
        'csrf' => csrfDe($rPerfil['body']),
        'nome' => '',
        'email' => '',
    ]);
    check('perfil valida nome/e-mail obrigatórios',
        $rPerfilErro['code'] === 200
        && (str_contains($rPerfilErro['body'], 'O nome é obrigatório') || str_contains($rPerfilErro['body'], 'e-mail válido')));

    $rPreferencias = http($base . '/social/configuracoes.php', $jarFiscal, [
        'csrf' => csrfDe($rConfig['body']),
        'acao' => 'preferencias',
        'tema' => 'dark',
        'idioma' => 'pt-br',
        'notificacoes' => '1',
    ]);
    check('preferências são salvas', $rPreferencias['code'] === 303, 'code=' . $rPreferencias['code']);

    $rSenhaErro = http($base . '/social/configuracoes.php', $jarFiscal, [
        'csrf' => csrfDe($rConfig['body']),
        'acao' => 'senha',
        'senha_atual' => 'senha-errada-smoke',
        'senha_nova' => 'NovaSenha#2026',
        'senha_confirma' => 'NovaSenha#2026',
    ]);
    check('troca de senha exige senha atual correta',
        $rSenhaErro['code'] === 200 && str_contains($rSenhaErro['body'], 'senha atual está incorreta'));

    // ===== Certificadoras: lista, nova, edição e membros =====
    $nomeCertificadora = 'SMOKE-CERTIF-' . date('His');
    $pdo->exec("DELETE FROM certificadoras WHERE nome LIKE 'SMOKE-CERTIF-%'");

    $rCertificadoras = http($base . '/social/certificadoras.php', $jarFiscal);
    check('módulo de certificadoras abre',
        $rCertificadoras['code'] === 200
        && str_contains($rCertificadoras['body'], 'Nova certificadora')
        && str_contains($rCertificadoras['body'], 'id="menu-lateral"'));

    $rCertificadorasRedir = http($base . '/social/index.php?funcao=certificadoras.listar', $jarFiscal);
    check('URL ?funcao=certificadoras.listar redireciona para o módulo', $rCertificadorasRedir['code'] === 303, 'code=' . $rCertificadorasRedir['code']);

    $rCertificadoraNova = http($base . '/social/certificadoras.php?acao=nova', $jarFiscal);
    check('formulário de nova certificadora abre',
        $rCertificadoraNova['code'] === 200 && str_contains($rCertificadoraNova['body'], 'name="logo_url"'));

    $rCertificadoraPost = http($base . '/social/certificadoras.php', $jarFiscal, [
        'csrf' => csrfDe($rCertificadoraNova['body']),
        'acao' => 'salvar',
        'nome' => $nomeCertificadora,
        'sigla' => 'SMK',
        'cidade' => 'Porto Alegre',
        'uf' => 'RS',
        'contato' => 'smoke@certificadora.test',
        'site' => 'https://certificadora.test',
    ]);
    $certificadoraId = (int) $pdo->query("SELECT id FROM certificadoras WHERE nome = '{$nomeCertificadora}' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check('certificadora é criada', $rCertificadoraPost['code'] === 303 && $certificadoraId > 0, 'code=' . $rCertificadoraPost['code'] . ' id=' . $certificadoraId);

    $rCertificadoraEditar = http($base . '/social/certificadoras.php?acao=editar&id=' . $certificadoraId, $jarFiscal);
    check('edição de certificadora abre', $rCertificadoraEditar['code'] === 200 && str_contains($rCertificadoraEditar['body'], $nomeCertificadora));

    $rCertificadoraPut = http($base . '/social/certificadoras.php', $jarFiscal, [
        'csrf' => csrfDe($rCertificadoraEditar['body']),
        'acao' => 'salvar',
        'id' => $certificadoraId,
        'nome' => $nomeCertificadora,
        'sigla' => 'SMK',
        'cidade' => 'Porto Alegre',
        'uf' => 'RS',
        'contato' => 'editado@certificadora.test',
        'site' => 'https://certificadora.test',
    ]);
    $contatoCertificadora = (string) $pdo->query("SELECT contato FROM certificadoras WHERE id = {$certificadoraId}")->fetchColumn();
    check('certificadora é editada', $rCertificadoraPut['code'] === 303 && $contatoCertificadora === 'editado@certificadora.test', 'code=' . $rCertificadoraPut['code']);

    $rCertificadoraMembros = http($base . '/social/certificadoras.php?acao=membros&id=' . $certificadoraId, $jarFiscal);
    check('membros da certificadora abrem', $rCertificadoraMembros['code'] === 200 && str_contains($rCertificadoraMembros['body'], 'Membros —'));
    $pdo->exec("DELETE FROM certificadoras WHERE nome LIKE 'SMOKE-CERTIF-%'");

    // Técnico/fiscal NÃO acessa a área associativa: login recusado e volta à landing.
    $jarFiscalAssoc = dirname(__DIR__) . '/_smoke_fiscal_assoc.txt';
    $jars[] = $jarFiscalAssoc;
    @unlink($jarFiscalAssoc);
    $r = http($base . '/assoc/login.php', $jarFiscalAssoc);
    $r = http($base . '/assoc/login.php', $jarFiscalAssoc, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['fiscal'],
        'senha' => $senha,
    ]);
    check('técnico é redirecionado à landing ao tentar login na área associativa', $r['code'] === 303, 'code=' . $r['code']);

    $rAssoc = http($base . '/assoc/index.php', $jarFiscalAssoc);
    check('técnico não fica autenticado na área associativa', $rAssoc['code'] === 303, 'code=' . $rAssoc['code']);

    $rLandingNegado = http($base . '/site?acesso=negado', $jarFiscalAssoc);
    check('landing avisa que a área não corresponde ao perfil',
        $rLandingNegado['code'] === 200 && str_contains($rLandingNegado['body'], 'não corresponde ao perfil'));

    $stmt = $pdo->prepare('SELECT orgao, especialidade, setor, telefone, cpf FROM usuarios WHERE email = :e');
    $stmt->execute(['e' => $emails['fiscal']]);
    $fiscal = $stmt->fetch() ?: [];
    check('vínculo e dados essenciais foram gravados',
        (string) ($fiscal['orgao'] ?? '') === 'MAPA'
        && (string) ($fiscal['especialidade'] ?? '') === 'Fiscal Agropecuário'
        && (string) ($fiscal['telefone'] ?? '') === '51999990000'
        && (string) ($fiscal['cpf'] ?? '') === '39053344705');

    // -------------------------------------------------------------
    // 4. Produtor: primeiro acesso cai na ficha de adesão
    // -------------------------------------------------------------
    criarUsuario($pdo, [
        'nome' => 'Produtor',
        'email' => $emails['produtor'],
        'senha' => $senha,
        'nivel' => 3,
        'tipo' => 'usuario',
        'tipo_usuario' => 'agricultor',
        'telefone' => '51988887777',
        'cpf' => '11144477735',
        'area_producao' => 'vegetal',
    ]);

    $jarProd = dirname(__DIR__) . '/_smoke_produtor.txt';
    $jars[] = $jarProd;
    @unlink($jarProd);

    $r = http($base . '/assoc/login.php', $jarProd);
    $r = http($base . '/assoc/login.php', $jarProd, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['produtor'],
        'senha' => $senha,
    ]);
    check('produtor é levado ao dashboard com o modal da ficha', $r['code'] === 303 && str_contains(localizacao($r), 'index.php'), localizacao($r));

    // Agricultor também não entra na área do técnico: volta para a landing.
    $jarProdSocial = dirname(__DIR__) . '/_smoke_produtor_social.txt';
    $jars[] = $jarProdSocial;
    @unlink($jarProdSocial);
    $r = http($base . '/social/login.php', $jarProdSocial);
    $rSocialProd = http($base . '/social/login.php', $jarProdSocial, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['produtor'],
        'senha' => $senha,
    ]);
    check('agricultor é redirecionado à landing ao tentar login na área do técnico', $rSocialProd['code'] === 303, 'code=' . $rSocialProd['code']);

    $rDashProd = http($base . '/assoc/index.php', $jarProd);
    check('dashboard do produtor exibe o modal com o formulário da ficha',
        $rDashProd['code'] === 200
        && str_contains($rDashProd['body'], 'modal-ficha-adesao')
        && str_contains($rDashProd['body'], 'name="nome_completo"')
        && str_contains($rDashProd['body'], 'Consumidor(a) / Cliente'));
    check('modal da ficha fecha sem sair da conta',
        str_contains($rDashProd['body'], 'onclick="sinporgFecharFichaModal()"')
        && str_contains($rDashProd['body'], 'Fechar'));

    $rDraft = http($base . '/assoc/index.php', $jarProd, [
        'csrf' => csrfDe($rDashProd['body']),
        'acao' => 'ficha',
        'modo' => 'rascunho',
        'tipo_associado' => 'agricultor_familiar',
        'nome_completo' => 'Produtor Smoke (rascunho)',
    ]);
    check('rascunho da ficha é salvo pelo modal', $rDraft['code'] === 303, 'code=' . $rDraft['code']);

    $rDashRascunho = http($base . '/assoc/index.php', $jarProd);
    check('modal reabre com o rascunho salvo',
        $rDashRascunho['code'] === 200
        && str_contains($rDashRascunho['body'], 'modal-ficha-adesao')
        && str_contains($rDashRascunho['body'], 'Rascunho'));

    $rMenuRascunho = http($base . '/assoc/index.php?funcao=membro.meus-documentos', $jarProd);
    check('modal não aparece ao navegar pelo menu lateral',
        $rMenuRascunho['code'] === 200 && !str_contains($rMenuRascunho['body'], 'modal-ficha-adesao'));

    $rMinhaRascunho = http($base . '/assoc/minha-ficha.php', $jarProd);
    check('Minha ficha reabre o modal do rascunho',
        $rMinhaRascunho['code'] === 200
        && str_contains($rMinhaRascunho['body'], 'modal-ficha-adesao')
        && str_contains($rMinhaRascunho['body'], 'Rascunho'));

    $r = http($base . '/assoc/index.php', $jarProd, [
        'csrf' => csrfDe($rDashRascunho['body']),
        'acao' => 'ficha',
        'tipo_associado' => 'agricultor_familiar',
        'nome_completo' => 'Produtor Smoke',
        'data_nascimento' => '1990-05-10',
        'cpf' => '11144477735',
        'municipio' => 'Porto Alegre',
        'estado' => 'RS',
        'telefone_whatsapp' => '51988887777',
        'email' => $emails['produtor'],
        'nome_propriedade' => 'Sítio Smoke',
        'aceite_termos' => '1',
        'sistemas_producao' => ['horticultura'],
    ]);
    check('ficha é submetida pelo modal do dashboard (303)', $r['code'] === 303 && str_contains(localizacao($r), 'index.php'), 'code=' . $r['code']);

    $r = http($base . '/assoc/index.php', $jarProd);
    // Após o envio o modal pode reaparecer uma vez (para mostrar "em análise");
    // na navegação seguinte não deve mais aparecer.
    $rDepois = http($base . '/assoc/index.php', $jarProd);
    check('modal não reaparece a cada clique no mesmo login',
        $rDepois['code'] === 200 && !str_contains($rDepois['body'], 'modal-ficha-adesao'));

    $rMinhaAnalise = http($base . '/assoc/minha-ficha.php', $jarProd);
    check('Minha ficha mostra a situação Em análise',
        $rMinhaAnalise['code'] === 200
        && str_contains($rMinhaAnalise['body'], 'Minha ficha de adesão')
        && str_contains($rMinhaAnalise['body'], 'Em análise'));

    $stmt = $pdo->prepare(
        'SELECT f.status, f.usuario_id FROM ficha_adesao f
         JOIN usuarios u ON u.id = f.usuario_id
         WHERE u.email = :e'
    );
    $stmt->execute(['e' => $emails['produtor']]);
    $ficha = $stmt->fetch() ?: [];
    check('ficha gravada na tabela oficial em status pendente',
        (string) ($ficha['status'] ?? '') === 'pendente' && (int) ($ficha['usuario_id'] ?? 0) > 0);

    // Enquanto a ficha não é aprovada, ela volta a aparecer no próximo login
    // (mas não a cada clique/navegação dentro da mesma sessão).
    $r = http($base . '/assoc/index.php', $jarProd);
    check('produtor sem aprovação não vê o modal a cada navegação',
        $r['code'] === 200 && !str_contains($r['body'], 'modal-ficha-adesao'));

    $rLogout = http($base . '/assoc/logout.php', $jarProd);
    $rLogin = http($base . '/assoc/login.php', $jarProd);
    $rLogin = http($base . '/assoc/login.php', $jarProd, [
        'csrf' => csrfDe($rLogin['body']),
        'identificador' => $emails['produtor'],
        'senha' => $senha,
    ]);
    $rDashRelogin = http($base . '/assoc/index.php', $jarProd);
    check('ficha reaparece no dashboard após novo login',
        $rLogout['code'] === 303
        && $rLogin['code'] === 303
        && str_contains($rDashRelogin['body'], 'modal-ficha-adesao')
        && str_contains($rDashRelogin['body'], 'Ficha em análise'));

    // Também no dashboard do agricultor (produção) o modal aparece.
    $jarProdProducao = dirname(__DIR__) . '/_smoke_produtor_producao.txt';
    $jars[] = $jarProdProducao;
    @unlink($jarProdProducao);
    $r = http($base . '/producao/login.php', $jarProdProducao);
    $rProdLogin = http($base . '/producao/login.php', $jarProdProducao, [
        'csrf' => csrfDe($r['body']),
        'identificador' => $emails['produtor'],
        'senha' => $senha,
    ]);
    $rProdDash = http($base . '/producao/index.php', $jarProdProducao);
    check('dashboard do agricultor (produção) reabre o modal da ficha em análise',
        $rProdLogin['code'] === 303
        && $rProdDash['code'] === 200
        && str_contains($rProdDash['body'], 'modal-ficha-adesao')
        && str_contains($rProdDash['body'], 'Ficha em análise'));

    // ===== Aprovação da ficha, papel automático e gestão de papéis =====
    $stmtFicha = $pdo->prepare('SELECT f.id, f.usuario_id FROM ficha_adesao f
        JOIN usuarios u ON u.id = f.usuario_id WHERE u.email = :e ORDER BY f.id DESC LIMIT 1');
    $stmtFicha->execute(['e' => $emails['produtor']]);
    $fichaProd = $stmtFicha->fetch() ?: [];
    $fichaId = (int) ($fichaProd['id'] ?? 0);
    $produtorUserId = (int) ($fichaProd['usuario_id'] ?? 0);

    $rPend = http($base . '/assoc/pendencias.php', $jarAdmin);
    check('pendências indicam ficha completa e link de conferência',
        $rPend['code'] === 200
        && str_contains($rPend['body'], 'Ficha completa')
        && str_contains($rPend['body'], 'Conferir ficha completa'));

    $rAnalise = http($base . '/assoc/ficha-analise.php?id=' . $fichaId, $jarAdmin);
    check('diretoria confere a ficha completa antes de aprovar',
        $rAnalise['code'] === 200
        && str_contains($rAnalise['body'], 'Ficha de adesão — análise')
        && str_contains($rAnalise['body'], 'Ficha completa'));

    $rEdicao = http($base . '/assoc/ficha.php', $jarProd);
    check('associado edita a ficha enquanto está pendente',
        $rEdicao['code'] === 200
        && str_contains($rEdicao['body'], 'name="nome_completo"')
        && str_contains($rEdicao['body'], 'Salvar rascunho'));

    $rSemConfirmacao = http($base . '/assoc/pendencias.php', $jarAdmin, [
        'csrf' => csrfDe($rPend['body']),
        'tipo' => 'ficha',
        'acao' => 'aprovar',
        'id' => $fichaId,
    ]);
    $stmtStatus = $pdo->prepare('SELECT status FROM ficha_adesao WHERE id = :id');
    $stmtStatus->execute(['id' => $fichaId]);
    check('aprovação exige confirmação de completude',
        $rSemConfirmacao['code'] === 303 && (string) $stmtStatus->fetchColumn() === 'pendente');

    $rAprovacao = http($base . '/assoc/pendencias.php', $jarAdmin, [
        'csrf' => csrfDe($rPend['body']),
        'tipo' => 'ficha',
        'acao' => 'aprovar',
        'id' => $fichaId,
        'ficha_completa' => '1',
    ]);
    check('admin aprova a ficha do novo associado', $rAprovacao['code'] === 303, 'code=' . $rAprovacao['code']);

    $stmtPapeis = $pdo->prepare(
        "SELECT p.codigo FROM usuario_papel up JOIN papeis p ON p.id = up.papel_id
          WHERE up.usuario_id = :id AND up.ativo = 1"
    );
    $stmtPapeis->execute(['id' => $produtorUserId]);
    $papeisProd = array_map('strval', array_column($stmtPapeis->fetchAll(), 'codigo'));
    check('associado aprovado recebe o papel produtor automaticamente', in_array('produtor', $papeisProd, true), implode(',', $papeisProd));

    $r = http($base . '/assoc/index.php', $jarProd);
    check('associado aprovado vê funcionalidades no portal',
        $r['code'] === 200
        && !str_contains($r['body'], 'não tem funcionalidades liberadas')
        && str_contains($r['body'], 'Meu cadastro')
        && str_contains($r['body'], 'from-emerald-800'));
    check('membro vê funções exclusivas da própria trajetória',
        str_contains($r['body'], 'Minha ficha')
        && str_contains($r['body'], 'Meus documentos')
        && str_contains($r['body'], 'Minhas mensalidades')
        && str_contains($r['body'], 'Meus certificados')
        && str_contains($r['body'], 'Meus relatórios')
        && !str_contains($r['body'], 'Mensalidades e caixa')
        && !str_contains($r['body'], 'Quadro de associados')
        && !str_contains($r['body'], 'Relatórios da associação'));

    $rSelf = http($base . '/assoc/index.php?funcao=membro.minhas-mensalidades', $jarProd);
    check('tabela de autoatendimento do membro abre',
        $rSelf['code'] === 200 && str_contains($rSelf['body'], 'Minhas mensalidades'));

    $rMinhaFicha = http($base . '/assoc/minha-ficha.php', $jarProd);
    check('associado acessa a própria ficha pelo menu Minha ficha',
        $rMinhaFicha['code'] === 200
        && str_contains($rMinhaFicha['body'], 'Minha ficha de adesão')
        && str_contains($rMinhaFicha['body'], 'Aprovada')
        && str_contains($rMinhaFicha['body'], 'Produtor Smoke'));
    check('minha ficha permite imprimir/salvar PDF',
        str_contains($rMinhaFicha['body'], 'Imprimir / Salvar PDF')
        && str_contains($rMinhaFicha['body'], 'Produtos / culturas'));
    check('ficha aprovada não oferece mais edição',
        !str_contains($rMinhaFicha['body'], 'Editar ficha'));

    $rRel = http($base . '/assoc/index.php?funcao=membro.meus-relatorios', $jarProd);
    check('relatório consolidado do membro abre',
        $rRel['code'] === 200 && str_contains($rRel['body'], 'Meus relatórios'));

    $r = http($base . '/assoc/papeis.php', $jarAdmin);
    check('admin acessa papéis e permissões', $r['code'] === 200 && str_contains($r['body'], 'Papéis e permissões'));

    $r = http($base . '/assoc/papeis.php?usuario_id=' . $produtorUserId, $jarAdmin);
    check('tela de gerenciar papéis abre', $r['code'] === 200 && str_contains($r['body'], 'Gerenciar papéis'));

    $r = http($base . '/assoc/papeis.php', $jarAdmin, [
        'csrf' => csrfDe($r['body']),
        'usuario_id' => $produtorUserId,
        'papeis' => ['produtor', 'tesoureiro'],
    ]);
    check('admin define papéis do associado', $r['code'] === 303, 'code=' . $r['code']);

    $stmtPapeis->execute(['id' => $produtorUserId]);
    $papeisProd = array_map('strval', array_column($stmtPapeis->fetchAll(), 'codigo'));
    check('papel tesoureiro gravado', in_array('tesoureiro', $papeisProd, true), implode(',', $papeisProd));

    $r = http($base . '/assoc/index.php', $jarProd);
    check('novo papel reflete no portal do associado', $r['code'] === 200 && str_contains($r['body'], 'Mensalidades e caixa'));

    // ===== Painel Produção do Agricultor (separado do associativo) =====
    $rProdPainel = http($base . '/producao/index.php', $jarProdProducao);
    check('painel de produção do agricultor vegetal mostra só os módulos da área',
        $rProdPainel['code'] === 200
        && str_contains($rProdPainel['body'], 'Produção Vegetal')
        && str_contains($rProdPainel['body'], 'Ferramentas')
        && str_contains($rProdPainel['body'], 'from-amber-700')
        && str_contains($rProdPainel['body'], 'Plano de manejo obrigatório')
        && !str_contains($rProdPainel['body'], 'Produção Animal')
        && !str_contains($rProdPainel['body'], 'Agroindústria'));

    $rProdForm = http($base . '/producao/registro.php?recurso=propriedades&acao=novo', $jarProdProducao);
    check('formulário de propriedade abre no painel de produção',
        $rProdForm['code'] === 200
        && str_contains($rProdForm['body'], 'Novo registro')
        && str_contains($rProdForm['body'], 'name="propriedade"')
        && str_contains($rProdForm['body'], 'name="estado"')
        && str_contains($rProdForm['body'], 'name="cidade"')
        && str_contains($rProdForm['body'], 'name="imagem_principal"')
        && str_contains($rProdForm['body'], 'enctype="multipart/form-data"')
        && str_contains($rProdForm['body'], 'Selecione primeiro o estado')
        && str_contains($rProdForm['body'], "estado.addEventListener")
        && str_contains($rProdForm['body'], 'Zona de amortecimento')
        && !str_contains($rProdForm['body'], 'name="localizacao"'));

    $rProdCreate = http($base . '/producao/registro.php?recurso=propriedades', $jarProdProducao, [
        'csrf' => csrfDe($rProdForm['body']),
        'acao' => 'salvar',
        'propriedade' => 'Propriedade Smoke Produção',
        'cidade' => 'Porto Alegre',
        'estado' => 'RS',
        'area_total' => '12,5',
    ]);
    check('agricultor cria propriedade no painel de produção', $rProdCreate['code'] === 303, 'code=' . $rProdCreate['code']);

    $rProdLista = http($base . '/producao/registro.php?recurso=propriedades&q=Propriedade%20Smoke', $jarProdProducao);
    check('propriedade criada aparece na lista do agricultor',
        $rProdLista['code'] === 200 && str_contains($rProdLista['body'], 'Propriedade Smoke Produção'));

    $stmtPropSmoke = $pdo->prepare("SELECT id FROM propriedades WHERE propriedade LIKE 'Propriedade Smoke Produção%' ORDER BY id DESC LIMIT 1");
    $stmtPropSmoke->execute();
    $propriedadeSmokeId = (int) $stmtPropSmoke->fetchColumn();
    $rMeuPlano = http($base . '/producao/meu-plano.php?modo=novo', $jarProdProducao);
    check('agricultor acessa o formulário obrigatório de plano de manejo',
        $rMeuPlano['code'] === 200
        && str_contains($rMeuPlano['body'], 'Meu plano de manejo')
        && str_contains($rMeuPlano['body'], 'Enviar plano para análise')
        && str_contains($rMeuPlano['body'], 'Etapa 1 · Informações básicas')
        && str_contains($rMeuPlano['body'], 'Etapa 5 · Revisão final')
        && str_contains($rMeuPlano['body'], 'preview-cultivos')
        && str_contains($rMeuPlano['body'], 'wizard-next'));

    $rPlanoPost = http($base . '/producao/meu-plano.php', $jarProdProducao, [
        'csrf' => csrfDe($rMeuPlano['body']),
        'acao' => 'salvar',
        'modo' => 'submeter',
        'propriedade_id' => $propriedadeSmokeId,
        'ano_safra' => '2099/2100',
        'titulo' => 'Smoke plano de manejo',
    ]);
    $stmtPlanoSmoke = $pdo->prepare("SELECT status FROM plano_manejo WHERE titulo = 'Smoke plano de manejo' ORDER BY id DESC LIMIT 1");
    $stmtPlanoSmoke->execute();
    check('plano de manejo é enviado para análise',
        $rPlanoPost['code'] === 303 && (string) $stmtPlanoSmoke->fetchColumn() === 'Em Análise');

    $stmtPlanoId = $pdo->prepare("SELECT id FROM plano_manejo WHERE titulo = 'Smoke plano de manejo' ORDER BY id DESC LIMIT 1");
    $stmtPlanoId->execute();
    $planoSmokeId = (int) $stmtPlanoId->fetchColumn();
    $rListaPlanos = http($base . '/producao/meu-plano.php', $jarProdProducao);
    check('listagem de planos do agricultor segue o padrão legado',
        $rListaPlanos['code'] === 200
        && str_contains($rListaPlanos['body'], 'Planos de Manejo Orgânico')
        && str_contains($rListaPlanos['body'], '2099/2100')
        && str_contains($rListaPlanos['body'], 'modo=ver')
        && str_contains($rListaPlanos['body'], 'id="planos-datatable"')
        && str_contains($rListaPlanos['body'], 'new DataTable'));

    $rVerPlano = http($base . '/producao/meu-plano.php?modo=ver&id=' . $planoSmokeId, $jarProdProducao);
    check('visualização do plano pelo agricultor abre em modo somente leitura',
        $rVerPlano['code'] === 200
        && str_contains($rVerPlano['body'], 'Visualizar plano de manejo')
        && str_contains($rVerPlano['body'], 'Caracterização e práticas'));

    $rProdRel = http($base . '/producao/relatorios.php', $jarProdProducao);
    check('relatórios de produção abrem', $rProdRel['code'] === 200 && str_contains($rProdRel['body'], 'Relatórios de produção'));
    $rProdRast = http($base . '/producao/rastreabilidade.php', $jarProdProducao);
    check('rastreabilidade abre no painel de produção', $rProdRast['code'] === 200 && str_contains($rProdRast['body'], 'Rastreabilidade'));

    // Troca de conta: já autenticado como produtor, envia credenciais do admin
    // no mesmo navegador; o sistema deve validar o admin informado (não manter
    // o usuário anterior).
    $rPerfilTroca = http($base . '/assoc/meu-perfil.php', $jarProd);
    $rTroca = http($base . '/assoc/login.php', $jarProd, [
        'csrf' => csrfDe($rPerfilTroca['body']),
        'identificador' => $adminEmail,
        'senha' => $senha,
    ]);
    $rAdminAposTroca = http($base . '/assoc/meu-perfil.php', $jarProd);
    check('login com outra conta troca o usuário autenticado',
        $rTroca['code'] === 303
        && str_contains($rAdminAposTroca['body'], $adminEmail));

    // Tentativa de login com CSRF expirado não pode preservar a sessão anterior.
    $rLoginCsrfInvalido = http($base . '/assoc/login.php', $jarProd, [
        'csrf' => str_repeat('a', 64),
        'identificador' => $adminEmail,
        'senha' => $senha,
    ]);
    $rAposCsrfInvalido = http($base . '/assoc/index.php', $jarProd);
    check('tentativa de login inválida encerra a sessão anterior',
        $rLoginCsrfInvalido['code'] === 200
        && str_contains($rLoginCsrfInvalido['body'], 'Sessão expirada')
        && $rAposCsrfInvalido['code'] === 303
        && str_contains(localizacao($rAposCsrfInvalido), '/assoc/login.php'));
} finally {
    foreach ($jars as $jar) {
        @unlink($jar);
    }
    foreach (array_values($emails) as $email) {
        limparConta($pdo, $email);
    }
    $pdo->exec("DELETE pmc FROM plano_manejo_cultivos pmc JOIN plano_manejo pm ON pm.id = pmc.plano_manejo_id WHERE pm.titulo = 'Smoke plano de manejo'");
    $pdo->exec("DELETE FROM plano_manejo WHERE titulo = 'Smoke plano de manejo'");
    $pdo->prepare("DELETE FROM propriedades WHERE propriedade LIKE 'Propriedade Smoke Produção%'")->execute();

    $restaurado = false;
    if (isset($adminId, $adminHashOriginal) && $adminId > 0) {
        $pdo->prepare('UPDATE usuarios SET senha = :h WHERE id = :id')
            ->execute(['h' => $adminHashOriginal, 'id' => $adminId]);
        $stmtRestore = $pdo->prepare('SELECT senha FROM usuarios WHERE id = :id');
        $stmtRestore->execute(['id' => $adminId]);
        $restaurado = hash_equals($adminHashOriginal, (string) $stmtRestore->fetchColumn());
    }

    $restantes = 0;
    foreach (array_values($emails) as $email) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = :e');
        $stmt->execute(['e' => $email]);
        $restantes += (int) $stmt->fetchColumn();
    }
    echo PHP_EOL . ($restantes === 0 ? 'PASS ' : 'FALHA') . '  contas temporárias removidas' . PHP_EOL;
    if ($restantes !== 0) {
        $falhas++;
    }
    echo ($restaurado ? 'PASS ' : 'FALHA') . '  senha do admin restaurada' . PHP_EOL;
    if (!$restaurado) {
        $falhas++;
    }

    $extras = ($restantes === 0 ? 1 : 0) + ($restaurado ? 1 : 0);
    echo PHP_EOL . 'resultado: ' . ($total - $falhas + $extras) . '/' . ($total + 2) . ' verificações OK' . PHP_EOL;
    exit($falhas > 0 ? 1 : 0);
}
