<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas de gestão de papéis do associado.
 */
final class UiPapeis
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $linhas
     */
    public static function listar(
        string $slug,
        array $app,
        array $identidade,
        string $busca,
        array $linhas,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Papéis e permissões', 'papeis.atribuir');
        $url = Urls::app($slug, 'papeis.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Gestão associativa</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Papéis e permissões</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">
                    Defina o papel do associado no módulo. Esta é uma função administrativa exclusiva do <strong>administrador do sistema</strong>.
                </p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
               class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="get" action="<?= self::e($url) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label for="q" class="block text-xs font-medium text-gray-600">Buscar associado</label>
                    <input id="q" name="q" value="<?= self::e($busca) ?>" placeholder="Nome ou e-mail"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Buscar</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpar</a>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Associado</th>
                            <th class="px-3 py-3">E-mail</th>
                            <th class="px-3 py-3">Vínculo</th>
                            <th class="px-3 py-3">Papéis atuais</th>
                            <th class="px-3 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum associado encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <?php $papeis = array_filter(explode(',', (string) ($linha['papeis'] ?? ''))); ?>
                            <tr class="border-b border-gray-100 align-middle hover:bg-gray-50">
                                <td class="px-3 py-3 font-medium text-gray-900">
                                    <?= self::e(trim((string) $linha['nome'] . ' ' . (string) $linha['sobrenome'])) ?>
                                </td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) $linha['email']) ?></td>
                                <td class="px-3 py-3 text-gray-600">
                                    <?= (int) ($linha['associado_id'] ?? 0) > 0 ? 'Associado vinculado' : 'Conta nível 3' ?>
                                </td>
                                <td class="px-3 py-3">
                                    <?php if ($papeis === []): ?>
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200">sem papel</span>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($papeis as $codigo): ?>
                                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-800 ring-1 ring-green-200">
                                                    <?= self::e(Aplicacoes::rotuloPapel($codigo)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="<?= self::e($url . '?usuario_id=' . (int) $linha['id']) ?>"
                                       class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-[11px] font-semibold text-teal-800 hover:bg-teal-100">Gerenciar papéis</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500">
                <?= count($linhas) ?> conta(s) · Gestão exclusiva do administrador do sistema. Papéis admin e de fiscalização são preservados.
            </div>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $usuario
     * @param array<string, array{rotulo: string, descricao: string}> $disponiveis
     * @param list<string> $selecionados
     */
    public static function editar(
        string $slug,
        array $app,
        array $identidade,
        array $usuario,
        array $disponiveis,
        array $selecionados,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Gerenciar papéis', 'papeis.atribuir');
        $url = Urls::app($slug, 'papeis.php');
        $extras = array_values(array_diff($selecionados, array_keys($disponiveis)));
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Gestão associativa · Papéis</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Gerenciar papéis</h1>
                <p class="mt-1 text-sm text-gray-600">
                    <?= self::e(trim((string) $usuario['nome'] . ' ' . (string) $usuario['sobrenome'])) ?>
                    · <?= self::e((string) $usuario['email']) ?>
                </p>
            </div>
            <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar à lista</a>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="post" action="<?= self::e($url) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="usuario_id" value="<?= (int) $usuario['id'] ?>">

            <h2 class="text-sm font-semibold text-gray-900">Papéis do módulo de associação</h2>
            <p class="mt-1 text-xs text-gray-500">Marque um ou mais papéis. A alteração vale no próximo acesso do associado.</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php foreach ($disponiveis as $codigo => $papel): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-teal-300 hover:bg-teal-50/40">
                        <input type="checkbox" name="papeis[]" value="<?= self::e($codigo) ?>" class="mt-1 h-4 w-4 rounded border-gray-300 accent-teal-700"
                            <?= in_array($codigo, $selecionados, true) ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900"><?= self::e($papel['rotulo']) ?></span>
                            <span class="block text-xs text-gray-500"><?= self::e($papel['descricao']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if ($extras !== []): ?>
                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
                    Papéis mantidos (não editáveis aqui):
                    <?php foreach ($extras as $codigo): ?>
                        <span class="ml-1 inline-flex rounded-full bg-white px-2 py-0.5 font-semibold ring-1 ring-gray-200"><?= self::e(Aplicacoes::rotuloPapel($codigo)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Salvar papéis</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>
        <?php Ui::fecharLayout();
    }

    private static function alertas(?string $flash, ?string $erro): void
    {
        if ($flash !== null && $flash !== '') {
            ?><div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div><?php
        }
        if ($erro !== null && $erro !== '') {
            ?><div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div><?php
        }
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
