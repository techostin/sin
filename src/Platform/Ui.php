<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Camada de apresentação do framework.
 *
 * A interface é dirigida a PERFIL: o painel renderiza apenas as funções que a
 * conta pode executar (`Autorizacao::funcoesVisiveis`). O administrador vê
 * todas as funções de todos os sistemas.
 */
final class Ui
{
    /** @var array<string, array<string, string>> */
    private const CORES = [
        'emerald' => [
            'gradiente' => 'from-emerald-700 to-emerald-900',
            'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'botao' => 'bg-emerald-700 hover:bg-emerald-800',
            'icone' => 'bg-emerald-100 text-emerald-800',
            'anel' => 'focus:ring-emerald-500',
        ],
        'teal' => [
            'gradiente' => 'from-teal-700 to-teal-900',
            'chip' => 'bg-teal-50 text-teal-700 ring-teal-200',
            'botao' => 'bg-teal-700 hover:bg-teal-800',
            'icone' => 'bg-teal-100 text-teal-800',
            'anel' => 'focus:ring-teal-500',
        ],
        'amber' => [
            'gradiente' => 'from-amber-600 to-amber-900',
            'chip' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'botao' => 'bg-amber-700 hover:bg-amber-800',
            'icone' => 'bg-amber-100 text-amber-900',
            'anel' => 'focus:ring-amber-500',
        ],
    ];

    /** @return array<string, string> */
    private static function cor(string $nome): array
    {
        return self::CORES[$nome] ?? self::CORES['emerald'];
    }

    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    /** Cabeçalho de grupo no menu lateral (ex.: Produção Vegetal/Animal). */
    private static function menuGrupo(string $grupo, string &$atual): void
    {
        if ($grupo === '' || $grupo === $atual) {
            return;
        }
        $atual = $grupo;
        ?>
        <li class="px-3 pb-1 pt-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">
            <?= self::e($grupo) ?>
        </li>
        <?php
    }

    private static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, static fn(string $p): bool => $p !== ''));
        if ($partes === []) {
            return '?';
        }
        $primeira = mb_substr($partes[0], 0, 1);
        $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }

    /**
     * Cabeçalho HTML comum (sem autenticação).
     *
     * @param array<string, mixed> $app
     */
    public static function head(string $titulo, array $app, bool $comTabelas = false): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1e3a2b">
<title><?= self::e($titulo) ?> · <?= self::e((string) ($app['nome'] ?? 'SINPORG')) ?></title>
<link rel="stylesheet" href="<?= self::e(Urls::asset('inter.css')) ?>">
<?php if ($comTabelas): ?>
<link rel="stylesheet" href="<?= self::e(Urls::asset('vendor/datatables.min.css')) ?>">
<?php endif; ?>
<script src="<?= self::e(Urls::asset('tailwind.js')) ?>"></script>
<style>
    body { font-family: 'Inter', sans-serif; }
    [x-cloak] { display: none; }
    #tabela-funcao { border-collapse: separate; border-spacing: 0; width: 100%; }
    #tabela-funcao thead th {
        background: #f8fafc; color: #0f172a; font-size: 11px; font-weight: 600;
        letter-spacing: .04em; text-transform: uppercase; text-align: left;
        border-bottom: 1px solid #e2e8f0; padding: 12px 14px; white-space: nowrap;
    }
    #tabela-funcao tbody td {
        padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155;
        vertical-align: middle;
    }
    #tabela-funcao tbody tr:hover td { background: #f8fafc; }
    #tabela-funcao tbody tr:last-child td { border-bottom: 0; }
    #tabela-funcao_wrapper .dt-layout-row { align-items: center; padding: 4px 0; }
    #tabela-funcao_wrapper .dt-search input,
    #tabela-funcao_wrapper .dt-length select {
        border: 1px solid #d1d5db; border-radius: .65rem; padding: .4rem .7rem;
        font-size: .8rem; background: #fff; color: #334155;
    }
    #tabela-funcao_wrapper .dt-search label,
    #tabela-funcao_wrapper .dt-length label { font-size: .72rem; color: #64748b; margin-right: .35rem; }
    #tabela-funcao_wrapper .dt-info { font-size: .75rem; color: #64748b; }
    #tabela-funcao_wrapper .dt-paging .dt-paging-button {
        border-radius: .55rem; border: 1px solid #e2e8f0; margin: 0 2px;
        font-size: .75rem; padding: .35rem .65rem; color: #475569;
    }
    #tabela-funcao_wrapper .dt-paging .dt-paging-button.current {
        background: #0f766e; color: #fff; border-color: #0f766e;
    }
    #tabela-funcao_wrapper .dt-paging .dt-paging-button:hover:not(.disabled) { background: #f0fdfa; }
    .menu-lateral-fundo { background-color: #E8E8E8; }
    .barra-superior-fundo { background-color: #E8E8E8; }
    .fundo-lilas-pagina {
        background:
            radial-gradient(900px 420px at 15% 10%, rgba(255, 255, 255, .6), transparent 60%),
            linear-gradient(225deg, rgba(169, 140, 201, .18) 0%, rgba(195, 174, 223, .10) 48%, rgba(236, 227, 247, .03) 100%),
            #ffffff;
        background-attachment: fixed;
    }
    @media print {
        body { background: #fff !important; }
    }
</style>
</head>
        <?php
    }

    // -----------------------------------------------------------------
    // Painel autenticado — menu lateral + DataTable por funcionalidade
    // -----------------------------------------------------------------
    /**
     * Painel com menu lateral por perfil e, no conteúdo, a DataTable da
     * funcionalidade selecionada (colunas e ações espelhando o sistema atual).
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function painel(string $slug, array $app, array $identidade): void
    {
        $cor = self::cor((string) ($app['cor'] ?? 'emerald'));
        $funcoes = Autorizacao::funcoesVisiveis($identidade, $slug);
        $todas = $app['funcoes'] ?? [];
        $admin = Autorizacao::ehAdmin($identidade);
        $sistemas = Autorizacao::sistemasPermitidos($identidade);
        $nome = trim((string) ($identidade['nome'] ?? ''));
        $primeiroNome = explode(' ', $nome)[0] ?? $nome;
        $perfil = (string) ($identidade['perfil'] ?? 'Sem perfil');
        $urlPainel = Urls::app($slug, 'index.php');

        $chavePedida = (string) ($_GET['funcao'] ?? '');
        $funcaoSelecionada = null;
        foreach ($funcoes as $funcao) {
            if ((string) ($funcao['chave'] ?? '') === $chavePedida) {
                $funcaoSelecionada = $funcao;
                break;
            }
        }
        if ($funcaoSelecionada === null && $funcoes !== []) {
            $funcaoSelecionada = $funcoes[0];
        }

        $chave = (string) ($funcaoSelecionada['chave'] ?? '');
        $def = $chave !== '' ? Tabelas::definicao($slug, $chave) : null;
        $linhas = $chave !== '' ? Tabelas::linhas($slug, $chave, $identidade) : [];
        $colunas = is_array($def['colunas'] ?? null) ? $def['colunas'] : [];
        $acoes = $chave !== '' ? Tabelas::acoes($slug, $chave) : [];

        self::head('Painel', $app, true);
        ?>
<body class="min-h-screen fundo-lilas-pagina text-gray-900">
<div class="flex min-h-screen">

    <!-- MENU LATERAL -->
    <aside id="menu-lateral"
           class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-gray-200 menu-lateral-fundo transition-transform duration-200 lg:static lg:translate-x-0 lg:shrink-0">

        <div class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-4">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-green-700 text-white" aria-hidden="true">🌿</span>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-semibold text-gray-900"><?= self::e((string) $app['nome']) ?></p>
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500">SINPORG</p>
            </div>
            <button type="button" onclick="sinporgFecharMenu()" aria-label="Fechar menu"
                    class="ml-auto rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden">✕</button>
        </div>

        <div class="border-b border-gray-100 px-4 py-4">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-green-100 text-sm font-semibold text-green-800" aria-hidden="true">
                    <?= self::e(self::iniciais($nome)) ?>
                </span>
                <div class="min-w-0">
                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 <?= $cor['chip'] ?>">
                        <?= self::e($perfil) ?>
                    </span>
                </div>
            </div>
            <?php if ($admin): ?>
                <span class="mt-2 inline-flex rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                    Acesso admin
                </span>
            <?php endif; ?>
            <p class="mt-3 text-[11px] text-gray-500">
                <?= count($funcoes) ?> de <?= count($todas) ?> funcionalidades liberadas
            </p>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Funcionalidades do sistema">
            <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-400">Funcionalidades</p>

            <?php if ($funcoes === []): ?>
                <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Nenhuma funcionalidade liberada para este perfil.
                </p>
            <?php else: ?>
                <ul class="mt-2 space-y-1">
                    <?php $grupoMenuAtual = ''; ?>
                    <?php foreach ($funcoes as $funcaoItem): ?>
                        <?php
                        $itemChave = (string) ($funcaoItem['chave'] ?? '');
                        $ativo = $itemChave === $chave;
                        $rotaItem = (string) ($funcaoItem['rota'] ?? '');
                        $link = $rotaItem !== ''
                            ? Urls::app($slug, $rotaItem)
                            : $urlPainel . '?funcao=' . rawurlencode($itemChave);
                        ?>
                        <?php self::menuGrupo((string) ($funcaoItem['grupo'] ?? ''), $grupoMenuAtual); ?>
                        <li>
                            <a href="<?= self::e($link) ?>"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition <?= $ativo
                                   ? 'bg-green-50 font-semibold text-green-900 ring-1 ring-green-200'
                                   : 'font-medium text-gray-700 hover:bg-gray-50' ?>"
                               <?= $ativo ? 'aria-current="page"' : '' ?>>
                                <span class="text-base" aria-hidden="true"><?= self::e((string) ($funcaoItem['icone'] ?? '•')) ?></span>
                                <span class="flex-1"><?= self::e((string) ($funcaoItem['titulo'] ?? '')) ?></span>
                                <?php if (empty($funcaoItem['rota']) && !$ativo): ?>
                                    <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[8px] font-semibold uppercase tracking-wide text-gray-400">em breve</span>
                                <?php elseif ($ativo): ?>
                                    <span class="text-green-600" aria-hidden="true">•</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="mt-6 px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-400">Sistemas</p>
            <ul class="mt-2 space-y-1">
                <?php foreach (Aplicacoes::todos() as $outroSlug => $outro): ?>
                    <?php
                    $permitido = in_array($outroSlug, $sistemas, true);
                    $atual = $outroSlug === $slug;
                    ?>
                    <li>
                        <?php if ($atual): ?>
                            <span class="flex items-center gap-3 rounded-xl bg-green-50 px-3 py-2 text-sm font-medium text-green-900">
                                <span aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-green-700 ring-1 ring-green-200">atual</span>
                            </span>
                        <?php elseif ($permitido): ?>
                            <a href="<?= self::e(Urls::sistema((string) $outroSlug)) ?>"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                                <span aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                <span class="text-gray-400" aria-hidden="true">→</span>
                            </a>
                        <?php else: ?>
                            <span class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-gray-400">
                                <span class="opacity-60" aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                <span class="text-[9px] uppercase tracking-wide">sem acesso</span>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="space-y-1 border-t border-gray-100 p-3 text-sm">
            <a href="<?= self::e(Urls::raiz()) ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-gray-600 hover:bg-gray-50">
                <span aria-hidden="true">←</span> Página inicial
            </a>
            <?php if ($admin): ?>
                <a href="<?= self::e(Urls::base()) ?>/"
                   class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-green-800 hover:bg-green-50">
                    <span aria-hidden="true">🏛️</span> Sistema atual (legado)
                </a>
            <?php endif; ?>
            <a href="<?= self::e(Urls::app($slug, 'logout.php')) ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-gray-600 hover:bg-gray-50">
                <span aria-hidden="true">⏻</span> Sair
            </a>
        </div>
    </aside>

    <div id="menu-overlay" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" onclick="sinporgFecharMenu()"></div>

    <!-- CONTEÚDO -->
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-gray-200 barra-superior-fundo backdrop-blur">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <button type="button" onclick="sinporgAbrirMenu()" aria-label="Abrir menu"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-gray-600 hover:bg-gray-50 lg:hidden">☰</button>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900"><?= self::e((string) $app['nome']) ?></p>
                    <p class="text-[11px] text-gray-500">
                        <?= self::e($perfil) ?> · <?= count($funcoes) ?> funcionalidade<?= count($funcoes) === 1 ? '' : 's' ?>
                    </p>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-medium text-gray-800"><?= self::e($nome) ?></span>
                        <span class="block text-[11px] text-gray-500"><?= self::e((string) ($identidade['email'] ?? '')) ?></span>
                    </span>
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-green-100 text-sm font-semibold text-green-800" aria-hidden="true">
                        <?= self::e(self::iniciais($nome)) ?>
                    </span>
                    <a href="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
                       class="rounded-full border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
                       title="Meu perfil">👤 <span class="hidden md:inline">Meu perfil</span></a>
                    <a href="<?= self::e(Urls::app($slug, 'configuracoes.php')) ?>"
                       class="rounded-full border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
                       title="Configurações">⚙ <span class="hidden md:inline">Configurações</span></a>
                    <a href="<?= self::e(Urls::app($slug, 'logout.php')) ?>"
                       class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100">
                        Sair
                    </a>
                </div>
            </div>
        </header>

        <main class="w-full flex-1 px-4 py-6 sm:px-6 xl:px-8 2xl:px-10">
            <?php if ($funcaoSelecionada === null): ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    Seu perfil não tem funcionalidades liberadas neste sistema. Peça ao administrador para
                    atribuir um papel em <strong>usuário → papéis</strong>.
                </div>
            <?php else: ?>
                <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-4">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-2xl <?= $cor['icone'] ?>" aria-hidden="true">
                                <?= self::e((string) ($funcaoSelecionada['icone'] ?? '•')) ?>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">
                                    <?= self::e((string) ($app['curto'] ?? $app['nome'])) ?> · módulo
                                </p>
                                <h1 class="mt-1 truncate text-2xl font-bold leading-tight text-gray-900 sm:text-3xl">
                                    <?= self::e((string) ($def['titulo'] ?? $funcaoSelecionada['titulo'] ?? '')) ?>
                                </h1>
                                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-600">
                                    <?= self::e((string) ($def['descricao'] ?? $funcaoSelecionada['descricao'] ?? '')) ?>
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-gray-500">
                                    <span>Olá, <?= self::e($primeiroNome) ?>.</span>
                                    <span class="h-1 w-1 rounded-full bg-gray-300"></span>
                                    <span class="rounded-full px-2.5 py-0.5 font-semibold uppercase tracking-wide ring-1 <?= $cor['chip'] ?>">
                                        <?= self::e($perfil) ?>
                                    </span>
                                    <span class="h-1 w-1 rounded-full bg-gray-300"></span>
                                    <span>permissão <code class="rounded bg-gray-100 px-1.5 py-0.5"><?= self::e((string) ($funcaoSelecionada['permissao'] ?? '')) ?></code></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-2xl bg-gray-900 px-4 py-2 text-center font-semibold text-white shadow-sm">
                                <span class="block text-lg leading-none"><?= count($linhas) ?></span>
                                <span class="mt-0.5 block text-[9px] font-medium uppercase tracking-wide text-white/70">registros</span>
                            </span>
                            <?php if (!empty($def['toolbar'])): ?>
                                <a href="<?= self::e(Urls::app($slug, (string) $def['toolbar'])) ?>"
                                   class="rounded-full bg-green-700 px-4 py-2 text-xs font-semibold text-white hover:bg-green-800">
                                    <?= self::e((string) ($def['toolbar_rotulo'] ?? 'Abrir tela')) ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?= self::e($urlPainel . '?funcao=' . rawurlencode($chave)) ?>"
                               class="rounded-full border border-gray-300 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                ↻ Atualizar
                            </a>
                        </div>
                    </div>
                </section>

                <section class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900">Resultados</h2>
                            <p class="text-[11px] text-gray-500">Colunas e ações espelham a tela equivalente do sistema atual.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" id="btn-exportar-csv"
                                    class="rounded-full border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                ⬇ Exportar CSV
                            </button>
                            <a href="<?= self::e($urlPainel . '?funcao=' . rawurlencode($chave)) ?>"
                               class="rounded-full border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                ↻ Atualizar
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto px-2 py-2 sm:px-3">
                        <table id="tabela-funcao" class="display w-full text-sm">
                            <thead>
                                <tr>
                                    <?php foreach ($colunas as $coluna): ?>
                                        <th><?= self::e((string) ($coluna['rotulo'] ?? '')) ?></th>
                                    <?php endforeach; ?>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linhas as $linha): ?>
                                    <tr>
                                        <?php foreach ($colunas as $coluna): ?>
                                            <?= self::celula($coluna, $linha) ?>
                                        <?php endforeach; ?>
                                        <td class="whitespace-nowrap">
                                            <?php if ($acoes === []): ?>
                                                <span class="text-xs text-gray-400">—</span>
                                            <?php else: ?>
                                                <?php foreach ($acoes as $acao): ?>
                                                    <button type="button" disabled title="Fluxo em implantação"
                                                            class="mb-1 mr-1 inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-500 shadow-sm disabled:cursor-not-allowed">
                                                        <?= self::e($acao) ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-3 text-[11px] text-gray-400 sm:px-5">
                        <?php if ($linhas === []): ?>
                            Nenhum registro encontrado nesta base. O fluxo interno desta funcionalidade está em implantação.
                        <?php else: ?>
                            As ações marcadas <em>“Fluxo em implantação”</em> serão conectadas às telas internas nas próximas fatias.
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php
// O modal da ficha só deve aparecer no dashboard de entrada (login/painel)
// quando a ficha ainda é rascunho/sem ficha — nunca ao navegar por outro item
// do menu lateral. O acesso "Minha ficha" também reabre o modal na sua página.
if (in_array($slug, ['assoc', 'producao'], true)
    && $chavePedida === ''
    && !empty($_SESSION['mostrar_ficha_modal'])
    && Onboarding::precisaFicha($identidade)) {
    unset($_SESSION['mostrar_ficha_modal']);
    UiFichaModal::render($slug, $app, $identidade);
}
?>

<script src="<?= self::e(Urls::asset('vendor/jquery.min.js')) ?>"></script>
<script src="<?= self::e(Urls::asset('vendor/datatables.min.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof DataTable !== 'undefined' && document.getElementById('tabela-funcao')) {
        window.sinporgTabela = new DataTable('#tabela-funcao', {
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
            order: [],
            autoWidth: false,
            columnDefs: [{ targets: -1, orderable: false, searchable: false }],
            language: {
                emptyTable: 'Nenhum registro encontrado',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Nenhum registro',
                infoFiltered: '(filtrado de _MAX_ registros)',
                lengthMenu: '_MENU_ por página',
                loadingRecords: 'Carregando…',
                search: 'Buscar:',
                zeroRecords: 'Nenhum registro encontrado',
                paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' }
            }
        });
    }

    var exportar = document.getElementById('btn-exportar-csv');
    if (exportar && window.sinporgTabela) {
        exportar.addEventListener('click', function () {
            var tabela = window.sinporgTabela;
            var cabecalhos = Array.prototype.map.call(
                document.querySelectorAll('#tabela-funcao thead th'),
                function (th) { return th.textContent.replace(/\s+/g, ' ').trim(); }
            );
            var linhas = [cabecalhos];

            tabela.rows({ page: 'current' }).every(function () {
                var tr = this.node();
                var celulas = Array.prototype.map.call(tr.querySelectorAll('td'), function (td) {
                    return td.textContent.replace(/\s+/g, ' ').trim();
                });
                linhas.push(celulas);
            });

            var csv = linhas.map(function (linha) {
                return linha.map(function (valor) {
                    return '"' + String(valor).replace(/"/g, '""') + '"';
                }).join(';');
            }).join('\r\n');

            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'sinporg-<?= self::e($chave) ?>-<?= date('Ymd') ?>.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        });
    }
});

function sinporgAbrirMenu() {
    var menu = document.getElementById('menu-lateral');
    var overlay = document.getElementById('menu-overlay');
    if (menu) { menu.classList.remove('-translate-x-full'); }
    if (overlay) { overlay.classList.remove('hidden'); }
    document.body.classList.add('overflow-hidden');
}
function sinporgFecharMenu() {
    var menu = document.getElementById('menu-lateral');
    var overlay = document.getElementById('menu-overlay');
    if (menu) { menu.classList.add('-translate-x-full'); }
    if (overlay) { overlay.classList.add('hidden'); }
    document.body.classList.remove('overflow-hidden');
}
document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape') { sinporgFecharMenu(); }
});
document.querySelectorAll('#menu-lateral a').forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth < 1024) { sinporgFecharMenu(); }
    });
});
</script>
</body>
</html>
        <?php
    }

    /**
     * Renderiza uma célula da DataTable conforme o tipo da coluna.
     *
     * @param array<string, mixed> $coluna
     * @param array<string, mixed> $linha
     */
    private static function celula(array $coluna, array $linha): string
    {
        $campo = (string) ($coluna['campo'] ?? '');
        $tipo = (string) ($coluna['tipo'] ?? 'texto');
        $valor = $linha[$campo] ?? null;
        $texto = $valor === null || $valor === '' ? '—' : (string) $valor;
        $ordem = '';

        switch ($tipo) {
            case 'cpf':
                $digitos = preg_replace('/\D+/', '', $texto) ?? '';
                $texto = strlen($digitos) === 11 ? '***.***.***-' . substr($digitos, -2) : '—';
                break;
            case 'valor':
                $numero = (float) ($valor ?? 0);
                $ordem = number_format($numero, 2, '.', '');
                $texto = 'R$ ' . number_format($numero, 2, ',', '.');
                break;
            case 'decimal':
                $numero = (float) ($valor ?? 0);
                $ordem = number_format($numero, 4, '.', '');
                $texto = number_format($numero, 2, ',', '.');
                break;
            case 'data':
            case 'datahora':
                if ($texto !== '—') {
                    $ts = strtotime($texto);
                    if ($ts !== false) {
                        $ordem = date('Y-m-d H:i:s', $ts);
                        $texto = date($tipo === 'data' ? 'd/m/Y' : 'd/m/Y H:i', $ts);
                    }
                }
                break;
            case 'nivel':
                $ordem = (string) (int) ($valor ?? 0);
                $texto = self::rotuloNivel($valor);
                break;
            case 'tipo_associado':
                $texto = self::rotuloTipoAssociado($texto);
                break;
            case 'badge':
                return '<td data-order="' . self::e($texto) . '"><span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 '
                    . self::badgeClasse($texto) . '">' . self::e($texto) . '</span></td>';
        }

        return '<td data-order="' . self::e($ordem !== '' ? $ordem : $texto) . '">' . self::e($texto) . '</td>';
    }

    private static function rotuloNivel(mixed $nivel): string
    {
        return match ((int) ($nivel ?? 0)) {
            1 => 'Admin',
            2 => 'Fiscal/Técnico',
            3 => 'Usuário',
            default => '—',
        };
    }

    private static function rotuloTipoAssociado(string $valor): string
    {
        return match ($valor) {
            'agricultor_familiar' => 'Agricultor(a) familiar',
            'colaborador' => 'Colaborador(a)',
            'honorario' => 'Honorário(a)',
            'institucional' => 'Institucional',
            default => $valor !== '' ? $valor : '—',
        };
    }

    private static function badgeClasse(string $valor): string
    {
        $normalizado = mb_strtolower($valor);

        foreach (['ativo', 'aprovad', 'pago', 'realizada', 'conclu', 'emitid', 'conforme', 'sim'] as $positivo) {
            if (str_contains($normalizado, $positivo)) {
                return 'bg-green-50 text-green-800 ring-green-200';
            }
        }
        foreach (['pendente', 'agendad', 'análise', 'analise', 'rascunho', 'atrasad', 'em análise'] as $atencao) {
            if (str_contains($normalizado, $atencao)) {
                return 'bg-amber-50 text-amber-800 ring-amber-200';
            }
        }
        foreach (['inativ', 'recusad', 'cancelad', 'revogad', 'desligad', 'falecid', 'não', 'nao'] as $negativo) {
            if (str_contains($normalizado, $negativo)) {
                return 'bg-red-50 text-red-800 ring-red-200';
            }
        }

        return 'bg-gray-100 text-gray-700 ring-gray-200';
    }

    /**
     * Menu lateral compartilhado por TODAS as páginas internas.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function menuLateral(string $slug, array $app, array $identidade, string $ativo = ''): void
    {
        $cor = self::cor((string) ($app['cor'] ?? 'emerald'));
        $funcoes = Autorizacao::funcoesVisiveis($identidade, $slug);
        $todas = $app['funcoes'] ?? [];
        $admin = Autorizacao::ehAdmin($identidade);
        $sistemas = Autorizacao::sistemasPermitidos($identidade);
        $nome = trim((string) ($identidade['nome'] ?? ''));
        $perfil = (string) ($identidade['perfil'] ?? 'Sem perfil');
        $urlPainel = Urls::app($slug, 'index.php');
        ?>
        <aside id="menu-lateral"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-gray-200 menu-lateral-fundo transition-transform duration-200 print:hidden lg:static lg:translate-x-0 lg:shrink-0">
            <div class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-4">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-green-700 text-white" aria-hidden="true">🌿</span>
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-gray-900"><?= self::e((string) $app['nome']) ?></p>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500">SINPORG</p>
                </div>
                <button type="button" onclick="sinporgFecharMenu()" aria-label="Fechar menu"
                        class="ml-auto rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden">✕</button>
            </div>

            <div class="border-b border-gray-100 px-4 py-4">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-green-100 text-sm font-semibold text-green-800" aria-hidden="true">
                        <?= self::e(self::iniciais($nome)) ?>
                    </span>
                    <div class="min-w-0">
                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 <?= $cor['chip'] ?>">
                            <?= self::e($perfil) ?>
                        </span>
                    </div>
                </div>
                <?php if ($admin): ?>
                    <span class="mt-2 inline-flex rounded-full bg-gray-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Acesso admin</span>
                <?php endif; ?>
                <p class="mt-3 text-[11px] text-gray-500"><?= count($funcoes) ?> de <?= count($todas) ?> funcionalidades liberadas</p>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Funcionalidades do sistema">
                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-400">Funcionalidades</p>
                <ul class="mt-2 space-y-1">
                    <?php $grupoMenuAtual = ''; ?>
                    <?php foreach ($funcoes as $funcaoItem): ?>
                        <?php
                        $itemChave = (string) ($funcaoItem['chave'] ?? '');
                        $itemAtivo = $itemChave === $ativo;
                        $rotaItem = (string) ($funcaoItem['rota'] ?? '');
                        $link = $rotaItem !== '' ? Urls::app($slug, $rotaItem) : $urlPainel . '?funcao=' . rawurlencode($itemChave);
                        ?>
                        <?php self::menuGrupo((string) ($funcaoItem['grupo'] ?? ''), $grupoMenuAtual); ?>
                        <li>
                            <a href="<?= self::e($link) ?>"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition <?= $itemAtivo
                                   ? 'bg-green-50 font-semibold text-green-900 ring-1 ring-green-200'
                                   : 'font-medium text-gray-700 hover:bg-gray-50' ?>"
                               <?= $itemAtivo ? 'aria-current="page"' : '' ?>>
                                <span class="text-base" aria-hidden="true"><?= self::e((string) ($funcaoItem['icone'] ?? '•')) ?></span>
                                <span class="flex-1"><?= self::e((string) ($funcaoItem['titulo'] ?? '')) ?></span>
                                <?php if ($itemAtivo): ?>
                                    <span class="text-green-600" aria-hidden="true">•</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="mt-6 px-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-400">Sistemas</p>
                <ul class="mt-2 space-y-1">
                    <?php foreach (Aplicacoes::todos() as $outroSlug => $outro): ?>
                        <?php
                        $permitido = in_array($outroSlug, $sistemas, true);
                        $atual = $outroSlug === $slug;
                        ?>
                        <li>
                            <?php if ($atual): ?>
                                <span class="flex items-center gap-3 rounded-xl bg-green-50 px-3 py-2 text-sm font-medium text-green-900">
                                    <span aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                    <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-green-700 ring-1 ring-green-200">atual</span>
                                </span>
                            <?php elseif ($permitido): ?>
                                <a href="<?= self::e(Urls::sistema((string) $outroSlug)) ?>"
                                   class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50">
                                    <span aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                    <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                    <span class="text-gray-400" aria-hidden="true">→</span>
                                </a>
                            <?php else: ?>
                                <span class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-gray-400">
                                    <span class="opacity-60" aria-hidden="true"><?= self::e((string) ($outro['icone'] ?? '•')) ?></span>
                                    <span class="flex-1"><?= self::e((string) $outro['nome']) ?></span>
                                    <span class="text-[9px] uppercase tracking-wide">sem acesso</span>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="space-y-1 border-t border-gray-100 p-3 text-sm">
                <a href="<?= self::e(Urls::raiz()) ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-gray-600 hover:bg-gray-50"><span aria-hidden="true">←</span> Página inicial</a>
                <?php if ($admin): ?>
                    <a href="<?= self::e(Urls::base()) ?>/" class="flex items-center gap-2 rounded-xl px-3 py-2 font-medium text-green-800 hover:bg-green-50"><span aria-hidden="true">🏛️</span> Sistema atual (legado)</a>
                <?php endif; ?>
                <a href="<?= self::e(Urls::app($slug, 'logout.php')) ?>" class="flex items-center gap-2 rounded-xl px-3 py-2 text-gray-600 hover:bg-gray-50"><span aria-hidden="true">⏻</span> Sair</a>
            </div>
        </aside>
        <div id="menu-overlay" class="fixed inset-0 z-40 hidden bg-black/40 lg:hidden" onclick="sinporgFecharMenu()"></div>
        <?php
    }

    /**
     * Abre o layout interno (menu lateral + topo + main) usado por todas as telas.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function abrirLayout(
        string $slug,
        array $app,
        array $identidade,
        string $tituloPagina,
        string $ativo = '',
        bool $comTabelas = false
    ): void {
        $nome = trim((string) ($identidade['nome'] ?? ''));
        $perfil = (string) ($identidade['perfil'] ?? '');
        $funcoes = Autorizacao::funcoesVisiveis($identidade, $slug);

        self::head($tituloPagina, $app, $comTabelas);
        ?>
<body class="min-h-screen fundo-lilas-pagina text-gray-900">
<div class="flex min-h-screen">
    <?php self::menuLateral($slug, $app, $identidade, $ativo); ?>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-gray-200 barra-superior-fundo backdrop-blur print:hidden">
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <button type="button" onclick="sinporgAbrirMenu()" aria-label="Abrir menu"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-gray-600 hover:bg-gray-50 lg:hidden">☰</button>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900"><?= self::e((string) $app['nome']) ?></p>
                    <p class="text-[11px] text-gray-500"><?= self::e($perfil) ?> · <?= count($funcoes) ?> funcionalidade<?= count($funcoes) === 1 ? '' : 's' ?></p>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-medium text-gray-800"><?= self::e($nome) ?></span>
                        <span class="block text-[11px] text-gray-500"><?= self::e((string) ($identidade['email'] ?? '')) ?></span>
                    </span>
                    <a href="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
                       class="rounded-full border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                       title="Meu perfil">👤 <span class="hidden md:inline">Meu perfil</span></a>
                    <a href="<?= self::e(Urls::app($slug, 'configuracoes.php')) ?>"
                       class="rounded-full border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                       title="Configurações">⚙ <span class="hidden md:inline">Configurações</span></a>
                    <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
                       class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Painel</a>
                    <a href="<?= self::e(Urls::app($slug, 'logout.php')) ?>"
                       class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Sair</a>
                </div>
            </div>
        </header>
        <main class="w-full flex-1 px-4 py-6 sm:px-6 xl:px-8 2xl:px-10">
        <?php
    }

    /** Fecha o layout aberto por abrirLayout(). */
    public static function fecharLayout(): void
    {
        ?>
        </main>
    </div>
</div>
<script>
function sinporgAbrirMenu() {
    var menu = document.getElementById('menu-lateral');
    var overlay = document.getElementById('menu-overlay');
    if (menu) { menu.classList.remove('-translate-x-full'); }
    if (overlay) { overlay.classList.remove('hidden'); }
    document.body.classList.add('overflow-hidden');
}
function sinporgFecharMenu() {
    var menu = document.getElementById('menu-lateral');
    var overlay = document.getElementById('menu-overlay');
    if (menu) { menu.classList.add('-translate-x-full'); }
    if (overlay) { overlay.classList.add('hidden'); }
    document.body.classList.remove('overflow-hidden');
}
document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape') { sinporgFecharMenu(); }
});
document.querySelectorAll('#menu-lateral a').forEach(function (link) {
    link.addEventListener('click', function () {
        if (window.innerWidth < 1024) { sinporgFecharMenu(); }
    });
});
</script>
</body>
</html>
        <?php
    }

    // -----------------------------------------------------------------
    // Acesso negado (perfil sem permissão para este sistema)
    // -----------------------------------------------------------------
    /**
     * Acesso negado (perfil sem permissão para este sistema).
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function negado(string $slug, array $app, array $identidade): void
    {
        http_response_code(403);
        self::head('Acesso restrito', $app);
        $sistemas = Autorizacao::sistemasPermitidos($identidade);
        ?>
<body class="min-h-screen fundo-lilas-pagina text-gray-900">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-lg rounded-3xl bg-white p-8 text-center shadow-2xl ring-1 ring-black/5 sm:p-10">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-amber-100 text-3xl" aria-hidden="true">🔒</span>
        <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">403 · acesso restrito</p>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Este sistema não está no seu perfil</h1>
        <p class="mt-3 text-sm leading-relaxed text-gray-600">
            <strong><?= self::e((string) ($identidade['nome'] ?? '')) ?></strong>
            (<?= self::e((string) ($identidade['perfil'] ?? 'sem perfil')) ?>)
            não tem permissão para acessar <strong><?= self::e((string) $app['nome']) ?></strong>.
        </p>

        <?php if ($sistemas !== []): ?>
            <div class="mt-6 rounded-2xl bg-gray-50 p-4 text-left">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sistemas liberados para você</p>
                <ul class="mt-2 space-y-2 text-sm">
                    <?php foreach ($sistemas as $permitidoSlug): ?>
                        <li>
                            <a class="font-medium text-green-700 hover:text-green-900" href="<?= self::e(Urls::sistema($permitidoSlug)) ?>">
                                <?= self::e((string) (Aplicacoes::obter($permitidoSlug)['nome'] ?? $permitidoSlug)) ?> →
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="mt-6 text-xs text-gray-500">
            Precisa de acesso? Solicite ao administrador a atribuição do papel adequado.
        </p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="<?= self::e(Urls::app($slug, 'logout.php')) ?>"
               class="rounded-full bg-gray-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-gray-800">
                Entrar com outra conta
            </a>
            <a href="<?= self::e(Urls::raiz()) ?>"
               class="rounded-full border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-100">
                Página inicial
            </a>
        </div>
    </div>
</div>
</body>
</html>
        <?php
    }

    public static function erro(int $status, string $titulo, string $mensagem): void
    {
        http_response_code($status);
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= self::e($titulo) ?> · SINPORG</title>
<link rel="stylesheet" href="<?= self::e(Urls::asset('inter.css')) ?>">
<script src="<?= self::e(Urls::asset('tailwind.js')) ?>"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 p-6 font-sans">
<div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl ring-1 ring-black/5">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400"><?= $status ?></p>
    <h1 class="mt-2 text-xl font-bold text-gray-900"><?= self::e($titulo) ?></h1>
    <p class="mt-3 text-sm text-gray-600"><?= self::e($mensagem) ?></p>
    <a class="mt-6 inline-flex rounded-full bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800"
       href="<?= self::e(Urls::raiz()) ?>">Voltar ao início</a>
</div>
</body>
</html>
        <?php
    }
}
