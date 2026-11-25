<?php

declare(strict_types=1);

/**
 * Bootstrap do framework de sistemas (assoc / social / producao).
 *
 * Além do autoload dos serviços da plataforma, ele reaproveita o núcleo
 * existente do projeto (`config/config.php`, `classes/Database.php`,
 * `classes/AuditLog.php`) — sem duplicar conexão, WAF nem auditoria.
 */

if (!defined('SINPORG_SKIP_SESSION')) {
    // Cada sistema controla a própria sessão (nome e caminho do cookie).
    define('SINPORG_SKIP_SESSION', true);
}

$raizProjeto = dirname(__DIR__, 2);

// Buffer: garante que um newline acidental de um require não impeça
// redirecionamentos (header()) nem a emissão dos headers de segurança.
ob_start();

require_once $raizProjeto . '/config/config.php';

foreach ([
    'Aplicacoes',
    'Urls',
    'Csrf',
    'Sessao',
    'Autorizacao',
    'Identidades',
    'Auditoria',
    'Cadastro',
    'Onboarding',
    'Papeis',
    'MinhaFicha',
    'ProducaoRecursos',
    'ProducaoRegistros',
    'ProducaoAreas',
    'ProducaoImagens',
    'PlanoManejo',
    'SocialModulos',
    'PainelDados',
    'Pendencias',
    'Tabelas',
    'Certificacoes',
    'Certificadoras',
    'Conta',
    'Plataforma',
    'Ui',
    'UiLogin',
    'UiPendencias',
    'UiModulo',
    'UiCertificacoes',
    'UiCertificadoras',
    'UiConta',
    'UiFichaModal',
    'UiPapeis',
    'UiMinhaFicha',
    'UiProducao',
    'UiProducaoFerramentas',
    'UiSocialModulos',
    'UiPainel',
    'UiPlanoManejo',
    'Fluxo',
    'FluxoCertificacoes',
    'FluxoCertificadoras',
    'FluxoConta',
    'FluxoPapeis',
    'FluxoMinhaFicha',
    'FluxoAnaliseFicha',
    'FluxoProducao',
    'FluxoProducaoFerramentas',
    'FluxoSocialModulos',
    'FluxoPainel',
    'FluxoPlanoManejo',
] as $classe) {
    require_once __DIR__ . '/' . $classe . '.php';
}
