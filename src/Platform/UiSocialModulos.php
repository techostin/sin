<?php

declare(strict_types=1);

namespace App\Platform;

/** Telas do painel técnico/gestor do Controle Social. */
final class UiSocialModulos
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, int> $kpis
     * @param list<array<string, mixed>> $visitas
     */
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, int> $kpis
     * @param list<array<string, mixed>> $visitas
     * @param list<array<string, mixed>> $confirmacoes
     */
    public static function painel(
        string $slug,
        array $app,
        array $identidade,
        array $kpis,
        array $visitas,
        array $confirmacoes = [],
        ?string $flash = null
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Painel do Gestor / Técnico', 'painel.gestor');
        $modulo = static fn(string $m): string => Urls::app($slug, 'modulo.php?modulo=' . $m);
        $cards = [
            ['Associados ativos', $kpis['associados'] ?? 0, '👥', '#'],
            ['Certificações ativas', $kpis['certificacoes'] ?? 0, '🏅', Urls::app($slug, 'certificacoes.php')],
            ['Planos em análise', $kpis['planos'] ?? 0, '📔', $modulo('planos')],
            ['Visitas agendadas', $kpis['visitas'] ?? 0, '📅', $modulo('visitas')],
            ['NCs abertas', $kpis['ncs'] ?? 0, '⚠️', $modulo('ncs')],
            ['Insumos em falta', $kpis['insumos_baixo'] ?? 0, '🧪', $modulo('validacao')],
            ['Propriedades', $kpis['propriedades'] ?? 0, '🏡', '#'],
            ['Atividades (semana)', $kpis['atividades'] ?? 0, '📓', '#'],
            ['Rebanho (animais)', $kpis['animais'] ?? 0, '🐄', '#'],
            ['Lotes de animais', $kpis['lotes_animais'] ?? 0, '🐑', '#'],
            ['Produtores no CNPO', $kpis['cnpo'] ?? 0, '🔎', $modulo('cnpo')],
        ];
        ?>
        <?php if ($flash !== null && $flash !== ''): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div>
        <?php endif; ?>
        <div class="rounded-3xl bg-gradient-to-br from-sky-800 via-cyan-700 to-blue-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-teal-100">Fiscalização e acompanhamento técnico</p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Painel do Gestor / Técnico</h1>
                    <p class="mt-1 text-sm text-teal-50">Controle social, inspeções e conformidade da produção orgânica.</p>
                </div>
                <a href="<?= self::e(Urls::app($slug, 'agendar-visita.php')) ?>"
                   class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-teal-900 hover:bg-teal-50">+ Agendar visita</a>
            </div>
            <div class="mt-5 flex flex-wrap gap-3 text-xs">
                <span class="rounded-full bg-white/15 px-3 py-1.5">👥 <?= (int) ($kpis['associados'] ?? 0) ?> associados ativos</span>
                <span class="rounded-full bg-white/15 px-3 py-1.5">📅 <?= (int) ($kpis['visitas'] ?? 0) ?> visitas agendadas</span>
                <span class="rounded-full bg-white/15 px-3 py-1.5">⚠️ <?= (int) ($kpis['ncs'] ?? 0) ?> NCs abertas</span>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($cards as [$rotulo, $valor, $icone, $link]): ?>
                <a href="<?= self::e($link) ?>" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-teal-300 hover:shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-xl" aria-hidden="true"><?= $icone ?></span>
                        <span class="text-2xl font-bold text-gray-900"><?= (int) $valor ?></span>
                    </div>
                    <p class="mt-2 text-xs font-medium text-gray-600"><?= self::e($rotulo) ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900">Próximas visitas e inspeções</h2>
                    <a href="<?= self::e($modulo('visitas')) ?>" class="text-xs font-semibold text-teal-700 hover:underline">Ver todas</a>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                <th class="px-2 py-2">Agricultor</th>
                                <th class="px-2 py-2">Propriedade</th>
                                <th class="px-2 py-2">Data</th>
                                <th class="px-2 py-2">Ciência</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($visitas === []): ?>
                                <tr><td colspan="4" class="px-2 py-6 text-center text-gray-500">Nenhuma visita agendada.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($visitas as $v): ?>
                                <tr class="border-b border-gray-100">
                                    <td class="px-2 py-2"><?= self::e((string) ($v['agricultor'] ?? '—')) ?></td>
                                    <td class="px-2 py-2 text-gray-600"><?= self::e((string) ($v['propriedade'] ?? '—')) ?></td>
                                    <td class="px-2 py-2 text-gray-600"><?= self::e(self::data((string) ($v['data_visita'] ?? ''))) ?></td>
                                    <td class="px-2 py-2">
                                        <?php if (!empty($v['ciente'])): ?><span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-800">Ciente</span>
                                        <?php elseif (!empty($v['ciente_recusada'])): ?><span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">Recusou</span>
                                        <?php else: ?><span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Pendente</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Ações de fiscalização</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <a class="block rounded-xl border border-gray-200 px-3 py-2 hover:bg-gray-50" href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=roteiros')) ?>">🗒️ Roteiros de inspeção</a>
                    <a class="block rounded-xl border border-gray-200 px-3 py-2 hover:bg-gray-50" href="<?= self::e(Urls::app($slug, 'agendar-visita.php')) ?>">📅 Agendar visita</a>
                    <a class="block rounded-xl border border-gray-200 px-3 py-2 hover:bg-gray-50" href="<?= self::e(Urls::app($slug, 'certificadoras.php')) ?>">🏢 Certificadoras</a>
                    <a class="block rounded-xl border border-gray-200 px-3 py-2 hover:bg-gray-50" href="<?= self::e(Urls::app($slug, 'relatorios.php')) ?>">📊 Relatórios</a>
                </div>
            </section>
        </div>
        <?php
        Ui::fecharLayout();
        self::modalVisitas($slug, $identidade, $confirmacoes);
    }

    /**
     * Modal "Confirmações de Visitas Agendadas" do gestor/técnico.
     *
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $confirmacoes
     */
    private static function modalVisitas(string $slug, array $identidade, array $confirmacoes): void
    {
        if ($confirmacoes === []) {
            return;
        }

        $podeEscrever = SocialModulos::podeEscrever($identidade);
        $totalVisitas = count($confirmacoes);
        $pendentes = 0;
        foreach ($confirmacoes as $visita) {
            if (empty($visita['gestor_ciente'])) {
                $pendentes++;
            }
        }
        if (!$podeEscrever) {
            $pendentes = 0;
        }
        ?>
        <div id="modal-visitas-gestor" class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 rounded-t-2xl bg-gradient-to-r from-teal-800 to-emerald-700 px-5 py-4 text-white">
                    <div>
                        <h2 class="text-lg font-bold">Confirmações de Visitas Agendadas</h2>
                        <p class="mt-1 text-xs text-teal-50">Acompanhe a confirmação dos agricultores e registre a ciência do gestor.</p>
                    </div>
                    <button type="button" id="fechar-modal-visitas-gestor"
                            class="rounded-lg px-2 py-1 text-xl leading-none text-white/90 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            <?= $pendentes > 0 ? 'disabled title="Registre a ciência em todas as visitas para fechar"' : '' ?>
                            onclick="sinporgFecharVisitasGestor()" aria-label="Fechar">×</button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                    <p class="text-sm text-gray-600">
                        Clique em <strong>Ciente</strong> para registrar que tomou conhecimento do status atual ou em
                        <strong>Reagendar</strong> para alterar a data/hora; o agricultor será notificado novamente.
                    </p>
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Ciência do gestor</span>
                            <span class="font-semibold"><?= $totalVisitas - $pendentes ?> de <?= $totalVisitas ?></span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-teal-600" style="width: <?= $totalVisitas > 0 ? (int) round((($totalVisitas - $pendentes) / $totalVisitas) * 100) : 100 ?>%"></div>
                        </div>
                    </div>
                    <?php if ($pendentes === 0): ?>
                        <div class="mt-3 rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-900">
                            Todas as visitas com ciência registrada. Você já pode fechar este modal.
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 space-y-3">
                        <?php foreach ($confirmacoes as $visita): ?>
                            <?php
                            $ciente = !empty($visita['ciente']);
                            $recusou = !empty($visita['ciente_recusada']);
                            $gestorCiente = !empty($visita['gestor_ciente']);
                            ?>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900"><?= self::e((string) ($visita['associado_nome'] ?? 'Agricultor')) ?></p>
                                        <p class="text-xs text-gray-500"><?= self::e((string) ($visita['propriedade_nome'] ?? 'Propriedade')) ?></p>
                                        <p class="mt-1 text-xs text-gray-500"><?= self::e((string) ($visita['roteiro_nome'] ?? 'Roteiro')) ?> · <?= self::e(trim((string) ($visita['tecnico_nome'] ?? '')) !== '' ? (string) $visita['tecnico_nome'] : 'Equipe técnica') ?></p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <?php if ($ciente): ?>
                                            <span class="rounded-full bg-green-50 px-2 py-0.5 font-semibold text-green-800 ring-1 ring-green-200">Confirmado</span>
                                        <?php elseif ($recusou): ?>
                                            <span class="rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 ring-1 ring-red-200">Recusou</span>
                                        <?php else: ?>
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-800 ring-1 ring-amber-200">Pendente</span>
                                        <?php endif; ?>
                                        <p class="mt-1 font-semibold text-gray-700"><?= self::e(self::data((string) ($visita['data_visita'] ?? ''))) ?> · <?= self::e(substr((string) ($visita['hora_inicio'] ?? ''), 0, 5)) ?></p>
                                    </div>
                                </div>
                                <?php if ($recusou && !empty($visita['ciente_recusada_motivo'])): ?>
                                    <p class="mt-2 rounded-lg bg-red-50 px-2 py-1 text-xs text-red-700"><strong>Motivo:</strong> <?= self::e((string) $visita['ciente_recusada_motivo']) ?></p>
                                <?php endif; ?>

                                <?php if ($podeEscrever): ?>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <?php if (!$gestorCiente): ?>
                                            <form method="post" action="<?= self::e(Urls::app($slug, 'painel.php')) ?>">
                                                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                                <input type="hidden" name="acao" value="visita_ciente">
                                                <input type="hidden" name="visita_id" value="<?= (int) ($visita['id'] ?? 0) ?>">
                                                <button type="submit" class="rounded-full border border-teal-300 bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-100">👁 Ciente</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="rounded-full bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-800">✓ Ciência registrada</span>
                                        <?php endif; ?>

                                        <details class="w-full">
                                            <summary class="inline-flex cursor-pointer rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">📅 Reagendar</summary>
                                            <form method="post" action="<?= self::e(Urls::app($slug, 'painel.php')) ?>" class="mt-2 flex flex-wrap items-end gap-2 rounded-xl bg-gray-50 p-3">
                                                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                                <input type="hidden" name="acao" value="visita_reagendar">
                                                <input type="hidden" name="visita_id" value="<?= (int) ($visita['id'] ?? 0) ?>">
                                                <div>
                                                    <label class="block text-[10px] font-medium text-gray-600">Nova data</label>
                                                    <input type="date" name="data_visita" required min="<?= self::e(date('Y-m-d')) ?>" value="<?= self::e((string) ($visita['data_visita'] ?? '')) ?>" class="mt-1 rounded-lg border border-gray-300 px-2 py-1 text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-medium text-gray-600">Hora</label>
                                                    <input type="time" name="hora_inicio" value="<?= self::e(substr((string) ($visita['hora_inicio'] ?? ''), 0, 5)) ?>" class="mt-1 rounded-lg border border-gray-300 px-2 py-1 text-xs">
                                                </div>
                                                <button type="submit" class="rounded-full bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800">Confirmar reagendamento</button>
                                            </form>
                                        </details>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl border-t border-gray-100 px-5 py-3">
                    <button type="button" onclick="sinporgFecharVisitasGestor()"
                            class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-gray-300"
                            <?= $pendentes > 0 ? 'disabled' : '' ?>>Fechar</button>
                </div>
            </div>
        </div>
        <script>
        document.body.classList.add('overflow-hidden');
        function sinporgFecharVisitasGestor() {
            var modal = document.getElementById('modal-visitas-gestor');
            if (!modal) { return; }
            var fechar = document.getElementById('fechar-modal-visitas-gestor');
            if (fechar && fechar.disabled) { return; }
            modal.remove();
            document.body.classList.remove('overflow-hidden');
        }
        </script>
        <?php
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $linhas
     */
    public static function lista(
        string $slug,
        array $app,
        array $identidade,
        string $modulo,
        array $def,
        array $linhas,
        string $busca,
        string $status,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, (string) $def['titulo'], (string) $def['chave']);
        $url = Urls::app($slug, 'modulo.php?modulo=' . rawurlencode($modulo));
        $podeEscrever = SocialModulos::podeEscrever($identidade);
        $statusOpcoes = match ($modulo) {
            'planos' => ['Em Análise', 'Aprovado', 'Reprovado', 'Revisão Necessária', 'Rascunho'],
            'visitas' => ['Agendada', 'Realizada', 'Cancelada', 'Reagendada'],
            'ncs' => ['Aberta', 'Em Correção', 'Resolvida', 'Não Resolvida', 'Cancelada'],
            default => [],
        };
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Controle Social e Certificação</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl"><?= self::e((string) $def['titulo']) ?></h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e((string) $def['descricao']) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'painel.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
                <?php if ($modulo === 'roteiros' && $podeEscrever): ?>
                    <a href="<?= self::e(Urls::app($slug, 'roteiro.php?novo=1')) ?>"
                       class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">+ Novo roteiro</a>
                <?php endif; ?>
                <a href="<?= self::e($url . '&exportar=csv') ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Exportar CSV</a>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="get" action="<?= self::e(Urls::app($slug, 'modulo.php')) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="modulo" value="<?= self::e($modulo) ?>">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[220px] flex-1">
                    <label for="q" class="block text-xs font-medium text-gray-600">Buscar</label>
                    <input id="q" name="q" value="<?= self::e($busca) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <?php if ($statusOpcoes !== []): ?>
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-600">Situação</label>
                        <select id="status" name="status" class="mt-1 rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Todas</option>
                            <?php foreach ($statusOpcoes as $opcao): ?>
                                <option value="<?= self::e($opcao) ?>" <?= $opcao === $status ? 'selected' : '' ?>><?= self::e($opcao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Filtrar</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpar</a>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <?= self::cabecalhos($modulo, $podeEscrever) ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="9" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum registro encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <?= self::linha($slug, $modulo, $linha, $podeEscrever) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500"><?= count($linhas) ?> registro(s)</div>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $visita
     */
    public static function visita(string $slug, array $app, array $identidade, array $visita): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Visita técnica #' . (int) ($visita['id'] ?? 0), 'visitas.inspecoes');
        $checklist = json_decode((string) ($visita['checklist'] ?? ''), true);
        $checklist = is_array($checklist) ? $checklist : [];
        $nomes = [];
        foreach ($checklist as $item) {
            $nomes[strval($item['id'] ?? '')] = $item['item'] ?? $item['pergunta'] ?? null;
        }
        require_once dirname(__DIR__, 2) . '/classes/ChecklistPadrao.php';
        if (class_exists('ChecklistPadrao')) {
            foreach (\ChecklistPadrao::mapa() as $id => $item) {
                $nomes[strval($id)] = $nomes[strval($id)] ?? $item['item'];
            }
        }
        $resultado = $visita['resultado'] ?? [];
        $evidencias = $visita['evidencias'] ?? [];
        $ncs = $visita['ncs'] ?? [];
        $podeEscrever = SocialModulos::podeEscrever($identidade);
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Visitas e Inspeções</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Resumo da Visita Técnica #<?= (int) ($visita['id'] ?? 0) ?></h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e((string) ($visita['roteiro_nome'] ?? 'Roteiro')) ?> · <?= self::e((string) ($visita['associado_nome'] ?? '—')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=visitas')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Visitas</a>
                <button type="button" onclick="window.print()" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">🖨 Imprimir / PDF</button>
                <?php if ($podeEscrever && ($visita['status'] ?? '') === 'Agendada'): ?>
                    <a href="<?= self::e(Urls::app($slug, 'realizar-visita.php?id=' . (int) $visita['id'])) ?>" class="rounded-full bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Realizar inspeção</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-1">
                <h2 class="text-sm font-semibold text-gray-900">Informações</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-[11px] uppercase text-gray-500">Agricultor</dt><dd class="text-gray-800"><?= self::e((string) ($visita['associado_nome'] ?? '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Propriedade</dt><dd class="text-gray-800"><?= self::e((string) ($visita['propriedade_nome'] ?? '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Local</dt><dd class="text-gray-800"><?= self::e(trim((string) ($visita['cidade'] ?? '') . ((string) ($visita['uf'] ?? '') !== '' ? '/' . (string) $visita['uf'] : ''))) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Data</dt><dd class="text-gray-800"><?= self::e(self::data((string) ($visita['data_visita'] ?? ''))) ?> · <?= self::e(substr((string) ($visita['hora_inicio'] ?? ''), 0, 5)) ?>–<?= self::e(substr((string) ($visita['hora_fim'] ?? ''), 0, 5)) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Técnico</dt><dd class="text-gray-800"><?= self::e((string) ($visita['tecnico_nome'] ?? '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Condições do tempo</dt><dd class="text-gray-800"><?= self::e((string) ($visita['condicoes_clima'] ?? '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Conformidade</dt><dd><?= self::badge((string) ($visita['conformidade'] ?? '—')) ?></dd></div>
                    <div>
                        <dt class="text-[11px] uppercase text-gray-500">Pontuação</dt>
                        <dd class="mt-1">
                            <div class="h-3 w-full rounded-full bg-gray-100"><div class="h-3 rounded-full bg-green-600" style="width: <?= (int) ($visita['pontuacao'] ?? 0) ?>%"></div></div>
                            <span class="text-xs text-gray-600"><?= (int) ($visita['pontuacao'] ?? 0) ?>%</span>
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <h3 class="text-[11px] uppercase text-gray-500">Parecer técnico</h3>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700"><?= self::e(trim((string) ($visita['observacoes'] ?? '')) !== '' ? (string) $visita['observacoes'] : 'Nenhuma observação registrada.') ?></p>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-900">Checklist (<?= count($resultado) ?> respostas)</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-gray-200 text-left text-[11px] uppercase text-gray-500">
                            <th class="px-2 py-2">Item / Requisito</th><th class="px-2 py-2">Status</th><th class="px-2 py-2">Observação</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($resultado === []): ?>
                            <tr><td colspan="3" class="px-2 py-5 text-center text-xs text-gray-500">Sem respostas registradas.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($resultado as $itemId => $res): ?>
                            <tr class="border-b border-gray-100">
                                <td class="px-2 py-2 text-gray-800"><?= self::e((string) ($nomes[strval($itemId)] ?? 'Item #' . $itemId)) ?></td>
                                <td class="px-2 py-2">
                                    <?php if (!empty($res['na'])): ?>
                                        <span class="font-semibold text-gray-500">N/A</span>
                                    <?php elseif (!empty($res['conforme'])): ?>
                                        <span class="font-semibold text-green-700">Conforme</span>
                                    <?php else: ?>
                                        <span class="font-semibold text-red-700">Não Conforme</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2 text-xs text-gray-500"><?= self::e((string) ($res['observacao'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Não conformidades (<?= count($ncs) ?>)</h2>
                <?php if ($ncs === []): ?>
                    <p class="mt-2 text-xs text-gray-500">Nenhuma não conformidade registrada nesta visita.</p>
                <?php else: ?>
                    <div class="mt-3 space-y-2">
                        <?php foreach ($ncs as $nc): ?>
                            <a href="<?= self::e(Urls::app($slug, 'nc.php?id=' . (int) $nc['id'])) ?>" class="block rounded-xl border border-gray-200 p-3 hover:bg-gray-50">
                                <div class="flex items-center justify-between gap-2"><?= self::badge((string) $nc['status']) ?> <span class="text-[11px] text-gray-500"><?= self::e((string) $nc['gravidade']) ?></span></div>
                                <p class="mt-1 text-xs text-gray-700"><?= self::e((string) $nc['descricao']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Evidências e assinaturas</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 text-center">
                    <div>
                        <?php if (!empty($visita['assinatura_produtor_url'])): ?><img src="<?= self::e((string) $visita['assinatura_produtor_url']) ?>" class="mx-auto max-h-24 rounded border" alt="Assinatura do produtor"><?php else: ?><p class="text-xs text-gray-400">Sem assinatura</p><?php endif; ?>
                        <p class="mt-1 text-[11px] text-gray-500">Produtor</p>
                    </div>
                    <div>
                        <?php if (!empty($visita['assinatura_tecnico_url'])): ?><img src="<?= self::e((string) $visita['assinatura_tecnico_url']) ?>" class="mx-auto max-h-24 rounded border" alt="Assinatura do técnico"><?php else: ?><p class="text-xs text-gray-400">Sem assinatura</p><?php endif; ?>
                        <p class="mt-1 text-[11px] text-gray-500">Técnico</p>
                    </div>
                </div>
                <?php if ($evidencias !== []): ?>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($evidencias as $url): ?>
                            <img src="<?= self::e((string) $url) ?>" class="h-20 w-20 rounded-lg border object-cover" alt="Evidência">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $visita
     * @param list<array<string, mixed>> $checklist
     * @param array<string, mixed> $contexto
     */
    public static function realizar(
        string $slug,
        array $app,
        array $identidade,
        array $visita,
        array $checklist,
        array $contexto,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Realizar inspeção', 'visitas.inspecoes');
        $grupos = [];
        foreach ($checklist as $item) {
            $grupos[(string) ($item['categoria'] ?? 'Geral')][] = $item;
        }
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Visitas e Inspeções</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Checklist de Inspeção</h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e((string) ($visita['roteiro_nome'] ?? '')) ?> — Visita #<?= (int) ($visita['id'] ?? 0) ?></p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'visita.php?id=' . (int) $visita['id'])) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar</a>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="post" action="<?= self::e(Urls::app($slug, 'realizar-visita.php?id=' . (int) $visita['id'])) ?>" enctype="multipart/form-data" id="form-inspecao" class="mt-5 space-y-4">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="assinatura_produtor_url" id="assinatura_produtor_url">
            <input type="hidden" name="assinatura_tecnico_url" id="assinatura_tecnico_url">

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="block text-xs font-medium text-gray-600">Hora início *</label><input type="time" name="hora_inicio" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600">Hora fim *</label><input type="time" name="hora_fim" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Condições do tempo *</label>
                        <select name="condicoes_clima" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Selecione...</option>
                            <?php foreach (['Ensolarado', 'Parcialmente Nublado', 'Nublado', 'Chuvoso', 'Chuva Fraca', 'Chuva Forte', 'Ventoso', 'Neve/Granizo'] as $clima): ?>
                                <option value="<?= self::e($clima) ?>"><?= self::e($clima) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <?php foreach ($grupos as $categoria => $itens): ?>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900"><?= self::e($categoria) ?></h2>
                    <div class="mt-3 space-y-3">
                        <?php foreach ($itens as $item): $itemId = strval($item['id']); ?>
                            <div class="rounded-xl border border-gray-200 p-3" data-obrigatorio="<?= $item['obrigatorio'] ? '1' : '0' ?>">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm text-gray-800"><?= self::e((string) $item['item']) ?> <?php if ($item['obrigatorio']): ?><span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-700 ring-1 ring-red-200">Crítico</span><?php endif; ?></p>
                                    <button type="button" data-contexto-item="<?= self::e($itemId) ?>" data-contexto-categoria="<?= self::e((string) $item['categoria']) ?>" data-contexto-titulo="<?= self::e((string) $item['item']) ?>" class="shrink-0 rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800 hover:bg-teal-100">🔎 Contexto</button>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-4">
                                    <?php foreach (['Conforme' => 'text-green-700', 'Não Conforme' => 'text-red-700', 'N/A' => 'text-gray-500'] as $rotulo => $cor): ?>
                                        <label class="flex items-center gap-1.5 text-xs font-semibold <?= $cor ?>">
                                            <input type="radio" name="item[<?= self::e($itemId) ?>][status]" value="<?= self::e($rotulo) ?>" required class="accent-teal-700"> <?= self::e($rotulo) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <textarea name="item[<?= self::e($itemId) ?>][obs]" rows="2" placeholder="Observações / evidências..." class="mt-2 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs"></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Fotos de evidência (máx. 5)</h2>
                <input type="file" name="evidencias[]" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 text-xs">
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Assinaturas</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div><p class="text-xs text-gray-600">Assinatura do produtor</p><canvas class="assinatura mt-1 h-28 w-full rounded-lg border border-gray-300 bg-white" data-alvo="assinatura_produtor_url"></canvas><button type="button" onclick="sinporgLimparAssinatura(this)" class="mt-1 text-xs text-red-600">Limpar</button></div>
                    <div><p class="text-xs text-gray-600">Assinatura do técnico</p><canvas class="assinatura mt-1 h-28 w-full rounded-lg border border-gray-300 bg-white" data-alvo="assinatura_tecnico_url"></canvas><button type="button" onclick="sinporgLimparAssinatura(this)" class="mt-1 text-xs text-red-600">Limpar</button></div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <label class="block text-sm font-semibold text-gray-900">Parecer final</label>
                <textarea name="observacoes" rows="3" placeholder="Resumo técnico da visita..." class="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></textarea>
            </section>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-green-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Finalizar inspeção</button>
                <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=visitas')) ?>" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>

        <script type="application/json" id="contexto-inspecao"><?= json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

        <div id="modal-contexto-inspecao" class="fixed inset-0 z-[120] hidden items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 rounded-t-2xl bg-teal-700 px-5 py-4 text-white">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-100">Contexto para análise técnica</p>
                        <h2 id="contexto-inspecao-titulo" class="mt-1 text-lg font-bold">Item do checklist</h2>
                    </div>
                    <button type="button" onclick="sinporgFecharContextoInspecao()" class="rounded-lg px-2 py-1 text-xl leading-none text-white/90 hover:bg-white/10">×</button>
                </div>
                <div id="contexto-inspecao-corpo" class="space-y-3 px-5 py-4"></div>
            </div>
        </div>

        <script>
        (function () {
            var modal = document.getElementById('modal-contexto-inspecao');
            var titulo = document.getElementById('contexto-inspecao-titulo');
            var corpo = document.getElementById('contexto-inspecao-corpo');
            var dados = {};
            try {
                dados = JSON.parse(document.getElementById('contexto-inspecao').textContent || '{}');
            } catch (e) { dados = {}; }

            function esc(valor) {
                return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            function linha(rotulo, valor) {
                return '<div class="grid grid-cols-3 gap-2 text-sm"><dt class="text-[11px] uppercase text-gray-500">' + esc(rotulo) + '</dt><dd class="col-span-2 text-gray-800">' + esc(valor || '—') + '</dd></div>';
            }
            function cartao(titulo, conteudo) {
                return '<section class="rounded-xl border border-gray-200 p-3"><h3 class="text-xs font-semibold uppercase text-gray-500">' + esc(titulo) + '</h3><div class="mt-2 space-y-1">' + conteudo + '</div></section>';
            }

            function propriedadeHtml() {
                var p = dados.propriedade || {};
                if (!Object.keys(p).length) return '';
                return cartao('Dados da propriedade',
                    linha('Propriedade', p.propriedade)
                    + linha('Endereço', p.endereco)
                    + linha('CEP', p.cep) + linha('Bairro', p.bairro)
                    + linha('Cidade/UF', (p.cidade || '') + (p.estado ? '/' + p.estado : ''))
                    + linha('Área total (ha)', p.area_total)
                    + linha('Latitude', p.latitude)
                    + linha('Longitude', p.longitude)
                    + linha('Recursos naturais', p.recursos_naturais)
                    + linha('Zona de amortecimento', p.zona_amortecimento));
            }

            function zonaAmortecimentoHtml() {
                var p = dados.propriedade || {};
                var plano = (dados.planos || [])[0] || {};
                return cartao('Zonas de amortecimento e barreiras',
                    linha('Zona de amortecimento', p.zona_amortecimento)
                    + linha('Recursos naturais', p.recursos_naturais)
                    + linha('Práticas de conservação (plano)', plano.praticas_conservacao)
                    + linha('Fontes de água (plano)', plano.fonte_agua)
                    + linha('Coordenadas', (p.latitude || '') + (p.longitude ? ', ' + p.longitude : '')));
            }

            function planosHtml(campos) {
                if (!dados.planos || !dados.planos.length) return '';
                var blocos = dados.planos.map(function (p) {
                    var corpo = '<div class="font-semibold text-gray-800">' + esc(p.ano_safra + ' — ' + p.titulo)
                        + ' <span class="text-[11px] text-gray-500">(' + esc(p.status) + ' · v' + esc(p.versao) + ')</span></div>';
                    campos.forEach(function (campo) {
                        if (p[campo] != null && p[campo] !== '') {
                            corpo += '<div class="text-xs text-gray-600">' + esc(campo) + ': ' + esc(p[campo]) + '</div>';
                        }
                    });
                    return '<div class="rounded-lg bg-gray-50 p-2 text-sm">' + corpo + '</div>';
                }).join('');
                return cartao('Plano de manejo — campos relevantes', blocos);
            }

            function certificacoesHtml() {
                if (!dados.certificacoes || !dados.certificacoes.length) return '';
                var blocos = dados.certificacoes.map(function (c) {
                    return '<div class="rounded-lg bg-gray-50 p-2 text-sm">'
                        + '<div class="font-semibold text-gray-800">' + esc(c.tipo_certificacao + ' — ' + c.numero_certificado) + '</div>'
                        + '<div class="text-xs text-gray-600">Status: ' + esc(c.status) + ' · Validade: ' + esc(c.data_validade) + '</div>'
                        + '<div class="text-xs text-gray-600">' + esc(c.organismo_certificador || c.nome_grupo || '') + '</div></div>';
                }).join('');
                return cartao('Certificações', blocos);
            }

            function insumosHtml() {
                if (!dados.insumos || !dados.insumos.length) return '';
                var linhas = dados.insumos.map(function (i) {
                    return linha(i.nome + ' (' + i.tipo_insumo + ')', 'Estoque ' + i.quantidade_estoque + ' · ' + (i.aprovado_organico == 1 ? 'Conforme' : (i.aprovado_organico == 0 ? 'Não conforme' : 'Pendente')));
                }).join('');
                return cartao('Insumos do produtor', linhas);
            }

            function ncsHtml() {
                if (!dados.ncs_anteriores || !dados.ncs_anteriores.length) return '';
                var blocos = dados.ncs_anteriores.map(function (n) {
                    return '<div class="rounded-lg bg-gray-50 p-2 text-sm">'
                        + '<div class="font-semibold text-gray-800">' + esc(n.gravidade + ' · ' + n.status) + ' <span class="text-[11px] text-gray-500">' + esc(n.data_visita) + '</span></div>'
                        + '<div class="text-xs text-gray-600">' + esc(n.descricao) + '</div></div>';
                }).join('');
                return cartao('Não conformidades anteriores', blocos);
            }

            function animaisHtml() {
                if (!dados.animais || !dados.animais.length) return '';
                var linhas = dados.animais.map(function (a) {
                    return linha(a.nome || a.especie, (a.especie || '') + (a.raca ? ' · ' + a.raca : '') + (a.status ? ' · ' + a.status : ''));
                }).join('');
                return cartao('Manejo animal — rebanho', linhas);
            }

            document.querySelectorAll('[data-contexto-item]').forEach(function (botao) {
                botao.addEventListener('click', function () {
                    titulo.textContent = botao.getAttribute('data-contexto-titulo') || 'Item do checklist';
                    var categoria = (botao.getAttribute('data-contexto-categoria') || '').toLowerCase();
                    var tituloItem = (botao.getAttribute('data-contexto-titulo') || '').toLowerCase();
                    var assoc = dados.associado || {};
                    var html = '';
                    var tem = function (texto) { return categoria.indexOf(texto) !== -1 || tituloItem.indexOf(texto) !== -1; };

                    html += cartao('Produtor', linha('Nome', (assoc.nome || '') + ' ' + (assoc.sobrenome || ''))
                        + linha('E-mail', assoc.email) + linha('CPF', assoc.cpf) + linha('Telefone', assoc.telefone));

                    if (tem('zona') || tem('amortecimento') || tem('barreira')) {
                        html += zonaAmortecimentoHtml();
                    } else if (tem('propriedade') || tem('registro') || tem('identifica') || tem('unidade de produção') || tem('área total') || tem('coordenada') || tem('gps')) {
                        html += propriedadeHtml();
                    }

                    if (tem('insumo') || tem('semente') || tem('muda') || tem('propaga') || tem('adubo') || tem('fertilizante') || tem('defensivo') || tem('calda') || tem('biofertilizante')) {
                        html += insumosHtml();
                        html += planosHtml(['adubacao_verde', 'compostagem', 'biofertilizantes', 'manejo_pragas', 'manejo_doencas']);
                    }

                    if (tem('solo') || tem('fitossanit') || tem('pragas') || tem('doença') || tem('rotação') || tem('cobertura') || tem('conserva')) {
                        html += planosHtml(['descricao_solo', 'rotacao_culturas', 'praticas_conservacao', 'manejo_pragas', 'manejo_doencas', 'adubacao_verde']);
                    }

                    if (tem('água') || tem('agua') || tem('irriga')) {
                        html += planosHtml(['fonte_agua', 'clima']);
                    }

                    if (tem('animal') || tem('rebanho') || tem('pasto') || tem('bem-estar') || tem('aleitamento') || tem('veterin')) {
                        html += animaisHtml();
                    }

                    if (tem('certific') || tem('ocs') || tem('spg') || tem('conformidade orgânica') || tem('certidão')) {
                        html += certificacoesHtml();
                    }

                    if (tem('documenta') || tem('rastreabil') || tem('caderno') || tem('registro') || tem('estoque') || tem('plano de manejo') || tem('pmo')) {
                        html += planosHtml(['titulo', 'status', 'area_organica_ha', 'area_conversao_ha']);
                        html += certificacoesHtml();
                    }

                    if (tem('armazenamento') || tem('transporte') || tem('embalagem') || tem('processamento') || tem('beneficiamento')) {
                        html += propriedadeHtml();
                        html += ncsHtml();
                    }

                    html += ncsHtml();

                    if (html.indexOf('Plano de manejo') === -1 && (dados.planos || []).length) {
                        html += planosHtml(['titulo', 'status', 'area_organica_ha', 'area_conversao_ha']);
                    }

                    if (html === '') {
                        html = '<p class="text-sm text-gray-500">Nenhum dado de contexto disponível para este item.</p>';
                    }
                    corpo.innerHTML = html;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            window.sinporgFecharContextoInspecao = function () {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };
        })();
        </script>

        <script>
        (function () {
            var form = document.getElementById('form-inspecao');
            var horaFim = form.querySelector('[name="hora_fim"]');
            form.addEventListener('submit', function () {
                var horaInicio = form.querySelector('[name="hora_inicio"]').value;
                if (horaFim && horaInicio && horaFim.value <= horaInicio) {
                    alert('A hora de fim deve ser posterior à hora de início.');
                    event.preventDefault();
                }
            });
        })();

        (function () {
            function iniciar(canvas) {
                var ctx = canvas.getContext('2d');
                var desenhando = false;
                function pos(e) {
                    var r = canvas.getBoundingClientRect();
                    var t = (e.touches && e.touches[0]) || e;
                    return { x: (t.clientX - r.left) * (canvas.width / r.width), y: (t.clientY - r.top) * (canvas.height / r.height) };
                }
                canvas.addEventListener('mousedown', function (e) { desenhando = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); });
                canvas.addEventListener('mousemove', function (e) { if (!desenhando) return; ctx.lineTo(pos(e).x, pos(e).y); ctx.stroke(); });
                canvas.addEventListener('mouseup', function () { desenhando = false; });
                canvas.addEventListener('touchstart', function (e) { e.preventDefault(); desenhando = true; ctx.beginPath(); ctx.moveTo(pos(e).x, pos(e).y); });
                canvas.addEventListener('touchmove', function (e) { e.preventDefault(); if (!desenhando) return; ctx.lineTo(pos(e).x, pos(e).y); ctx.stroke(); });
                canvas.addEventListener('touchend', function () { desenhando = false; });
            }
            document.querySelectorAll('canvas.assinatura').forEach(function (canvas) {
                canvas.width = canvas.offsetWidth || 400;
                canvas.height = 112;
                iniciar(canvas);
            });
            window.sinporgLimparAssinatura = function (btn) {
                var canvas = btn.parentElement.querySelector('canvas');
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            };
            window.addEventListener('submit', function () {
                document.querySelectorAll('canvas.assinatura').forEach(function (canvas) {
                    var alvo = canvas.getAttribute('data-alvo');
                    document.getElementById(alvo).value = canvas.toDataURL('image/png');
                });
            }, true);
        })();
        </script>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $nc
     */
    public static function nc(string $slug, array $app, array $identidade, array $nc, ?string $flash): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Não conformidade #' . (int) ($nc['id'] ?? 0), 'nao.conformidades');
        $podeEscrever = SocialModulos::podeEscrever($identidade);
        $resolvida = ($nc['status'] ?? '') === 'Resolvida';
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Não Conformidades</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Não Conformidade #<?= (int) ($nc['id'] ?? 0) ?></h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e((string) ($nc['produtor'] ?? '—')) ?> · <?= self::e((string) ($nc['propriedade_nome'] ?? '—')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=ncs')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← NCs</a>
                <a href="<?= self::e(Urls::app($slug, 'visita.php?id=' . (int) ($nc['visita_id'] ?? 0))) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Ver visita</a>
            </div>
        </div>

        <?php self::alertas($flash, null); ?>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2"><?= self::badge((string) $nc['status']) ?><span class="text-xs font-semibold text-gray-700">Gravidade: <?= self::e((string) $nc['gravidade']) ?></span></div>
                <div class="mt-3 rounded-xl border-l-4 border-red-400 bg-red-50 p-3">
                    <p class="text-[10px] uppercase text-gray-500">Requisito não atendido</p>
                    <p class="mt-1 whitespace-pre-line text-sm font-semibold text-gray-900"><?= self::e((string) $nc['descricao']) ?></p>
                </div>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-[11px] uppercase text-gray-500">Identificado em</dt><dd class="text-gray-800"><?= self::e(self::data((string) ($nc['data_visita'] ?? ''))) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Prazo limite</dt><dd class="text-gray-800"><?= self::e(self::data((string) ($nc['prazo_correcao'] ?? ''))) ?></dd></div>
                    <div><dt class="text-[11px] uppercase text-gray-500">Roteiro</dt><dd class="text-gray-800"><?= self::e((string) ($nc['roteiro_nome'] ?? '—')) ?></dd></div>
                    <?php if (!empty($nc['data_resolucao'])): ?>
                        <div><dt class="text-[11px] uppercase text-gray-500">Resolvida em</dt><dd class="text-green-700"><?= self::e(self::data((string) $nc['data_resolucao'])) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-900">Plano de ação corretiva</h2>
                <?php if ($podeEscrever && !$resolvida): ?>
                    <form method="post" action="<?= self::e(Urls::app($slug, 'nc.php?id=' . (int) $nc['id'])) ?>" class="mt-3 space-y-3">
                        <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                        <input type="hidden" name="acao" value="nc_salvar">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="block text-xs font-medium text-gray-600">Status</label>
                                <select name="status" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                    <?php foreach (['Aberta', 'Em Correção', 'Resolvida', 'Não Resolvida', 'Cancelada'] as $s): ?><option value="<?= self::e($s) ?>" <?= ($nc['status'] ?? '') === $s ? 'selected' : '' ?>><?= self::e($s) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="block text-xs font-medium text-gray-600">Gravidade</label>
                                <select name="gravidade" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                    <?php foreach (['Baixa', 'Média', 'Alta', 'Crítica'] as $g): ?><option value="<?= self::e($g) ?>" <?= ($nc['gravidade'] ?? '') === $g ? 'selected' : '' ?>><?= self::e($g) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="block text-xs font-medium text-gray-600">Prazo de correção</label><input type="date" name="prazo_correcao" value="<?= self::e(substr((string) ($nc['prazo_correcao'] ?? ''), 0, 10)) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                            <div><label class="block text-xs font-medium text-gray-600">Responsável</label><input type="text" name="responsavel_correcao" value="<?= self::e((string) ($nc['responsavel_correcao'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                        </div>
                        <div><label class="block text-xs font-medium text-gray-600">Ação corretiva</label><textarea name="acao_corretiva" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($nc['acao_corretiva'] ?? '')) ?></textarea></div>
                        <div><label class="block text-xs font-medium text-gray-600">Observações</label><textarea name="observacoes" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($nc['observacoes'] ?? '')) ?></textarea></div>
                        <button type="submit" class="rounded-full bg-green-700 px-5 py-2 text-sm font-semibold text-white hover:bg-green-800">Salvar</button>
                    </form>
                <?php elseif ($resolvida): ?>
                    <p class="mt-3 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">Esta não conformidade foi resolvida em <?= self::e(self::data((string) $nc['data_resolucao'])) ?>.</p>
                <?php else: ?>
                    <p class="mt-3 text-xs text-gray-500">Seu perfil pode consultar, mas não alterar esta não conformidade.</p>
                <?php endif; ?>
            </section>
        </div>

        <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Evidência de correção</h2>
            <?php if (!empty($nc['evidencia_correcao_url'])): ?><img src="<?= self::e((string) $nc['evidencia_correcao_url']) ?>" class="mt-2 max-h-56 rounded-lg border" alt="Evidência"><?php else: ?><p class="mt-2 text-xs text-gray-500">Nenhuma evidência anexada.</p><?php endif; ?>
            <?php if ($podeEscrever && !$resolvida): ?>
                <form method="post" action="<?= self::e(Urls::app($slug, 'nc.php?id=' . (int) $nc['id'])) ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                    <input type="hidden" name="acao" value="nc_evidencia">
                    <input type="file" name="evidencia_foto" accept="image/jpeg,image/png,image/webp" required class="text-xs">
                    <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Enviar foto</button>
                </form>
            <?php endif; ?>
        </section>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed>|null $roteiro
     */
    public static function roteiroForm(string $slug, array $app, array $identidade, ?array $roteiro, ?string $flash, ?string $erro): void
    {
        Ui::abrirLayout($slug, $app, $identidade, $roteiro === null ? 'Novo roteiro' : 'Editar roteiro', 'roteiros.templates');
        $itens = json_decode((string) ($roteiro['checklist'] ?? ''), true);
        $itens = is_array($itens) ? $itens : [];
        $categorias = ['Geral', 'Solo e Adubação', 'Manejo de Água', 'Insumos Utilizados', 'Sementes e Mudas', 'Controle de Pragas', 'Armazenamento', 'Documentação', 'Rastreabilidade'];
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Roteiros (Templates)</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900"><?= $roteiro === null ? 'Novo roteiro de inspeção' : 'Editar roteiro de inspeção' ?></h1>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=roteiros')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar</a>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="post" action="<?= self::e(Urls::app($slug, 'roteiro.php' . ($roteiro !== null ? '?id=' . (int) $roteiro['id'] : '?novo=1'))) ?>" class="mt-5 space-y-4">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($roteiro !== null): ?><input type="hidden" name="id" value="<?= (int) $roteiro['id'] ?>"><?php endif; ?>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Identificação</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    <div><label class="block text-xs font-medium text-gray-600">Nome *</label><input name="nome" required value="<?= self::e((string) ($roteiro['nome'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600">Código</label><input name="codigo" value="<?= self::e((string) ($roteiro['codigo'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Tipo de visita *</label>
                        <select name="tipo" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <?php foreach (['Visita Inicial', 'Visita Regular', 'Visita de Renovação', 'Visita Surpresa', 'Auditoria Interna'] as $tipo): ?>
                                <option value="<?= self::e($tipo) ?>" <?= ($roteiro['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= self::e($tipo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium text-gray-600">Pontuação mínima (%)</label><input name="pontuacao_minima" type="number" min="0" max="100" value="<?= (int) ($roteiro['pontuacao_minima'] ?? 70) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div class="sm:col-span-2"><label class="block text-xs font-medium text-gray-600">Objetivo técnico</label><textarea name="objetivo" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($roteiro['objetivo'] ?? '')) ?></textarea></div>
                    <div class="sm:col-span-3"><label class="block text-xs font-medium text-gray-600">Descrição</label><textarea name="descricao" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($roteiro['descricao'] ?? '')) ?></textarea></div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-gray-900">Checklist</h2>
                    <button type="button" onclick="sinporgAddItemRoteiro()" class="rounded-full border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-100">+ Adicionar item</button>
                </div>
                <div id="itens-roteiro" class="mt-3 space-y-2">
                    <?php foreach ($itens as $i => $item): ?>
                        <div class="item-roteiro grid gap-2 rounded-xl border border-gray-200 p-3 sm:grid-cols-12">
                            <input name="checklist[<?= (int) $i ?>][item]" value="<?= self::e((string) ($item['item'] ?? '')) ?>" placeholder="Requisito / pergunta" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-6">
                            <select name="checklist[<?= (int) $i ?>][categoria]" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-3">
                                <?php foreach ($categorias as $categoria): ?><option value="<?= self::e($categoria) ?>" <?= ($item['categoria'] ?? '') === $categoria ? 'selected' : '' ?>><?= self::e($categoria) ?></option><?php endforeach; ?>
                            </select>
                            <input name="checklist[<?= (int) $i ?>][peso]" type="number" min="1" value="<?= (int) ($item['peso'] ?? 10) ?>" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-1">
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 sm:col-span-1"><input type="checkbox" name="checklist[<?= (int) $i ?>][obrigatorio]" value="1" <?= !empty($item['obrigatorio']) ? 'checked' : '' ?>> Crítico</label>
                            <button type="button" onclick="this.closest('.item-roteiro').remove()" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1.5 text-xs font-semibold text-red-700 sm:col-span-1">Remover</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Salvar roteiro</button>
                <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=roteiros')) ?>" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>

        <script>
        function sinporgAddItemRoteiro() {
            var container = document.getElementById('itens-roteiro');
            var index = container.querySelectorAll('.item-roteiro').length;
            var html = '<div class="item-roteiro grid gap-2 rounded-xl border border-gray-200 p-3 sm:grid-cols-12">'
                + '<input name="checklist[' + index + '][item]" placeholder="Requisito / pergunta" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-6">'
                + '<select name="checklist[' + index + '][categoria]" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-3"><?php foreach ($categorias as $categoria): ?><option value="<?= self::e($categoria) ?>"><?= self::e($categoria) ?></option><?php endforeach; ?></select>'
                + '<input name="checklist[' + index + '][peso]" type="number" min="1" value="10" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-1">'
                + '<label class="flex items-center gap-1.5 text-xs text-gray-700 sm:col-span-1"><input type="checkbox" name="checklist[' + index + '][obrigatorio]" value="1"> Crítico</label>'
                + '<button type="button" onclick="this.closest(\'.item-roteiro\').remove()" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1.5 text-xs font-semibold text-red-700 sm:col-span-1">Remover</button></div>';
            container.insertAdjacentHTML('beforeend', html);
        }
        </script>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $roteiro
     */
    public static function roteiro(string $slug, array $app, array $identidade, array $roteiro): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Roteiro de inspeção', 'roteiros.templates');
        $checklist = json_decode((string) ($roteiro['checklist'] ?? ''), true);
        $itens = is_array($checklist) ? $checklist : [];
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Roteiros (Templates)</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900"><?= self::e((string) ($roteiro['nome'] ?? 'Roteiro')) ?></h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e((string) ($roteiro['tipo'] ?? '')) ?> · versão <?= (int) ($roteiro['versao'] ?? 1) ?></p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'modulo.php?modulo=roteiros')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar</a>
        </div>
        <div class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Checklist (<?= count($itens) ?> itens)</h2>
            <?php if ($itens === []): ?>
                <p class="mt-2 text-sm text-gray-500">Checklist sem itens ou não informado.</p>
            <?php else: ?>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700">
                    <?php foreach ($itens as $item): ?>
                        <li>
                            <?= self::e(is_array($item) ? (string) ($item['item'] ?? $item['titulo'] ?? $item['descricao'] ?? json_encode($item)) : (string) $item) ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, int> $kpis
     */
    public static function relatorios(string $slug, array $app, array $identidade, array $kpis): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Relatórios de fiscalização', 'relatorios.admin');
        $modulo = static fn(string $m): string => Urls::app($slug, 'modulo.php?modulo=' . $m);
        $relatorios = [
            ['Visitas e inspeções', 'Agendadas, realizadas e revisadas', $kpis['visitas'] ?? 0, $modulo('visitas')],
            ['Não conformidades', 'Abertas, em correção e resolvidas', $kpis['ncs'] ?? 0, $modulo('ncs')],
            ['Certificações SPG/OCS', 'Emitidas, renovadas e vencidas', $kpis['certificacoes'] ?? 0, Urls::app($slug, 'certificacoes.php')],
            ['Validação de insumos', 'Conformidade frente à Portaria 52/2021', $kpis['insumos_baixo'] ?? 0, $modulo('validacao')],
            ['Planos de manejo', 'Planos em análise e aprovados', $kpis['planos'] ?? 0, $modulo('planos')],
        ];
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Relatórios</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Relatórios Administrativos e de Conformidade</h1>
                <p class="mt-1 text-sm text-gray-600">Indicadores da fiscalização e atalhos para as listas com exportação CSV.</p>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'painel.php')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
        </div>

        <form method="get" action="<?= self::e(Urls::app($slug, 'relatorio.php')) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Relatório</label>
                    <select name="tipo" class="mt-1 rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <option value="visitas">Visitas e inspeções</option>
                        <option value="ncs">Não conformidades</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium text-gray-600">Início</label><input type="date" name="inicio" class="mt-1 rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-600">Fim</label><input type="date" name="fim" class="mt-1 rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Gerar relatório</button>
            </div>
        </form>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($relatorios as [$titulo, $descricao, $total, $link]): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900"><?= self::e($titulo) ?></h2>
                        <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-bold text-teal-800 ring-1 ring-teal-200"><?= (int) $total ?></span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500"><?= self::e($descricao) ?></p>
                    <a href="<?= self::e($link) ?>" class="mt-3 inline-flex rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-[11px] font-semibold text-teal-800 hover:bg-teal-100">Abrir lista</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array{colunas: list<string>, linhas: list<array<string, mixed>>} $dados
     */
    public static function relatorio(string $slug, array $app, array $identidade, string $tipo, string $inicio, string $fim, array $dados): void
    {
        Ui::abrirLayout($slug, $app, $identidade, 'Relatório de ' . ($tipo === 'ncs' ? 'não conformidades' : 'visitas'), 'relatorios.admin');
        $base = Urls::app($slug, 'relatorio.php?tipo=' . rawurlencode($tipo));
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Relatórios</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900"><?= $tipo === 'ncs' ? 'Relatório de não conformidades' : 'Relatório de visitas e inspeções' ?></h1>
                <p class="mt-1 text-sm text-gray-600"><?= self::e(($inicio !== '' ? 'De ' . $inicio : 'Início não informado') . ($fim !== '' ? ' até ' . $fim : '')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'relatorios.php')) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Relatórios</a>
                <a href="<?= self::e($base . '&inicio=' . rawurlencode($inicio) . '&fim=' . rawurlencode($fim) . '&formato=csv') ?>" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Exportar CSV</a>
                <button type="button" onclick="window.print()" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Imprimir / PDF</button>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase text-gray-500">
                        <?php foreach ($dados['colunas'] as $coluna): ?><th class="px-3 py-3"><?= self::e($coluna) ?></th><?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php if ($dados['linhas'] === []): ?>
                        <tr><td colspan="<?= count($dados['colunas']) ?>" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum registro no período.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($dados['linhas'] as $linha): ?>
                        <tr class="border-b border-gray-100">
                            <?php foreach ($dados['colunas'] as $coluna): ?>
                                <td class="px-3 py-2 text-gray-700"><?= self::e(self::formatarCelula($coluna, $linha)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php Ui::fecharLayout();
    }

    /** @param array<string, mixed> $linha */
    private static function formatarCelula(string $coluna, array $linha): string
    {
        $valor = $linha[$coluna] ?? '';
        if (str_contains($coluna, 'data') || str_contains($coluna, 'prazo')) {
            return self::data((string) $valor);
        }

        return (string) $valor;
    }

    private static function cabecalhos(string $modulo, bool $podeEscrever): string
    {
        $acoes = $podeEscrever && in_array($modulo, ['planos', 'visitas', 'ncs', 'validacao'], true)
            || in_array($modulo, ['planos', 'visitas', 'ncs'], true)
            ? '<th class="px-3 py-3">Ações</th>' : '';

        return match ($modulo) {
            'planos' => '<th class="px-3 py-3">Safra</th><th class="px-3 py-3">Título</th><th class="px-3 py-3">Agricultor</th><th class="px-3 py-3">Propriedade</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Versão</th>' . $acoes,
            'insumos_aprovados' => '<th class="px-3 py-3">Categoria</th><th class="px-3 py-3">Nome comercial</th><th class="px-3 py-3">Fabricante</th><th class="px-3 py-3">Princípio/composição</th><th class="px-3 py-3">Status</th>',
            'visitas' => '<th class="px-3 py-3">Data</th><th class="px-3 py-3">Propriedade</th><th class="px-3 py-3">Agricultor</th><th class="px-3 py-3">Roteiro</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Conformidade</th>' . $acoes,
            'roteiros' => '<th class="px-3 py-3">Código</th><th class="px-3 py-3">Nome</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Versão</th><th class="px-3 py-3">Itens</th><th class="px-3 py-3">Ações</th>',
            'ncs' => '<th class="px-3 py-3">Data visita</th><th class="px-3 py-3">Produtor</th><th class="px-3 py-3">Propriedade</th><th class="px-3 py-3">Gravidade</th><th class="px-3 py-3">Prazo</th><th class="px-3 py-3">Status</th>' . $acoes,
            'validacao' => '<th class="px-3 py-3">Insumo</th><th class="px-3 py-3">Fornecedor</th><th class="px-3 py-3">Estoque</th><th class="px-3 py-3">Validade</th><th class="px-3 py-3">Situação</th>' . $acoes,
            'cnpo' => '<th class="px-3 py-3">Produtor</th><th class="px-3 py-3">Entidade</th><th class="px-3 py-3">Tipo</th><th class="px-3 py-3">Município/UF</th><th class="px-3 py-3">Situação</th>',
            default => '<th class="px-3 py-3">Registro</th>',
        };
    }

    /** @param array<string, mixed> $linha */
    private static function linha(string $slug, string $modulo, array $linha, bool $podeEscrever): string
    {
        $url = Urls::app($slug, 'modulo.php?modulo=' . rawurlencode($modulo));
        $id = (int) ($linha['id'] ?? 0);
        $acoes = '';
        $detalhePlano = '<a class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-800" href="'
            . self::e($url . '&ver=' . $id) . '">Ver detalhes</a>';
        $verVisita = '<a class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-800" href="'
            . self::e(Urls::app($slug, 'visita.php?id=' . $id)) . '">Ver</a>';
        $realizarVisita = '<a class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-800" href="'
            . self::e(Urls::app($slug, 'realizar-visita.php?id=' . $id)) . '">Realizar</a>';
        $verNc = '<a class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[10px] font-semibold text-teal-800" href="'
            . self::e(Urls::app($slug, 'nc.php?id=' . $id)) . '">Acompanhar</a>';

        if ($modulo === 'visitas') {
            $acoes .= $verVisita;
            if ($podeEscrever && ($linha['status'] ?? '') === 'Agendada') {
                $acoes .= $realizarVisita
                    . self::formAcao($url, $id, 'cancelar', 'Cancelar', 'red');
            }
        } elseif ($modulo === 'ncs') {
            $acoes .= $verNc;
            if ($podeEscrever) {
                $acoes .= self::formAcao($url, $id, 'abrir', 'Abrir', 'gray')
                    . self::formAcao($url, $id, 'corrigir', 'Em correção', 'amber')
                    . self::formAcao($url, $id, 'resolver', 'Resolver', 'green')
                    . self::formAcao($url, $id, 'cancelar', 'Cancelar', 'red');
            }
        } elseif ($podeEscrever) {
            $acoes = match ($modulo) {
                'planos' => self::formAcao($url, $id, 'aprovar', 'Aprovar', 'green')
                    . self::formAcao($url, $id, 'reprovar', 'Reprovar', 'red')
                    . self::formAcao($url, $id, 'revisao', 'Revisão', 'amber'),
                'validacao' => self::formAcao($url, $id, 'aprovar', 'Aprovar', 'green')
                    . self::formAcao($url, $id, 'reprovar', 'Reprovar', 'red'),
                default => '',
            };
        }

        return match ($modulo) {
            'planos' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e((string) ($linha['ano_safra'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['titulo'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['agricultor'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['propriedade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((string) ($linha['status'] ?? '')) . '</td>'
                . '<td class="px-3 py-2">' . (int) ($linha['versao'] ?? 1) . '</td>'
                . '<td class="px-3 py-2">' . ($podeEscrever ? $detalhePlano . $acoes : $detalhePlano) . '</td></tr>',
            'insumos_aprovados' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e((string) ($linha['categoria'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) (($linha['nome_comercial'] ?? '') !== '' ? $linha['nome_comercial'] : ($linha['nome'] ?? '—'))) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['fabricante'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['principio'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((int) ($linha['permitido'] ?? 0) === 1 ? 'Permitido' : 'Restrito') . '</td></tr>',
            'visitas' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e(self::data((string) ($linha['data_visita'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['propriedade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['agricultor'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['roteiro'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((string) ($linha['status'] ?? '')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['conformidade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . $acoes . '</td></tr>',
            'roteiros' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e((string) ($linha['codigo'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['nome'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['tipo'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . (int) ($linha['versao'] ?? 1) . '</td>'
                . '<td class="px-3 py-2">' . (int) ($linha['itens'] ?? 0) . '</td>'
                . '<td class="px-3 py-2"><a class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800" href="'
                . self::e($url . '&ver=' . $id) . '">Ver</a>'
                . ($podeEscrever
                    ? '<a class="ml-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800" href="'
                      . self::e(Urls::app($slug, 'roteiro.php?id=' . $id)) . '">Editar</a>'
                      . '<form method="post" action="' . self::e(Urls::app($slug, 'roteiro.php')) . '" class="inline" onsubmit="return confirm(\'Excluir este roteiro?\')">'
                      . '<input type="hidden" name="csrf" value="' . self::e(Csrf::token()) . '"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="' . $id . '">'
                      . '<button type="submit" class="ml-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">Excluir</button></form>'
                    : '')
                . '</td></tr>',
            'ncs' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e(self::data((string) ($linha['data_visita'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['produtor'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['propriedade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((string) ($linha['gravidade'] ?? '')) . '</td>'
                . '<td class="px-3 py-2">' . self::e(self::data((string) ($linha['prazo_correcao'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((string) ($linha['status'] ?? '')) . '</td>'
                . '<td class="px-3 py-2">' . $acoes . '</td></tr>',
            'validacao' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e((string) ($linha['nome'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['fornecedor'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . (int) ($linha['quantidade_estoque'] ?? 0) . '</td>'
                . '<td class="px-3 py-2">' . self::e(self::data((string) ($linha['data_validade'] ?? ''))) . '</td>'
                . '<td class="px-3 py-2">' . self::badge((int) ($linha['aprovado_organico'] ?? 0) === 1 ? 'Conforme' : ((int) ($linha['aprovado_organico'] ?? 0) === 0 ? 'Não conforme' : 'Pendente')) . '</td>'
                . ($podeEscrever ? '<td class="px-3 py-2">' . $acoes . '</td>' : '') . '</tr>',
            'cnpo' => '<tr class="border-b border-gray-100"><td class="px-3 py-2">' . self::e((string) ($linha['nome_produtor'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['entidade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['tipo_entidade'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['cidade'] ?? '—') . '/' . (string) ($linha['uf'] ?? '—')) . '</td>'
                . '<td class="px-3 py-2">' . self::e((string) ($linha['situacao'] ?? '—')) . '</td></tr>',
            default => '<tr><td class="px-3 py-3">—</td></tr>',
        };
    }

    private static function formAcao(string $url, int $id, string $acao, string $rotulo, string $cor): string
    {
        $classes = match ($cor) {
            'green' => 'border-green-200 bg-green-50 text-green-800 hover:bg-green-100',
            'red' => 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100',
            'gray' => 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100',
            default => 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100',
        };

        return '<form method="post" action="' . self::e($url) . '" class="inline">'
            . '<input type="hidden" name="csrf" value="' . self::e(Csrf::token()) . '">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="acao" value="' . self::e($acao) . '">'
            . '<button type="submit" class="rounded-lg border px-2 py-1 text-[10px] font-semibold ' . $classes . '">' . self::e($rotulo) . '</button></form>';
    }

    private static function badge(string $valor): string
    {
        $classes = match (mb_strtolower($valor)) {
            'aprovado', 'conforme', 'permitido', 'resolvida', 'realizada', 'ativa' => 'bg-green-50 text-green-800 ring-green-200',
            'reprovado', 'não conforme', 'restrito', 'cancelada', 'crítica', 'alta' => 'bg-red-50 text-red-700 ring-red-200',
            'em análise', 'em correção', 'reagendada', 'revisão necessária', 'média' => 'bg-amber-50 text-amber-800 ring-amber-200',
            default => 'bg-gray-50 text-gray-700 ring-gray-200',
        };

        return '<span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' . $classes . '">' . self::e($valor !== '' ? $valor : '—') . '</span>';
    }

    /**
     * Consulta completa do plano de manejo + pareceres e sugestões.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $plano
     */
    public static function plano(
        string $slug,
        array $app,
        array $identidade,
        array $plano,
        ?string $flash = null
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Plano de Manejo — ' . (string) ($plano['titulo'] ?? ''), 'planos.analise');
        $podeEscrever = SocialModulos::podeEscrever($identidade);
        $url = Urls::app($slug, 'modulo.php?modulo=planos');
        $status = (string) ($plano['status'] ?? 'Rascunho');
        $pareceres = (array) ($plano['pareceres'] ?? []);
        $agricultor = trim((string) ($plano['associado_nome'] ?? '') . ' ' . (string) ($plano['associado_sobrenome'] ?? ''));
        $secoes = [
            'Caracterização da unidade' => [
                ['Agricultor', $agricultor],
                ['Propriedade', (string) ($plano['propriedade_nome'] ?? '—')],
                ['Safra', (string) ($plano['ano_safra'] ?? '—')],
                ['Título', (string) ($plano['titulo'] ?? '—')],
                ['Área total (ha)', self::numero($plano['area_total_ha'] ?? null)],
                ['Área orgânica (ha)', self::numero($plano['area_organica_ha'] ?? null)],
                ['Área em conversão (ha)', self::numero($plano['area_conversao_ha'] ?? null)],
                ['Topografia', (string) ($plano['topografia'] ?? '—')],
                ['Descrição do solo', (string) ($plano['descricao_solo'] ?? '—')],
                ['Fonte de água', (string) ($plano['fonte_agua'] ?? '—')],
                ['Clima', (string) ($plano['clima'] ?? '—')],
            ],
            'Práticas de manejo' => [
                ['Manejo de pragas', (string) ($plano['manejo_pragas'] ?? '—')],
                ['Manejo de doenças', (string) ($plano['manejo_doencas'] ?? '—')],
                ['Conservação do solo', (string) ($plano['praticas_conservacao'] ?? '—')],
                ['Rotação de culturas', (string) ($plano['rotacao_culturas'] ?? '—')],
                ['Compostagem', (string) ($plano['compostagem'] ?? '—')],
                ['Biofertilizantes', (string) ($plano['biofertilizantes'] ?? '—')],
                ['Adubação verde', (string) ($plano['adubacao_verde'] ?? '—')],
            ],
        ];

        $agroindustria = json_decode((string) ($plano['agroindustria_dados'] ?? ''), true);
        if (is_array($agroindustria) && $agroindustria !== []) {
            $rotulosAgro = [
                'nome_agroindustria' => 'Unidade de processamento',
                'produtos_processados' => 'Produtos processados',
                'boas_praticas_fabricacao' => 'Boas Práticas de Fabricação',
                'higienizacao' => 'Controle de higienização',
                'controle_pragas_unidade' => 'Controle de pragas (PPHO)',
                'rastreabilidade_lotes' => 'Rastreabilidade por lote',
                'insumos_processamento' => 'Insumos de processamento permitidos',
                'agua_processamento' => 'Água de processamento',
                'gestao_residuos' => 'Gestão de resíduos',
            ];
            $linhasAgro = [];
            foreach ($rotulosAgro as $campo => $rotulo) {
                $linhasAgro[] = [$rotulo, (string) ($agroindustria[$campo] ?? '—')];
            }
            $secoes['Agroindústria e processamento'] = $linhasAgro;
        }
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Planos de Manejo · Análise Técnica</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900"><?= self::e((string) ($plano['titulo'] ?? 'Plano de manejo')) ?></h1>
                <p class="mt-1 text-sm text-gray-600">Safra <?= self::e((string) ($plano['ano_safra'] ?? '—')) ?> · <?= self::e($agricultor) ?> · <?= self::e((string) ($plano['propriedade_nome'] ?? '—')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Planos</a>
                <button type="button" onclick="window.print()" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">🖨 Imprimir</button>
            </div>
        </div>

        <?php self::alertas($flash, null); ?>

        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status atual</span>
            <?= self::badge($status) ?>
            <span class="ml-auto text-xs text-gray-500">Versão <?= (int) ($plano['versao'] ?? 1) ?> · criado em <?= self::e(self::data((string) ($plano['created_at'] ?? ''))) ?></span>
        </div>

        <?php foreach ($secoes as $tituloSecao => $campos): ?>
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900"><?= self::e($tituloSecao) ?></h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($campos as [$rotuloSecao, $valorSecao]): ?>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-gray-500"><?= self::e($rotuloSecao) ?></dt>
                            <dd class="mt-0.5 whitespace-pre-line text-sm text-gray-800"><?= self::e($valorSecao !== '' ? $valorSecao : '—') ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>

        <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Planejamento de cultivos</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Cultura</th><th class="px-2 py-2">Área (ha)</th>
                            <th class="px-2 py-2">Semente</th><th class="px-2 py-2">Produção estimada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($plano['cultivos'])): ?>
                            <tr><td colspan="4" class="px-2 py-5 text-center text-xs text-gray-500">Nenhum cultivo registrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ((array) ($plano['cultivos'] ?? []) as $cultivo): ?>
                            <tr class="border-b border-gray-100">
                                <td class="px-2 py-2"><?= self::e((string) ($cultivo['cultura'] ?? '—')) ?> <?= self::e((string) ($cultivo['variedade'] ?? '')) ?></td>
                                <td class="px-2 py-2"><?= self::e(self::numero($cultivo['area_cultivada_ha'] ?? null)) ?></td>
                                <td class="px-2 py-2"><?= self::e((string) ($cultivo['tipo_semente'] ?? '—')) ?></td>
                                <td class="px-2 py-2"><?= self::e(self::numero($cultivo['producao_estimada_kg'] ?? null)) ?> kg</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!empty($plano['croqui_propriedade_url'])): ?>
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Croqui da propriedade</h2>
                <img src="<?= self::e((string) $plano['croqui_propriedade_url']) ?>" alt="Croqui da propriedade" class="mt-3 max-h-[420px] rounded-xl border border-gray-200">
            </section>
        <?php endif; ?>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Parecer e sugestões de ajuste</h2>
                <p class="mt-1 text-xs text-gray-500">Registre sua análise, sugestões ou a decisão técnica. Todo parecer fica no histórico.</p>
                <?php if ($podeEscrever): ?>
                    <form method="post" action="<?= self::e($url) ?>" class="mt-3 space-y-3">
                        <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                        <input type="hidden" name="acao" value="plano_parecer">
                        <input type="hidden" name="id" value="<?= (int) ($plano['id'] ?? 0) ?>">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Decisão</label>
                            <select name="status" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                <option value="Sugestão">Sugestão / ajuste (mantém o status)</option>
                                <option value="Aprovado">Aprovar plano</option>
                                <option value="Revisão Necessária">Solicitar revisão / ajustes</option>
                                <option value="Reprovado">Reprovar plano</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Parecer técnico</label>
                            <textarea name="parecer" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Pontos de conformidade ou pendências encontradas..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Sugestões de ajuste / alteração</label>
                            <textarea name="sugestoes" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="O que o agricultor deve ajustar neste plano..."></textarea>
                        </div>
                        <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Registrar parecer</button>
                    </form>
                <?php else: ?>
                    <p class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-500">Seu perfil pode consultar o plano, mas não registrar parecer.</p>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Histórico de pareceres</h2>
                <?php if ($pareceres === []): ?>
                    <p class="mt-2 text-xs text-gray-500">Nenhum parecer registrado.</p>
                <?php endif; ?>
                <div class="mt-3 space-y-3">
                    <?php foreach ($pareceres as $parecer): ?>
                        <div class="rounded-xl border border-gray-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <?= self::badge((string) ($parecer['status'] ?? 'Sugestão')) ?>
                                <span class="text-[11px] text-gray-500"><?= self::e(self::data((string) ($parecer['created_at'] ?? ''))) ?></span>
                            </div>
                            <?php if (!empty($parecer['parecer'])): ?>
                                <p class="mt-2 whitespace-pre-line text-xs text-gray-700"><strong>Parecer:</strong> <?= self::e((string) $parecer['parecer']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($parecer['sugestoes'])): ?>
                                <p class="mt-1 whitespace-pre-line text-xs text-gray-700"><strong>Sugestões:</strong> <?= self::e((string) $parecer['sugestoes']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <?php Ui::fecharLayout();
    }

    private static function numero(mixed $valor): string
    {
        return $valor === null || $valor === '' ? '—' : number_format((float) $valor, 2, ',', '.');
    }

    private static function data(string $valor): string
    {
        $ts = $valor !== '' ? strtotime($valor) : false;

        return $ts !== false ? date('d/m/Y', $ts) : '—';
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
