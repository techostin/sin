<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas de Certificadoras (lista, formulário e membros).
 */
final class UiCertificadoras
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $filtros
     * @param list<array<string, mixed>> $linhas
     * @param list<array<string, mixed>> $ufs
     */
    public static function listar(
        string $slug,
        array $app,
        array $identidade,
        array $filtros,
        array $linhas,
        array $ufs,
        ?string $flash,
        ?string $erro,
        bool $podeGerenciar
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Certificadoras', 'certificadoras.listar');
        $url = Urls::app($slug, 'certificadoras.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificadoras</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Certificadoras</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">OPACs, OCSs e certificadoras com contato, site e logo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($podeGerenciar): ?>
                    <a href="<?= self::e($url . '?acao=nova') ?>" class="rounded-full bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">+ Nova certificadora</a>
                <?php endif; ?>
                <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="get" action="<?= self::e($url) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="q" class="block text-xs font-medium text-gray-600">Buscar</label>
                    <input id="q" name="q" value="<?= self::e($filtros['q']) ?>" placeholder="Nome, sigla ou contato"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="uf" class="block text-xs font-medium text-gray-600">UF</label>
                    <select id="uf" name="uf" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <?php foreach ($ufs as $uf): ?>
                            <option value="<?= self::e((string) $uf['uf']) ?>" <?= $filtros['uf'] === (string) $uf['uf'] ? 'selected' : '' ?>><?= self::e((string) $uf['uf']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Aplicar filtros</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpar</a>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Nome</th>
                            <th class="px-3 py-3">Cidade/UF</th>
                            <th class="px-3 py-3">Contato</th>
                            <th class="px-3 py-3">Logo</th>
                            <th class="px-3 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">Nenhuma certificadora encontrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <tr class="border-b border-gray-100 align-middle hover:bg-gray-50">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-900"><?= self::e((string) $linha['nome']) ?></p>
                                    <?php if (!empty($linha['sigla'])): ?>
                                        <p class="text-[11px] text-gray-500"><?= self::e((string) $linha['sigla']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e(trim((string) (($linha['cidade'] ?? '') . ' / ' . ($linha['uf'] ?? $linha['estado'] ?? '')), ' /')) ?: '—' ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) (($linha['contato'] ?: $linha['email'] ?? '') ?: '—')) ?></td>
                                <td class="px-3 py-3">
                                    <?php $logo = Certificadoras::logo($linha); ?>
                                    <?php if ($logo !== null): ?>
                                        <img src="<?= self::e($logo) ?>" alt="Logo de <?= self::e((string) $linha['nome']) ?>" class="h-10 w-auto max-w-[120px] rounded object-contain">
                                    <?php else: ?>
                                        <span class="text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($podeGerenciar): ?>
                                            <a href="<?= self::e($url . '?acao=editar&id=' . (int) $linha['id']) ?>"
                                               class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-medium text-teal-800 hover:bg-teal-100">Editar</a>
                                        <?php endif; ?>
                                        <a href="<?= self::e($url . '?acao=membros&id=' . (int) $linha['id']) ?>"
                                           class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100">Ver membros</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500"><?= count($linhas) ?> certificadora(s) listada(s)</div>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $registro
     */
    public static function formulario(
        string $slug,
        array $app,
        array $identidade,
        string $modo,
        array $registro,
        ?string $erro,
        bool $podeGerenciar
    ): void {
        $editando = $modo === 'editar' && !empty($registro['id']);
        $titulo = $editando ? 'Editar certificadora' : 'Nova certificadora';
        Ui::abrirLayout($slug, $app, $identidade, $titulo, 'certificadoras.listar');
        $url = Urls::app($slug, 'certificadoras.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificadoras</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl"><?= self::e($titulo) ?></h1>
            </div>
            <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar à lista</a>
        </div>

        <?php self::alertas(null, $erro); ?>

        <form method="post" action="<?= self::e($url) ?>" enctype="multipart/form-data"
              class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int) $registro['id'] ?>">
            <?php endif; ?>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="nome" class="block text-xs font-medium text-gray-600">Nome <span class="text-red-600">*</span></label>
                    <input id="nome" name="nome" required value="<?= self::e((string) ($registro['nome'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="sigla" class="block text-xs font-medium text-gray-600">Sigla</label>
                    <input id="sigla" name="sigla" value="<?= self::e((string) ($registro['sigla'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="cidade" class="block text-xs font-medium text-gray-600">Cidade</label>
                    <input id="cidade" name="cidade" value="<?= self::e((string) ($registro['cidade'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="uf" class="block text-xs font-medium text-gray-600">UF</label>
                    <input id="uf" name="uf" maxlength="2" value="<?= self::e((string) ($registro['uf'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm uppercase">
                </div>
                <div class="sm:col-span-2">
                    <label for="contato" class="block text-xs font-medium text-gray-600">Contato / e-mail</label>
                    <input id="contato" name="contato" value="<?= self::e((string) ($registro['contato'] ?? $registro['email'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="site" class="block text-xs font-medium text-gray-600">Site</label>
                    <input id="site" name="site" type="url" value="<?= self::e((string) ($registro['site'] ?? '')) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="logo_url" class="block text-xs font-medium text-gray-600">Logo (URL pública)</label>
                    <input id="logo_url" name="logo_url" value="<?= self::e((string) ($registro['logo_url'] ?? '')) ?>"
                           placeholder="https://…/logo.png"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="logo" class="block text-xs font-medium text-gray-600">Logo (arquivo)</label>
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>

            <?php $logoAtual = Certificadoras::logo($registro); ?>
            <?php if ($logoAtual !== null): ?>
                <div class="mt-4 flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <img src="<?= self::e($logoAtual) ?>" alt="Logo atual" class="h-12 w-auto max-w-[160px] object-contain">
                    <span class="text-[11px] text-gray-500">Logo atual (envie outra para substituir).</span>
                </div>
            <?php endif; ?>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Salvar certificadora</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $certificadora
     * @param list<array<string, mixed>> $membros
     */
    public static function membros(
        string $slug,
        array $app,
        array $identidade,
        array $certificadora,
        array $membros,
        bool $podeGerenciar
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Membros da certificadora', 'certificadoras.listar');
        $url = Urls::app($slug, 'certificadoras.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificadoras</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Membros — <?= self::e((string) $certificadora['nome']) ?></h1>
                <p class="mt-1 text-sm text-gray-600">Associados vinculados a esta certificadora/organismo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($podeGerenciar): ?>
                    <a href="<?= self::e($url . '?acao=editar&id=' . (int) $certificadora['id']) ?>" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Editar certificadora</a>
                <?php endif; ?>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">← Lista</a>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Associado</th>
                            <th class="px-3 py-3">E-mail</th>
                            <th class="px-3 py-3">Telefone</th>
                            <th class="px-3 py-3">Município</th>
                            <th class="px-3 py-3">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($membros === []): ?>
                            <tr><td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum associado vinculado a esta certificadora.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($membros as $membro): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-3 py-3 font-medium text-gray-800"><?= self::e((string) $membro['nome']) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) ($membro['email'] ?? '—')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) ($membro['telefone'] ?? '—')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) ($membro['municipio'] ?? '—')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) ($membro['status'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500"><?= count($membros) ?> membro(s)</div>
        </div>
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
