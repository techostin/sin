<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas de login e de primeiro acesso.
 *
 * Segue o desenho do sistema atual: foto de fundo da lavoura, logo SINPORG,
 * abas "Entrar" / "Primeiro Acesso" / "Perfil de Acesso", etapa de confirmação
 * de vínculo (Gestor/Fiscal) e ficha de adesão para a conta de produtor.
 */
final class UiLogin
{
    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private static function fundo(): string
    {
        return Urls::base() . '/assets/img/login-bg.jpg';
    }

    private static function logo(): string
    {
        return Urls::base() . '/img/logo_Sinporg-2.png';
    }

    private static function head(string $titulo): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        $fundo = self::e(self::fundo());
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f2417">
<title><?= self::e($titulo) ?> · SINPORG</title>
<link rel="manifest" href="<?= self::e(Urls::base()) ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= self::e(Urls::base() . '/assets/img/icon-192.png') ?>">
<link rel="stylesheet" href="<?= self::e(Urls::asset('inter.css')) ?>">
<script src="<?= self::e(Urls::asset('tailwind.js')) ?>"></script>
<style>
    body { font-family: 'Inter', sans-serif; }
    .fundo-login {
        background-color: #0f2417;
        background-image:
            linear-gradient(180deg, rgba(6, 22, 14, .58), rgba(6, 22, 14, .82)),
            url('<?= $fundo ?>');
        background-size: cover;
        background-position: center 30%;
        background-repeat: no-repeat;
        background-attachment: fixed;
    }
    .aba-ativa { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12); color: #14532d; }
    .girador { display: inline-block; animation: sinporgGirar .8s linear infinite; }
    @keyframes sinporgGirar { to { transform: rotate(360deg); } }
</style>
</head>
        <?php
    }

    private static function marca(): void
    {
        ?>
<div class="mb-6 text-center">
    <img src="<?= self::e(self::logo()) ?>" alt="SINPORG"
         class="mx-auto h-16 w-auto object-contain drop-shadow-lg sm:h-20">
    <p class="mt-2 text-[10px] font-semibold uppercase tracking-[0.22em] text-white/70 sm:text-[11px]">
        Sistema Integrado da Produção Orgânica
    </p>
</div>
        <?php
    }

    /**
     * Tela de login com as MESMAS etapas do sistema atual:
     *  - aba Entrar (credenciais);
     *  - aba Primeiro Acesso (dados pessoais) → aba Perfil de Acesso (situação);
     *  - estado de sucesso "Cadastro enviado" com botão para voltar ao login;
     *  - validação em 2 passos, erros por painel e botões com estado de envio.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $estado
     */
    public static function login(string $slug, array $app, array $estado): void
    {
        $erro = $estado['erro'] ?? null;
        $sucesso = $estado['sucesso'] ?? null;
        $tab = (string) ($estado['tab'] ?? 'login');
        $identificador = (string) ($estado['identificador'] ?? '');
        $nome = (string) ($estado['nome'] ?? '');
        $sobrenome = (string) ($estado['sobrenome'] ?? '');
        $email = (string) ($estado['email'] ?? '');
        $perfilSelecionado = (string) ($estado['perfil'] ?? '');
        $acao = Urls::app($slug, 'login.php');
        $telaSucesso = $tab === 'sucesso';
        $painelCadastro = $tab === 'cadastro';
        $painelPerfil = $tab === 'perfil';
        $abaAtiva = $tab === 'login' ? 'login' : ($tab === 'cadastro' ? 'cadastro' : 'perfil');

        self::head('Entrar');
        ?>
<body class="fundo-login min-h-screen text-gray-900">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-md">
        <?php self::marca(); ?>

        <div class="relative overflow-hidden rounded-3xl bg-white/95 p-6 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:p-8">
            <div class="mb-5 flex items-center justify-between gap-2">
                <span class="rounded-full bg-green-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-green-800 ring-1 ring-green-200">
                    <?= self::e((string) $app['nome']) ?>
                </span>
                <a href="<?= self::e(Urls::raiz()) ?>" class="text-xs font-medium text-green-800 hover:text-green-950">← Início</a>
            </div>

            <?php if ($telaSucesso): ?>
                <div class="py-3 text-center">
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-green-100 text-3xl text-green-700" aria-hidden="true">✓</div>
                    <h1 class="mt-5 text-xl font-bold text-gray-900">Cadastro enviado!</h1>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600">
                        <?= self::e($sucesso ?? 'Seu cadastro foi recebido com sucesso.') ?><br>
                        <strong>Aguarde a ativação por um administrador</strong> para poder fazer login.
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Você receberá um aviso assim que sua conta for ativada.
                    </p>
                    <a href="<?= self::e($acao) ?>"
                       class="mt-6 inline-flex w-full max-w-[220px] items-center justify-center rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg hover:-translate-y-0.5">
                        Ir para Login
                    </a>
                </div>

            <?php else: ?>
                <?php if ($erro !== null && $abaAtiva === 'login'): ?>
                    <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
                <?php endif; ?>

                <div class="grid grid-cols-3 gap-1 rounded-2xl bg-gray-100 p-1" role="tablist" aria-label="Acesso">
                    <?php foreach ([
                        'login' => 'Entrar',
                        'cadastro' => 'Primeiro Acesso',
                        'perfil' => 'Perfil de Acesso',
                    ] as $chave => $rotulo): ?>
                        <button type="button" role="tab" data-aba="<?= self::e($chave) ?>"
                                aria-selected="<?= $chave === $abaAtiva ? 'true' : 'false' ?>"
                                onclick="sinporgAba('<?= self::e($chave) ?>')"
                                class="rounded-xl px-2 py-2 text-xs font-semibold text-gray-600 transition sm:text-[13px] <?= $chave === $abaAtiva ? 'aba-ativa' : '' ?>">
                            <?= self::e($rotulo) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Painel: entrar -->
                <div data-painel="login" role="tabpanel" class="<?= $abaAtiva === 'login' ? '' : 'hidden' ?> mt-5">
                    <h1 class="text-lg font-bold text-gray-900">Bem-vindo de volta</h1>
                    <p class="mt-1 text-sm text-gray-500">Entre com suas credenciais para continuar.</p>

                    <form method="post" action="<?= self::e($acao) ?>" id="form-login" class="mt-4 space-y-4">
                        <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">

                        <div>
                            <label for="identificador" class="block text-sm font-medium text-gray-700">E-mail</label>
                            <input id="identificador" name="identificador" type="text" required autofocus
                                   autocomplete="username" autocapitalize="none" spellcheck="false"
                                   placeholder="seu@email.com" value="<?= self::e($identificador) ?>"
                                   class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="senha" class="block text-sm font-medium text-gray-700">Senha</label>
                                <a href="<?= self::e(Urls::base() . '/recuperar_senha.php') ?>" class="text-xs font-medium text-green-800 hover:text-green-950">Esqueceu?</a>
                            </div>
                            <div class="relative mt-1.5">
                                <input id="senha" name="senha" type="password" required autocomplete="current-password"
                                       placeholder="Sua senha"
                                       class="w-full rounded-xl border border-gray-300 px-4 py-3 pr-12 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                                <button type="button" onclick="sinporgSenha('senha', this)" aria-label="Mostrar senha"
                                        class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-800">👁</button>
                            </div>
                        </div>

                        <button type="submit" data-carregando="Verificando…"
                                class="w-full rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5">
                            Entrar
                        </button>
                    </form>

                    <p class="mt-4 text-center text-xs text-gray-500">
                        Cada sistema tem login e sessão próprios. Entrar aqui não abre os outros.
                    </p>
                </div>

                <!-- Formulário único do primeiro acesso: dados + perfil -->
                <form method="post" action="<?= self::e($acao) ?>" id="form-cadastro" novalidate
                      class="mt-5 <?= ($painelCadastro || $painelPerfil) ? '' : 'hidden' ?>">
                    <input type="hidden" name="acao" value="pre_cadastro">
                    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">

                    <!-- Passo 1: dados pessoais -->
                    <div data-painel="cadastro" role="tabpanel" class="<?= $painelCadastro ? '' : 'hidden' ?>">
                        <h1 class="text-lg font-bold text-gray-900">Criar minha conta</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Preencha seus dados abaixo e continue para escolher o <strong>Perfil de Acesso</strong>.
                        </p>

                        <div id="cadastro-erro" role="alert" class="mt-4 <?= ($painelCadastro && $erro !== null) ? '' : 'hidden' ?> rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <?= $painelCadastro && $erro !== null ? self::e($erro) : '' ?>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label for="c-nome" class="block text-sm font-medium text-gray-700">Nome <span class="text-red-600">*</span></label>
                                <input id="c-nome" name="nome" type="text" required autocomplete="given-name"
                                       placeholder="Seu nome" value="<?= self::e($nome) ?>"
                                       class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label for="c-sobrenome" class="block text-sm font-medium text-gray-700">Sobrenome <span class="text-red-600">*</span></label>
                                <input id="c-sobrenome" name="sobrenome" type="text" required autocomplete="family-name"
                                       placeholder="Sobrenome" value="<?= self::e($sobrenome) ?>"
                                       class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="c-email" class="block text-sm font-medium text-gray-700">E-mail <span class="text-red-600">*</span></label>
                            <input id="c-email" name="email" type="email" required autocomplete="email" inputmode="email"
                                   autocapitalize="none" placeholder="seu@email.com" value="<?= self::e($email) ?>"
                                   class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="mt-3">
                            <label for="c-senha" class="block text-sm font-medium text-gray-700">Senha <span class="text-red-600">*</span></label>
                            <div class="relative mt-1.5">
                                <input id="c-senha" name="senha" type="password" required minlength="6"
                                       autocomplete="new-password" placeholder="Mínimo 6 caracteres"
                                       class="w-full rounded-xl border border-gray-300 px-3 py-2.5 pr-11 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                                <button type="button" onclick="sinporgSenha('c-senha', this)" aria-label="Mostrar senha"
                                        class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-800">👁</button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="c-confirma" class="block text-sm font-medium text-gray-700">Confirmar senha <span class="text-red-600">*</span></label>
                            <input id="c-confirma" name="confirma_senha" type="password" required minlength="6"
                                   autocomplete="new-password" placeholder="Repita a senha"
                                   class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                        </div>

                        <button type="button" id="btn-cadastrar-proximo" onclick="sinporgProximoPasso()"
                                class="mt-5 w-full rounded-full bg-green-700 px-6 py-3 text-sm font-semibold text-white hover:bg-green-800">
                            Continuar
                        </button>
                    </div>

                    <!-- Passo 2: perfil de acesso (situação) -->
                    <div data-painel="perfil" role="tabpanel" class="<?= $painelPerfil ? '' : 'hidden' ?>">
                        <h1 class="text-lg font-bold text-gray-900">Perfil de Acesso</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Conforme o perfil escolhido, os módulos do sistema serão disponibilizados para você.
                        </p>

                        <div id="perfil-erro" role="alert" class="mt-4 <?= ($painelPerfil && $erro !== null) ? '' : 'hidden' ?> rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <?= $painelPerfil && $erro !== null ? self::e($erro) : 'Selecione um perfil de acesso.' ?>
                        </div>

                        <div class="mt-4 space-y-2">
                            <?php foreach (Cadastro::SITUACOES as $chave => $situacao): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-200 p-3 transition hover:border-green-400 hover:bg-green-50/40">
                                    <input type="radio" name="perfil" value="<?= self::e($chave) ?>" class="mt-1 h-4 w-4 accent-green-700"
                                        <?= $perfilSelecionado === $chave ? 'checked' : '' ?>>
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-900"><?= self::e($situacao['icone'] . ' ' . $situacao['rotulo']) ?></span>
                                        <span class="block text-xs text-gray-500"><?= self::e($situacao['descricao']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <p class="mt-3 flex items-start gap-2 text-xs text-gray-500">
                            <span aria-hidden="true">ℹ️</span>
                            <span>Fiscais e técnicos são <strong>aprovados pela direção</strong> antes de acessarem o sistema.</span>
                        </p>

                        <div class="mt-5 flex gap-2">
                            <button type="button" onclick="sinporgAba('cadastro')"
                                    class="rounded-full border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                                Voltar
                            </button>
                            <button type="submit" data-carregando="Enviando…"
                                    class="flex-1 rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg hover:-translate-y-0.5">
                                Solicitar Acesso
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <p class="mt-4 text-center text-xs text-white/70">
            SINPORG — Agricultura Orgânica<br>
            © <?= date('Y') ?> · Todos os direitos reservados.
        </p>
    </main>
</div>

<script>
function sinporgAba(aba) {
    document.querySelectorAll('[data-painel]').forEach(function (p) {
        var alvo = p.getAttribute('data-painel');
        p.classList.toggle('hidden', alvo !== aba || (alvo !== 'login' && aba === 'login'));
    });
    document.querySelectorAll('[data-aba]').forEach(function (b) {
        var ativo = b.getAttribute('data-aba') === aba;
        b.classList.toggle('aba-ativa', ativo);
        b.setAttribute('aria-selected', ativo ? 'true' : 'false');
    });
    var formCad = document.getElementById('form-cadastro');
    if (formCad) { formCad.classList.toggle('hidden', aba !== 'cadastro' && aba !== 'perfil'); }
    var painelLogin = document.querySelector('[data-painel="login"]');
    if (painelLogin) { painelLogin.classList.toggle('hidden', aba !== 'login'); }
}

function sinporgSenha(id, botao) {
    var campo = document.getElementById(id);
    if (!campo) { return; }
    var visivel = campo.type === 'password';
    campo.type = visivel ? 'text' : 'password';
    if (botao) { botao.setAttribute('aria-label', visivel ? 'Ocultar senha' : 'Mostrar senha'); }
}

function sinporgMostrarErro(id, mensagem) {
    var caixa = document.getElementById(id);
    if (!caixa) { return; }
    caixa.textContent = mensagem;
    caixa.classList.remove('hidden');
}

function sinporgEsconderErro(id) {
    var caixa = document.getElementById(id);
    if (caixa) { caixa.classList.add('hidden'); }
}

function sinporgValidarDados() {
    var nome = document.getElementById('c-nome').value.trim();
    var sobrenome = document.getElementById('c-sobrenome').value.trim();
    var email = document.getElementById('c-email').value.trim();
    var senha = document.getElementById('c-senha').value;
    var confirma = document.getElementById('c-confirma').value;

    if (!nome || !sobrenome || !email || !senha || !confirma) {
        sinporgMostrarErro('cadastro-erro', 'Preencha todos os campos obrigatórios.');
        return false;
    }
    if (senha.length < 6) {
        sinporgMostrarErro('cadastro-erro', 'A senha deve ter pelo menos 6 caracteres.');
        return false;
    }
    if (senha !== confirma) {
        sinporgMostrarErro('cadastro-erro', 'As senhas não coincidem.');
        return false;
    }
    return true;
}

function sinporgProximoPasso() {
    sinporgEsconderErro('cadastro-erro');
    if (sinporgValidarDados()) {
        sinporgAba('perfil');
    }
}

function sinporgCarregar(botao) {
    if (!botao || botao.dataset.carregandoJa === '1') { return; }
    botao.dataset.carregandoJa = '1';
    botao.dataset.rotuloOriginal = botao.innerHTML;
    botao.disabled = true;
    botao.classList.add('opacity-70');
    botao.innerHTML = '<span class="girador">⟳</span> ' + (botao.getAttribute('data-carregando') || 'Enviando…');
}

document.addEventListener('DOMContentLoaded', function () {
    var formLogin = document.getElementById('form-login');
    if (formLogin) {
        formLogin.addEventListener('submit', function () {
            sinporgCarregar(formLogin.querySelector('button[type="submit"]'));
        });
    }

    var formCadastro = document.getElementById('form-cadastro');
    if (formCadastro) {
        formCadastro.addEventListener('submit', function (evento) {
            sinporgEsconderErro('cadastro-erro');
            sinporgEsconderErro('perfil-erro');

            if (!sinporgValidarDados()) {
                evento.preventDefault();
                sinporgAba('cadastro');
                return;
            }

            var perfil = formCadastro.querySelector('input[name="perfil"]:checked');
            if (!perfil) {
                evento.preventDefault();
                sinporgMostrarErro('perfil-erro', 'Selecione um perfil de acesso.');
                sinporgAba('perfil');
                return;
            }

            sinporgCarregar(formCadastro.querySelector('button[type="submit"]'));
        });
    }

    document.querySelectorAll('[data-painel="perfil"] input[name="perfil"]').forEach(function (radio) {
        radio.addEventListener('change', function () { sinporgEsconderErro('perfil-erro'); });
    });

    var painelPerfil = document.querySelector('[data-painel="perfil"]');
    if (painelPerfil && !painelPerfil.classList.contains('hidden')) {
        // mantém o passo 2 aberto após erro de servidor
        sinporgAba('perfil');
    }
});
</script>
</body>
</html>
        <?php
    }

    // -----------------------------------------------------------------
    // Etapa 2 do login: confirmação de vínculo (Fiscal/Técnico, nível 2)
    // -----------------------------------------------------------------
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function vinculo(string $slug, array $app, array $identidade, ?string $erro): void
    {
        $acao = Urls::app($slug, 'login.php');
        self::head('Confirmar vínculo');
        ?>
<body class="fundo-login min-h-screen text-gray-900">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-md">
        <?php self::marca(); ?>

        <div class="rounded-3xl bg-white/95 p-6 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:p-8">
            <div class="mb-4 flex items-center gap-2" aria-hidden="true">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span class="h-2 w-8 rounded-full bg-gray-200"></span>
            </div>

            <h1 class="text-lg font-bold text-gray-900">Confirmar vínculo</h1>
            <p class="mt-1 text-sm text-gray-500">Informe seus dados profissionais para acessar.</p>

            <div class="mt-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-900 ring-1 ring-green-200">
                <strong><?= self::e((string) ($identidade['nome'] ?? '')) ?></strong>,
                confirme seu vínculo institucional.
            </div>

            <?php if ($erro !== null): ?>
                <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= self::e($acao) ?>" class="mt-4 space-y-4">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="etapa" value="vinculo">

                <div>
                    <label for="orgao" class="block text-sm font-medium text-gray-700">Órgão *</label>
                    <input id="orgao" name="orgao" type="text" required autofocus autocomplete="organization"
                           placeholder="Ex: MAPA, EMATER, Prefeitura..."
                           value="<?= self::e((string) ($identidade['orgao'] ?? '')) ?>"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label for="especialidade" class="block text-sm font-medium text-gray-700">Cargo / Especialidade *</label>
                    <input id="especialidade" name="especialidade" type="text" required autocomplete="organization-title"
                           placeholder="Ex: Fiscal, Téc. Agrícola, Eng. Agrônomo..."
                           value="<?= self::e((string) ($identidade['especialidade'] ?? '')) ?>"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label for="setor" class="block text-sm font-medium text-gray-700">Setor *</label>
                    <input id="setor" name="setor" type="text" required
                           placeholder="Ex: Defesa Sanitária, Certificação..."
                           value="<?= self::e((string) ($identidade['setor'] ?? '')) ?>"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <button type="submit" data-carregando="Aguarde…"
                        class="w-full rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5">
                    Acessar sistema
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-gray-500">
                <a href="<?= self::e($acao . '?cancelar=1') ?>" class="font-medium text-green-800 hover:text-green-950">Cancelar e voltar</a>
            </p>
        </div>

        <p class="mt-4 text-center text-xs text-white/70">SINPORG · Agricultura orgânica e familiar</p>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    if (!form) { return; }
    form.addEventListener('submit', function () {
        var botao = form.querySelector('button[type="submit"]');
        if (!botao || botao.disabled) { return; }
        botao.disabled = true;
        botao.classList.add('opacity-70');
        botao.innerHTML = '<span class="girador">⟳</span> ' + (botao.getAttribute('data-carregando') || 'Aguarde…');
    });
});
</script>
</body>
</html>
        <?php
    }

    // -----------------------------------------------------------------
    // Primeiro acesso: completar dados essenciais (telefone/CPF)
    // -----------------------------------------------------------------
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function perfil(string $slug, array $app, array $identidade, ?string $erro): void
    {
        $acao = Urls::app($slug, 'login.php');
        self::head('Completar perfil');
        ?>
<body class="fundo-login min-h-screen text-gray-900">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-md">
        <?php self::marca(); ?>

        <div class="rounded-3xl bg-white/95 p-6 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:p-8">
            <div class="mb-4 flex items-center gap-2" aria-hidden="true">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span class="h-2 w-8 rounded-full bg-emerald-500"></span>
                <span class="h-2 w-2 rounded-full bg-gray-200"></span>
            </div>

            <h1 class="text-lg font-bold text-gray-900">Complete seu perfil</h1>
            <p class="mt-1 text-sm text-gray-500">Precisamos de um telefone e do CPF para continuar.</p>

            <?php if ($erro !== null): ?>
                <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= self::e($acao) ?>" class="mt-4 space-y-4">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="etapa" value="perfil">

                <div>
                    <label for="telefone" class="block text-sm font-medium text-gray-700">Telefone / WhatsApp *</label>
                    <input id="telefone" name="telefone" type="tel" required autofocus inputmode="tel" autocomplete="tel"
                           value="<?= self::e((string) ($identidade['telefone'] ?? '')) ?>"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label for="cpf" class="block text-sm font-medium text-gray-700">CPF *</label>
                    <input id="cpf" name="cpf" type="text" required inputmode="numeric" autocomplete="off"
                           placeholder="000.000.000-00"
                           value="<?= self::e((string) ($identidade['cpf'] ?? '')) ?>"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">
                </div>

                <button type="submit" data-carregando="Aguarde…"
                        class="w-full rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5">
                    Salvar e continuar
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-xs text-white/70">SINPORG · Agricultura orgânica e familiar</p>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    if (!form) { return; }
    form.addEventListener('submit', function () {
        var botao = form.querySelector('button[type="submit"]');
        if (!botao || botao.disabled) { return; }
        botao.disabled = true;
        botao.classList.add('opacity-70');
        botao.innerHTML = '<span class="girador">⟳</span> ' + (botao.getAttribute('data-carregando') || 'Aguarde…');
    });
});
</script>
</body>
</html>
        <?php
    }

    // -----------------------------------------------------------------
    // Ficha de adesão (primeiro acesso do produtor)
    // -----------------------------------------------------------------

    /**
     * Reaproveita a classe legada FichaAdesao: mesma tabela, mesmas regras de
     * validação e o mesmo fluxo de aprovação da Diretoria.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array{status: string, ficha: array<string, mixed>|null, produtos: list<array<string, mixed>>} $status
     */
    public static function ficha(string $slug, array $app, array $identidade, array $status, ?string $erro, bool $enviada = false): void
    {
        $ficha = $status['ficha'] ?? [];
        $produtos = $status['produtos'] ?? [];
        $situacao = (string) ($status['status'] ?? 'sem_ficha');

        $rotulos = [
            'sem_ficha' => ['Sem ficha enviada', 'bg-gray-100 text-gray-700 ring-gray-200'],
            'rascunho' => ['Rascunho', 'bg-gray-100 text-gray-700 ring-gray-200'],
            'pendente' => ['Em análise', 'bg-amber-50 text-amber-800 ring-amber-200'],
            'aprovada' => ['Aprovada', 'bg-green-50 text-green-800 ring-green-200'],
            'recusada' => ['Recusada', 'bg-red-50 text-red-800 ring-red-200'],
        ];
        [$rotulo, $classes] = $rotulos[$situacao] ?? $rotulos['sem_ficha'];

        Ui::abrirLayout($slug, $app, $identidade, 'Ficha de adesão');
        ?>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ficha de adesão ao quadro associativo</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">
                Sua conta de acesso ainda não tem cadastro de associado. Preencha a ficha abaixo —
                ela será analisada pela Diretoria/Presidência e o cadastro é criado na aprovação.
            </p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= $classes ?>"><?= self::e($rotulo) ?></span>
    </div>

    <?php if ($enviada): ?>
        <div role="status" class="mt-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
            Ficha enviada com sucesso! Agora ela aguarda a análise da Diretoria/Presidência.
        </div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div role="alert" class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
    <?php endif; ?>

    <?php if ($situacao === 'aprovada'): ?>
        <div class="mt-6 rounded-3xl border border-green-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-green-900">Ficha aprovada 🎉</h2>
            <p class="mt-2 text-sm text-gray-600">
                Seu cadastro de associado foi criado e vinculado a esta conta. Você já pode acessar o sistema.
            </p>
            <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
               class="mt-4 inline-flex rounded-full bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
                Ir para o painel
            </a>
        </div>
    <?php else: ?>
        <?php if ($situacao === 'pendente'): ?>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Sua ficha está em análise. Se precisar corrigir algo, edite e reenvie abaixo.
            </div>
        <?php elseif ($situacao === 'recusada'): ?>
            <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <strong>Ficha recusada.</strong>
                <?php if (!empty($ficha['parecer_diretoria'])): ?>
                    Parecer: <?= self::e((string) $ficha['parecer_diretoria']) ?>
                <?php endif; ?>
                Corrija as informações e reenvie.
            </div>
        <?php endif; ?>

        <?php self::formFicha($ficha, $produtos, false, self::tipoPadraoFicha($identidade)); ?>
    <?php endif; ?>
        <?php Ui::fecharLayout(); ?>
        <?php
    }

    /**
     * Tipo de associado sugerido para a ficha conforme o cadastro inicial.
     *
     * @param array<string, mixed> $identidade
     */
    public static function tipoPadraoFicha(array $identidade): string
    {
        $perfil = strtolower(trim((string) ($identidade['perfil_solicitado'] ?? '')));
        $tipo = strtolower(trim((string) ($identidade['tipo_usuario'] ?? '')));

        if ($perfil === 'consumidor' || $tipo === 'consumidor') {
            return 'consumidor';
        }

        if (in_array($perfil, ['agroindustria', 'vegetal_agro', 'animal_agro', 'mista'], true)
            || in_array($tipo, ['produtor_agroindustria', 'agricultor_agroindustria'], true)) {
            return 'produtor_agroindustria';
        }

        return 'agricultor_familiar';
    }

    /**
     * @param array<string, mixed> $ficha
     * @param list<array<string, mixed>> $produtos
     */
    public static function formFicha(array $ficha, array $produtos, bool $modal = false, string $tipoPadrao = 'agricultor_familiar'): void
    {
        $v = static fn(string $campo): string => (string) ($ficha[$campo] ?? '');
        $lista = static function (string $campo) use ($ficha): array {
            $valor = (string) ($ficha[$campo] ?? '');
            return $valor === '' ? [] : array_map('trim', explode(',', $valor));
        };

        $sexo = $v('sexo_genero') !== '' ? $v('sexo_genero') : '';
        $nacionalidade = $v('nacionalidade') !== '' ? $v('nacionalidade') : 'Brasileira';
        ?>
<form method="post" action="" class="mt-6 space-y-6">
    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
    <input type="hidden" name="acao" value="ficha">

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">1. Identificação</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= self::selectCampo('tipo_associado', 'Tipo de associado *', \FichaAdesao::TIPOS_ASSOCIADO, $v('tipo_associado') ?: $tipoPadrao, true) ?>
            <?= self::campo('nome_completo', 'Nome completo *', $v('nome_completo'), 'text', 'required autocomplete="name"') ?>
            <?= self::campo('nome_social', 'Nome social', $v('nome_social')) ?>
            <?= self::campo('data_nascimento', 'Data de nascimento *', $v('data_nascimento'), 'date', 'required') ?>
            <?= self::campo('cpf', 'CPF *', $v('cpf'), 'text', 'required inputmode="numeric" placeholder="000.000.000-00"') ?>
            <?= self::campo('rg_orgao_emissor', 'RG / órgão emissor', $v('rg_orgao_emissor')) ?>
            <?= self::selectCampo('sexo_genero', 'Sexo / gênero', ['' => 'Não informar', 'Feminino' => 'Feminino', 'Masculino' => 'Masculino', 'Outro' => 'Outro'], $sexo) ?>
            <?= self::campo('nacionalidade', 'Nacionalidade', $nacionalidade) ?>
            <?= self::selectCampo('estado_civil', 'Estado civil', ['' => 'Não informar', 'Solteiro(a)' => 'Solteiro(a)', 'Casado(a)' => 'Casado(a)', 'União estável' => 'União estável', 'Divorciado(a)' => 'Divorciado(a)', 'Viúvo(a)' => 'Viúvo(a)'], $v('estado_civil')) ?>
            <?= self::selectCampo('escolaridade', 'Escolaridade', ['' => 'Não informar', 'Fundamental incompleto' => 'Fundamental incompleto', 'Fundamental completo' => 'Fundamental completo', 'Médio incompleto' => 'Médio incompleto', 'Médio completo' => 'Médio completo', 'Técnico' => 'Técnico', 'Superior incompleto' => 'Superior incompleto', 'Superior completo' => 'Superior completo', 'Pós-graduação' => 'Pós-graduação'], $v('escolaridade')) ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">2. Contato</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= self::campo('telefone_whatsapp', 'Telefone / WhatsApp *', $v('telefone_whatsapp'), 'tel', 'required inputmode="tel"') ?>
            <?= self::campo('telefone_alternativo', 'Telefone alternativo', $v('telefone_alternativo'), 'tel') ?>
            <?= self::campo('email', 'E-mail', $v('email') ?: (string) ($_SESSION['identidade']['email'] ?? ''), 'email') ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">3. Endereço</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= self::campo('logradouro', 'Logradouro *', $v('logradouro'), 'text', 'required') ?>
            <?= self::campo('numero', 'Número', $v('numero')) ?>
            <?= self::campo('complemento', 'Complemento', $v('complemento')) ?>
            <?= self::campo('bairro', 'Bairro', $v('bairro')) ?>
            <?= self::campo('municipio', 'Município *', $v('municipio'), 'text', 'required') ?>
            <?= self::campo('estado', 'UF *', $v('estado'), 'text', 'required maxlength="2" placeholder="RS"') ?>
            <?= self::campo('cep', 'CEP', $v('cep'), 'text', 'inputmode="numeric" placeholder="00000-000"') ?>
            <?= self::selectCampo('zona', 'Zona', ['' => 'Selecione', ...\FichaAdesao::ZONAS], $v('zona')) ?>
            <?= self::campo('latitude_longitude', 'Latitude / longitude', $v('latitude_longitude')) ?>
            <?= self::campo('georreferenciamento_sncr', 'Georreferenciamento (SNCR)', $v('georreferenciamento_sncr')) ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">4. Propriedade e produção</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= self::campo('nome_propriedade', 'Nome da propriedade *', $v('nome_propriedade'), 'text', 'required') ?>
            <?= self::campo('nirf_itr', 'NIRF / ITR', $v('nirf_itr')) ?>
            <?= self::campo('area_total_ha', 'Área total (ha)', $v('area_total_ha'), 'number', 'step="0.01" min="0"') ?>
            <?= self::campo('area_cultivada_ha', 'Área cultivada (ha)', $v('area_cultivada_ha'), 'number', 'step="0.01" min="0"') ?>
            <?= self::campo('area_preservacao_ha', 'Área de preservação (ha)', $v('area_preservacao_ha'), 'number', 'step="0.01" min="0"') ?>
            <?= self::selectCampo('situacao_fundiaria', 'Situação fundiária', ['' => 'Selecione', ...\FichaAdesao::SITUACOES_FUNDIARIAS], $v('situacao_fundiaria')) ?>
            <?= self::campo('dap_caf', 'DAP / CAF', $v('dap_caf')) ?>
            <?= self::campo('pessoas_familia', 'Pessoas na família', $v('pessoas_familia'), 'number', 'min="0"') ?>
            <?= self::campo('pessoas_trabalham', 'Pessoas que trabalham', $v('pessoas_trabalham'), 'number', 'min="0"') ?>
            <?= self::campo('renda_mensal_estimada', 'Renda mensal estimada (R$)', $v('renda_mensal_estimada'), 'number', 'step="0.01" min="0"') ?>
            <?= self::campo('tempo_producao_agroecologica', 'Tempo de produção agroecológica', $v('tempo_producao_agroecologica')) ?>
            <?= self::campo('certificacao_organica', 'Certificação orgânica (qual)', $v('certificacao_organica')) ?>
            <?= self::selectCampo('tipo_certificacao', 'Tipo de certificação', ['' => 'Selecione', ...\FichaAdesao::TIPOS_CERTIFICACAO], $v('tipo_certificacao')) ?>
            <?= self::campo('outra_organizacao', 'Outra organização', $v('outra_organizacao')) ?>
        </div>

        <div class="mt-5">
            <p class="text-sm font-medium text-gray-700">Sistemas de produção</p>
            <?= self::checkboxes('sistemas_producao', \FichaAdesao::SISTEMAS_PRODUCAO, $lista('sistemas_producao')) ?>
        </div>
        <div class="mt-4">
            <p class="text-sm font-medium text-gray-700">Canais de comercialização</p>
            <?= self::checkboxes('canais_comercializacao', \FichaAdesao::CANAIS_COMERCIALIZACAO, $lista('canais_comercializacao')) ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">5. Dados bancários</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= self::campo('banco', 'Banco', $v('banco')) ?>
            <?= self::campo('agencia', 'Agência', $v('agencia')) ?>
            <?= self::campo('conta', 'Conta', $v('conta')) ?>
            <?= self::campo('chave_pix', 'Chave PIX', $v('chave_pix')) ?>
            <?= self::campo('titular_conta', 'Titular da conta', $v('titular_conta')) ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">6. Produtos e culturas (até 5)</h2>
        <div class="mt-4 space-y-4">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <?php $p = $produtos[$i] ?? []; ?>
                <div class="grid gap-3 rounded-2xl bg-gray-50 p-3 sm:grid-cols-5">
                    <?= self::campo('produtos[' . $i . '][produto_cultura]', 'Produto / cultura', (string) ($p['produto_cultura'] ?? '')) ?>
                    <?= self::campo('produtos[' . $i . '][variedade_especie]', 'Variedade / espécie', (string) ($p['variedade_especie'] ?? '')) ?>
                    <?= self::campo('produtos[' . $i . '][producao_estimada]', 'Produção estimada', (string) ($p['producao_estimada'] ?? '')) ?>
                    <?= self::campo('produtos[' . $i . '][unidade]', 'Unidade', (string) ($p['unidade'] ?? ''), 'text', 'placeholder="kg, un., cx"') ?>
                    <?= self::campo('produtos[' . $i . '][destino_principal]', 'Destino principal', (string) ($p['destino_principal'] ?? '')) ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-semibold text-gray-900">7. Observações e aceite</h2>
        <div class="mt-4">
            <label for="f-observacoes" class="block text-sm font-medium text-gray-700">Observações</label>
            <textarea id="f-observacoes" name="observacoes" rows="3"
                      class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500"><?= self::e($v('observacoes')) ?></textarea>
        </div>
        <label class="mt-4 flex items-start gap-3 rounded-2xl bg-green-50/60 p-3 text-sm text-gray-800 ring-1 ring-green-100">
            <input type="checkbox" name="aceite_termos" value="1" required class="mt-1 h-4 w-4 accent-green-700" <?= !empty($ficha['aceite_termos']) ? 'checked' : '' ?>>
            <span>Declaro que as informações são verdadeiras e aceito os termos de adesão ao quadro associativo.</span>
        </label>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <?php if ($modal): ?>
            <button type="button" onclick="sinporgFecharFichaModal()"
                    class="text-sm font-medium text-gray-500 hover:text-gray-800">
                Fechar
            </button>
        <?php else: ?>
            <a href="<?= self::e(Urls::app((string) ($_SESSION['plataforma_app'] ?? 'assoc'), 'index.php')) ?>"
               class="text-sm font-medium text-gray-500 hover:text-gray-800">Fechar</a>
        <?php endif; ?>
        <div class="flex flex-wrap gap-2">
            <button type="submit" name="modo" value="rascunho" formnovalidate
                    class="rounded-full border border-green-700 bg-white px-5 py-3 text-sm font-semibold text-green-800 hover:bg-green-50">
                Salvar rascunho
            </button>
            <button type="submit" name="modo" value="submeter"
                    class="rounded-full bg-gradient-to-r from-green-700 to-emerald-500 px-8 py-3.5 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5">
                Enviar ficha para análise
            </button>
        </div>
    </div>
</form>
        <?php
    }

    private static function campo(string $nome, string $label, string $valor, string $tipo = 'text', string $extra = ''): string
    {
        $id = 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $nome);
        return '<div><label for="' . self::e($id) . '" class="block text-xs font-medium text-gray-600">' . $label . '</label>'
            . '<input id="' . self::e($id) . '" name="' . self::e($nome) . '" type="' . self::e($tipo) . '" value="' . self::e($valor) . '"'
            . ($extra !== '' ? ' ' . $extra : '')
            . ' class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500"></div>';
    }

    /** @param array<string, string> $opcoes */
    private static function selectCampo(string $nome, string $label, array $opcoes, string $valor, bool $obrigatorio = false): string
    {
        $id = 'f-' . $nome;
        $html = '<div><label for="' . self::e($id) . '" class="block text-xs font-medium text-gray-600">' . $label . '</label>'
            . '<select id="' . self::e($id) . '" name="' . self::e($nome) . '"' . ($obrigatorio ? ' required' : '')
            . ' class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500">';
        foreach ($opcoes as $chave => $rotulo) {
            $html .= '<option value="' . self::e((string) $chave) . '"' . ((string) $chave === $valor ? ' selected' : '') . '>'
                . self::e((string) $rotulo) . '</option>';
        }
        return $html . '</select></div>';
    }

    /**
     * @param array<string, string> $opcoes
     * @param list<string> $selecionados
     */
    private static function checkboxes(string $nome, array $opcoes, array $selecionados): string
    {
        $html = '<div class="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-3">';
        foreach ($opcoes as $chave => $rotulo) {
            $id = 'f-' . $nome . '-' . $chave;
            $html .= '<label class="flex items-center gap-2 text-xs text-gray-700" for="' . self::e($id) . '">'
                . '<input type="checkbox" id="' . self::e($id) . '" name="' . self::e($nome) . '[]" value="' . self::e((string) $chave) . '"'
                . (in_array((string) $chave, $selecionados, true) ? ' checked' : '')
                . ' class="h-3.5 w-3.5 accent-green-700">' . self::e((string) $rotulo) . '</label>';
        }
        return $html . '</div>';
    }

    // -----------------------------------------------------------------
    // Conta de pré-cadastro aguardando liberação pela diretoria
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function aguardando(string $slug, array $app, array $identidade): void
    {
        $perfil = (string) ($identidade['perfil_solicitado'] ?? '');
        $rotuloPerfil = $perfil !== '' ? (Cadastro::SITUACOES[$perfil]['rotulo'] ?? $perfil) : 'a confirmar';

        self::head('Aguardando liberação');
        ?>
<body class="fundo-login min-h-screen text-gray-900">
<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
    <main class="w-full max-w-md">
        <?php self::marca(); ?>

        <div class="rounded-3xl bg-white/95 p-6 text-center shadow-2xl ring-1 ring-black/5 backdrop-blur sm:p-8">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-amber-100 text-3xl" aria-hidden="true">⏳</span>
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Solicitação recebida</p>
            <h1 class="mt-2 text-xl font-bold text-gray-900">Aguardando liberação</h1>

            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                <strong><?= self::e((string) ($identidade['nome'] ?? '')) ?></strong>, seu cadastro foi
                enviado e está aguardando a liberação pela direção da associação
                (diretor, presidente ou outro responsável).
            </p>

            <div class="mt-4 rounded-2xl bg-gray-50 p-4 text-left text-xs text-gray-600">
                <p><span class="font-semibold text-gray-800">E-mail:</span> <?= self::e((string) ($identidade['email'] ?? '')) ?></p>
                <p class="mt-1"><span class="font-semibold text-gray-800">Situação solicitada:</span> <?= self::e($rotuloPerfil) ?></p>
            </div>

            <p class="mt-4 text-xs leading-relaxed text-gray-500">
                Assim que o acesso for liberado, no primeiro login você preencherá a
                <strong>ficha de adesão ao quadro associativo</strong> (mesma ficha do sistema atual).
            </p>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="<?= self::e(Urls::app($slug, 'login.php')) ?>"
                   class="rounded-full bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">
                    Voltar ao login
                </a>
                <a href="<?= self::e(Urls::raiz()) ?>"
                   class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                    Página inicial
                </a>
            </div>
        </div>

        <p class="mt-4 text-center text-xs text-white/70">
            SINPORG · Agricultura orgânica e familiar ·
            <a href="<?= self::e(Urls::base() . '/recuperar_senha.php') ?>" class="underline hover:text-white">recuperar senha</a>
        </p>
    </main>
</div>
</body>
</html>
        <?php
    }
}
