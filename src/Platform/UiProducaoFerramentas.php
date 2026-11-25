<?php

declare(strict_types=1);

namespace App\Platform;

/** Telas de relatórios e rastreabilidade da produção. */
final class UiProducaoFerramentas
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, array{def: array<string, mixed>, total: int}> $totais
     */
    public static function relatorios(string $slug, array $app, array $identidade, array $totais): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Relatórios de produção', 'producao.relatorios');
        $grupos = [];
        foreach ($totais as $recurso => $item) {
            $grupo = (string) ($item['def']['grupo'] ?? 'Produção');
            $grupos[$grupo][$recurso] = $item;
        }
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">Ferramentas</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Relatórios de produção</h1>
                <p class="mt-1 text-sm text-gray-600">Resumo dos registros e exportação em CSV, separados por módulo de produção.</p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
               class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
        </div>

        <?php foreach ($grupos as $grupo => $recursos): ?>
            <section class="mt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500"><?= self::e($grupo) ?></h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($recursos as $recurso => $item): ?>
                        <?php $url = Urls::app($slug, 'registro.php?recurso=' . rawurlencode((string) $recurso)); ?>
                        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900"><?= self::e((string) $item['def']['titulo']) ?></p>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800 ring-1 ring-amber-200"><?= (int) $item['total'] ?></span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500"><?= self::e((string) $item['def']['singular']) ?> registrados no seu escopo.</p>
                            <div class="mt-3 flex gap-2 text-[11px] font-semibold">
                                <a href="<?= self::e($url) ?>" class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-teal-800">Abrir</a>
                                <a href="<?= self::e($url . '&exportar=csv') ?>" class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-gray-700">CSV</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $resultados
     */
    public static function rastreabilidade(string $slug, array $app, array $identidade, string $codigo, array $resultados): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Rastreabilidade', 'producao.rastreabilidade');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">Ferramentas</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Rastreabilidade</h1>
                <p class="mt-1 text-sm text-gray-600">Consulte lotes de produção, ordens de produção e códigos públicos.</p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
               class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
        </div>

        <form method="get" action="<?= self::e(Urls::app($slug, 'rastreabilidade.php')) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label for="q" class="block text-xs font-medium text-gray-600">Código / nome do lote</label>
                    <input id="q" name="q" value="<?= self::e($codigo) ?>" placeholder="Ex.: LOTE-2026-001"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-full bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">Consultar</button>
            </div>
        </form>

        <?php if ($codigo !== ''): ?>
            <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-3">Tipo</th>
                                <th class="px-3 py-3">Código</th>
                                <th class="px-3 py-3">Data</th>
                                <th class="px-3 py-3">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultados === []): ?>
                                <tr><td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum registro encontrado.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($resultados as $resultado): ?>
                                <tr class="border-b border-gray-100">
                                    <td class="px-3 py-3 text-gray-700"><?= self::e((string) $resultado['tipo']) ?></td>
                                    <td class="px-3 py-3 font-medium text-gray-900"><?= self::e((string) $resultado['codigo']) ?></td>
                                    <td class="px-3 py-3 text-gray-600"><?= self::e((string) ($resultado['data'] ?? '—')) ?></td>
                                    <td class="px-3 py-3">
                                        <?php if ((string) ($resultado['recurso'] ?? '') !== ''): ?>
                                            <a href="<?= self::e(Urls::app($slug, 'registro.php?recurso=' . rawurlencode((string) $resultado['recurso']) . '&q=' . rawurlencode((string) $resultado['codigo']))) ?>"
                                               class="text-[11px] font-semibold text-teal-700 hover:underline">Abrir registro</a>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <?php Ui::fecharLayout();
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
