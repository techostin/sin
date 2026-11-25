<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas "Meu perfil" e "Configurações" do usuário autenticado.
 */
final class UiConta
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $dados
     */
    public static function perfil(
        string $slug,
        array $app,
        array $identidade,
        array $dados,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Meu perfil');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Minha conta</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Meu perfil</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">
                    Atualize seus dados pessoais, contato e endereço. Os campos com <span class="text-red-600">*</span> são obrigatórios.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'configuracoes.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">⚙ Configurações</a>
                <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="post" action="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
              class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="nome" class="block text-xs font-medium text-gray-600">Nome <span class="text-red-600">*</span></label>
                    <input id="nome" name="nome" required value="<?= self::e((string) ($dados['nome'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="sobrenome" class="block text-xs font-medium text-gray-600">Sobrenome</label>
                    <input id="sobrenome" name="sobrenome" value="<?= self::e((string) ($dados['sobrenome'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="email" class="block text-xs font-medium text-gray-600">E-mail <span class="text-red-600">*</span></label>
                    <input id="email" name="email" type="email" required value="<?= self::e((string) ($dados['email'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="telefone" class="block text-xs font-medium text-gray-600">Telefone</label>
                    <input id="telefone" name="telefone" value="<?= self::e((string) ($dados['telefone'] ?? '')) ?>"
                           placeholder="(00) 00000-0000" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="cpf" class="block text-xs font-medium text-gray-600">CPF</label>
                    <input id="cpf" name="cpf" value="<?= self::e((string) ($dados['cpf'] ?? '')) ?>"
                           placeholder="Somente números" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="data_nascimento" class="block text-xs font-medium text-gray-600">Data de nascimento</label>
                    <input id="data_nascimento" name="data_nascimento" type="date" value="<?= self::e((string) ($dados['data_nascimento'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <h2 class="mt-6 text-sm font-semibold text-gray-900">Endereço</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="cep" class="block text-xs font-medium text-gray-600">CEP</label>
                    <input id="cep" name="cep" value="<?= self::e((string) ($dados['cep'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="lg:col-span-2">
                    <label for="endereco" class="block text-xs font-medium text-gray-600">Endereço</label>
                    <input id="endereco" name="endereco" value="<?= self::e((string) ($dados['endereco'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="numero" class="block text-xs font-medium text-gray-600">Número</label>
                    <input id="numero" name="numero" value="<?= self::e((string) ($dados['numero'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="complemento" class="block text-xs font-medium text-gray-600">Complemento</label>
                    <input id="complemento" name="complemento" value="<?= self::e((string) ($dados['complemento'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="bairro" class="block text-xs font-medium text-gray-600">Bairro</label>
                    <input id="bairro" name="bairro" value="<?= self::e((string) ($dados['bairro'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="cidade" class="block text-xs font-medium text-gray-600">Cidade</label>
                    <input id="cidade" name="cidade" value="<?= self::e((string) ($dados['cidade'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="estado" class="block text-xs font-medium text-gray-600">UF</label>
                    <input id="estado" name="estado" maxlength="2" value="<?= self::e((string) ($dados['estado'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <h2 class="mt-6 text-sm font-semibold text-gray-900">Vínculo institucional</h2>
            <p class="text-[11px] text-gray-500">Preencha apenas se você atua como fiscal/técnico ou organização de controle social.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="orgao" class="block text-xs font-medium text-gray-600">Órgão</label>
                    <input id="orgao" name="orgao" value="<?= self::e((string) ($dados['orgao'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="setor" class="block text-xs font-medium text-gray-600">Setor</label>
                    <input id="setor" name="setor" value="<?= self::e((string) ($dados['setor'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="especialidade" class="block text-xs font-medium text-gray-600">Especialidade</label>
                    <input id="especialidade" name="especialidade" value="<?= self::e((string) ($dados['especialidade'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-3">
                    <label for="nicho" class="block text-xs font-medium text-gray-600">Nicho / área de atuação</label>
                    <input id="nicho" name="nicho" value="<?= self::e((string) ($dados['nicho'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Salvar alterações</button>
                <a href="<?= self::e(Urls::app($slug, 'configuracoes.php')) ?>"
                   class="rounded-full border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Trocar senha e preferências</a>
            </div>
        </form>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $dados
     * @param array<string, mixed> $prefs
     */
    public static function configuracoes(
        string $slug,
        array $app,
        array $identidade,
        array $dados,
        array $prefs,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Configurações');
        $url = Urls::app($slug, 'configuracoes.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Minha conta</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Configurações</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">Preferências de uso, notificações de eventos e segurança da conta.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">👤 Meu perfil</a>
                <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <form method="post" action="<?= self::e($url) ?>" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="acao" value="preferencias">
                <h2 class="text-sm font-semibold text-gray-900">Preferências</h2>
                <p class="mt-1 text-[11px] text-gray-500">Aparência e idioma da interface.</p>

                <label for="tema" class="mt-4 block text-xs font-medium text-gray-600">Tema</label>
                <select id="tema" name="tema" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    <?php foreach (['light' => 'Claro', 'dark' => 'Escuro', 'auto' => 'Automático'] as $valor => $rotulo): ?>
                        <option value="<?= self::e($valor) ?>" <?= ($prefs['tema'] ?? 'light') === $valor ? 'selected' : '' ?>><?= self::e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="idioma" class="mt-3 block text-xs font-medium text-gray-600">Idioma</label>
                <select id="idioma" name="idioma" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    <?php foreach (['pt-br' => 'Português (Brasil)', 'en' => 'English', 'es' => 'Español'] as $valor => $rotulo): ?>
                        <option value="<?= self::e($valor) ?>" <?= ($prefs['idioma'] ?? 'pt-br') === $valor ? 'selected' : '' ?>><?= self::e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="mt-3 flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notificacoes" value="1" class="h-4 w-4 rounded border-gray-300" <?= !empty($prefs['notificacoes']) ? 'checked' : '' ?>>
                    Receber notificações do sistema
                </label>

                <button type="submit" class="mt-4 w-full rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Salvar preferências</button>
            </form>

            <form method="post" action="<?= self::e($url) ?>" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="acao" value="notificacoes">
                <h2 class="text-sm font-semibold text-gray-900">Notificações de eventos</h2>
                <p class="mt-1 text-[11px] text-gray-500">Quando e como avisar sobre eventos e prazos.</p>

                <label class="mt-4 flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notificacoes_ativas" value="1" class="h-4 w-4 rounded border-gray-300" <?= !empty($dados['notificacoes_ativas']) ? 'checked' : '' ?>>
                    Ativar notificações
                </label>

                <label for="dias_antecedencia_evento" class="mt-3 block text-xs font-medium text-gray-600">Dias de antecedência</label>
                <select id="dias_antecedencia_evento" name="dias_antecedencia_evento" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    <?php $diasAtual = (int) ($dados['dias_antecedencia_evento'] ?? 7); ?>
                    <?php foreach ([1 => '1 dia', 3 => '3 dias', 7 => '7 dias', 15 => '15 dias', 30 => '30 dias'] as $valor => $rotulo): ?>
                        <option value="<?= (int) $valor ?>" <?= $diasAtual === $valor ? 'selected' : '' ?>><?= self::e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>

                <p class="mt-4 text-[11px] font-medium uppercase tracking-wide text-gray-500">Avisar sobre</p>
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notif_eventos_hoje" value="1" class="h-4 w-4 rounded border-gray-300" <?= !empty($prefs['notif_eventos_hoje']) ? 'checked' : '' ?>> Eventos de hoje
                </label>
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notif_eventos_proximos" value="1" class="h-4 w-4 rounded border-gray-300" <?= !empty($prefs['notif_eventos_proximos']) ? 'checked' : '' ?>> Eventos próximos
                </label>
                <label class="mt-2 flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" name="notif_eventos_atrasados" value="1" class="h-4 w-4 rounded border-gray-300" <?= !empty($prefs['notif_eventos_atrasados']) ? 'checked' : '' ?>> Eventos atrasados
                </label>

                <button type="submit" class="mt-4 w-full rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Salvar notificações</button>
            </form>

            <form method="post" action="<?= self::e($url) ?>" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="acao" value="senha">
                <h2 class="text-sm font-semibold text-gray-900">Segurança</h2>
                <p class="mt-1 text-[11px] text-gray-500">Altere a senha de acesso deste sistema.</p>

                <label for="senha_atual" class="mt-4 block text-xs font-medium text-gray-600">Senha atual</label>
                <input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">

                <label for="senha_nova" class="mt-3 block text-xs font-medium text-gray-600">Nova senha</label>
                <input id="senha_nova" name="senha_nova" type="password" required minlength="6" autocomplete="new-password"
                       class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">

                <label for="senha_confirma" class="mt-3 block text-xs font-medium text-gray-600">Confirmar nova senha</label>
                <input id="senha_confirma" name="senha_confirma" type="password" required minlength="6" autocomplete="new-password"
                       class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">

                <button type="submit" class="mt-4 w-full rounded-full border border-teal-700 px-4 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50">Alterar senha</button>
            </form>
        </div>

        <p class="mt-4 text-[11px] text-gray-500">
            Conta: <strong><?= self::e((string) ($dados['email'] ?? '')) ?></strong> ·
            perfil <strong><?= self::e((string) ($identidade['perfil'] ?? '')) ?></strong>.
            Cada sistema mantém sessão própria — alterar a senha aqui não afeta as outras sessões.
        </p>
        <?php Ui::fecharLayout();
    }

    private static function alertas(?string $flash, ?string $erro): void
    {
        if ($flash !== null && $flash !== '') {
            ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div>
            <?php
        }
        if ($erro !== null && $erro !== '') {
            ?>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
            <?php
        }
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
