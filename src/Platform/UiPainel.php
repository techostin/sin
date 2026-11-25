<?php

declare(strict_types=1);

namespace App\Platform;

/** Renderiza os painéis de entrada com identidade visual própria por sistema. */
final class UiPainel
{
    /** @var array<string, array<string, string>> */
    private const TEMAS = [
        'assoc' => [
            'hero' => 'from-emerald-800 via-green-700 to-teal-700',
            'badge' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'accent' => 'text-emerald-700',
        ],
        'admin' => [
            'hero' => 'from-indigo-800 via-violet-700 to-purple-700',
            'badge' => 'bg-indigo-50 text-indigo-800 ring-indigo-200',
            'accent' => 'text-indigo-700',
        ],
        'producao' => [
            'hero' => 'from-amber-700 via-orange-600 to-yellow-600',
            'badge' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'accent' => 'text-amber-700',
        ],
        'social' => [
            'hero' => 'from-teal-800 via-teal-700 to-emerald-700',
            'badge' => 'bg-teal-50 text-teal-800 ring-teal-200',
            'accent' => 'text-teal-700',
        ],
    ];

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $painel
     */
    public static function render(
        string $slug,
        array $app,
        array $identidade,
        array $painel,
        string $tema = 'assoc',
        ?string $flash = null
    ): void {
        $t = self::TEMAS[$tema] ?? self::TEMAS['assoc'];
        $titulo = (string) ($painel['titulo'] ?? 'Painel');
        $subtitulo = (string) ($painel['subtitulo'] ?? '');
        $cards = (array) ($painel['cards'] ?? []);
        $secoes = (array) ($painel['secoes'] ?? []);

        $chaveAtiva = $tema === 'producao' ? 'painel.producao' : ($tema === 'social' ? 'painel.gestor' : 'painel.assoc');
        Ui::abrirLayout($slug, $app, $identidade, $titulo, $chaveAtiva);
        ?>
        <div class="rounded-3xl bg-gradient-to-br <?= self::e($t['hero']) ?> p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/80">SINPORG · <?= self::e((string) ($app['curto'] ?? '')) ?></p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl"><?= self::e($titulo) ?></h1>
                    <p class="mt-1 text-sm text-white/85"><?= self::e($subtitulo) ?></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if ($tema === 'producao'): ?>
                        <a href="<?= self::e(Urls::app($slug, 'registro.php?recurso=atividades&acao=novo')) ?>"
                           class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">+ Registrar atividade</a>
                    <?php elseif ($tema === 'assoc'): ?>
                        <a href="<?= self::e(Urls::app($slug, 'index.php?funcao=' . rawurlencode('associados.quadro'))) ?>"
                           class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-50">Abrir quadro</a>
                    <?php endif; ?>
                    <a href="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
                       class="rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white hover:bg-white/10">Meu perfil</a>
                </div>
            </div>
        </div>

        <?php if ($flash !== null && $flash !== ''): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($painel['aviso']) && is_array($painel['aviso'])): ?>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div>
                    <p class="font-semibold"><?= self::e((string) ($painel['aviso']['titulo'] ?? 'Atenção')) ?></p>
                    <p class="mt-0.5 text-xs"><?= self::e((string) ($painel['aviso']['texto'] ?? '')) ?></p>
                </div>
                <?php if (!empty($painel['aviso']['link'])): ?>
                    <a href="<?= self::e((string) $painel['aviso']['link']) ?>"
                       class="rounded-full bg-amber-700 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-800">
                        <?= self::e((string) ($painel['aviso']['rotulo'] ?? 'Abrir')) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($cards as $indice => $card): ?>
                <?php
                $rotulo = (string) ($card[0] ?? '');
                $valor = $card[1] ?? '';
                $icone = (string) ($card[2] ?? '•');
                $link = (string) ($card[3] ?? '#');
                ?>
                <a href="<?= self::e($link) ?>" class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-gray-50 text-lg" aria-hidden="true"><?= $icone ?></span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"><?= str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <p class="mt-3 text-2xl font-bold text-gray-900"><?= self::e((string) $valor) ?></p>
                    <p class="mt-0.5 text-xs font-medium text-gray-600"><?= self::e($rotulo) ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <?php foreach ($secoes as $secao): ?>
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900"><?= self::e((string) ($secao['titulo'] ?? '')) ?></h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                                    <?php foreach ((array) ($secao['colunas'] ?? []) as $coluna): ?>
                                        <th class="px-2 py-2"><?= self::e(self::rotulo((string) $coluna)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($secao['linhas'])): ?>
                                    <tr><td colspan="<?= count((array) ($secao['colunas'] ?? [])) ?>" class="px-2 py-5 text-center text-xs text-gray-500"><?= self::e((string) ($secao['vazio'] ?? 'Nenhum registro.')) ?></td></tr>
                                <?php endif; ?>
                                <?php foreach ((array) ($secao['linhas'] ?? []) as $linha): ?>
                                    <tr class="border-b border-gray-100 last:border-0">
                                        <?php foreach ((array) ($secao['colunas'] ?? []) as $coluna): ?>
                                            <td class="px-2 py-2 text-gray-700"><?= self::e((string) ($linha[$coluna] ?? '—')) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php Ui::fecharLayout();
    }

    /**
     * Modal "Inspeções Agendadas" do agricultor: confirmar ou recusar a data.
     *
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $visitas
     */
    public static function modalVisitasAgricultor(string $slug, array $identidade, array $visitas): void
    {
        if ($visitas === []) {
            return;
        }
        ?>
        <div id="modal-visita-agricultor" class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 rounded-t-2xl bg-gradient-to-r from-amber-700 to-orange-600 px-5 py-4 text-white">
                    <div>
                        <h2 class="text-lg font-bold">Inspeções Agendadas</h2>
                        <p class="mt-1 text-xs text-amber-50">Confirme que está ciente da data ou informe que não poderá comparecer.</p>
                    </div>
                    <button type="button" class="rounded-lg px-2 py-1 text-xl leading-none text-white/90 hover:bg-white/10"
                            onclick="sinporgFecharVisitaAgricultor()" aria-label="Fechar">×</button>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <?php foreach ($visitas as $visita): ?>
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900"><?= self::e((string) ($visita['propriedade_nome'] ?? 'Propriedade')) ?></p>
                                    <p class="text-xs text-gray-500"><?= self::e((string) ($visita['roteiro_nome'] ?? 'Roteiro')) ?> · <?= self::e(trim((string) ($visita['tecnico_nome'] ?? '')) !== '' ? (string) $visita['tecnico_nome'] : 'Equipe técnica') ?></p>
                                </div>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200">
                                    <?= self::e(self::data((string) ($visita['data_visita'] ?? ''))) ?> · <?= self::e(substr((string) ($visita['hora_inicio'] ?? ''), 0, 5)) ?>
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="post" action="<?= self::e(Urls::app($slug, 'painel.php')) ?>">
                                    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                    <input type="hidden" name="acao" value="visita_confirmar">
                                    <input type="hidden" name="visita_id" value="<?= (int) ($visita['id'] ?? 0) ?>">
                                    <button type="submit" class="rounded-full bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800">✓ Estou ciente</button>
                                </form>
                                <details class="w-full">
                                    <summary class="inline-flex cursor-pointer rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Não poderei nesta data</summary>
                                    <form method="post" action="<?= self::e(Urls::app($slug, 'painel.php')) ?>" class="mt-2 rounded-xl bg-gray-50 p-3">
                                        <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                        <input type="hidden" name="acao" value="visita_recusar">
                                        <input type="hidden" name="visita_id" value="<?= (int) ($visita['id'] ?? 0) ?>">
                                        <label class="block text-[10px] font-medium text-gray-600">Motivo</label>
                                        <textarea name="motivo" required maxlength="500" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs" placeholder="Ex.: outro compromisso na data"></textarea>
                                        <button type="submit" class="mt-2 rounded-full bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800">Enviar e solicitar reagendamento</button>
                                    </form>
                                </details>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <script>
        document.body.classList.add('overflow-hidden');
        function sinporgFecharVisitaAgricultor() {
            var modal = document.getElementById('modal-visita-agricultor');
            if (!modal) { return; }
            modal.remove();
            document.body.classList.remove('overflow-hidden');
        }
        </script>
        <?php
    }

    private static function rotulo(string $campo): string
    {
        $mapa = [
            'associado' => 'Associado', 'referencia' => 'Referência', 'vencimento' => 'Vencimento',
            'valor' => 'Valor', 'situacao' => 'Situação', 'nome' => 'Nome', 'dia' => 'Dia',
            'titulo' => 'Título', 'inicio' => 'Início', 'numero' => 'Número', 'submetida' => 'Submetida',
            'data' => 'Data', 'tipo' => 'Tipo', 'produto' => 'Produto', 'lote' => 'Lote', 'auditoria' => 'Auditoria',
        ];

        return $mapa[$campo] ?? mb_convert_case(str_replace('_', ' ', $campo), MB_CASE_TITLE, 'UTF-8');
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    public static function modalPlanoObrigatorio(string $slug, bool $semPropriedade = false): void
    {
        ?>
        <div id="modal-plano-obrigatorio" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">Produção do Agricultor</p>
                        <h2 class="mt-1 text-xl font-bold text-gray-900">Plano de Manejo é obrigatório</h2>
                    </div>
                    <button type="button" onclick="sinporgFecharModalPlanoObrigatorio()" aria-label="Fechar"
                            class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-700">×</button>
                </div>
                <p class="mt-2 text-sm text-gray-600">
                    Independentemente da sua área de produção (vegetal, animal ou agroindústria), você precisa manter um
                    plano de manejo enviado para análise técnica.
                </p>
                <?php if ($semPropriedade): ?>
                    <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        Você ainda não possui propriedade cadastrada. Cadastre uma propriedade e depois preencha o plano.
                    </p>
                <?php endif; ?>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?= self::e(Urls::app($slug, 'meu-plano.php')) ?>"
                       class="rounded-full bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Preencher meu plano</a>
                    <?php if ($semPropriedade): ?>
                        <a href="<?= self::e(Urls::app($slug, 'registro.php?recurso=propriedades&acao=novo')) ?>"
                           class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cadastrar propriedade</a>
                    <?php endif; ?>
                </div>
                <p class="mt-3 text-[11px] text-gray-400">Este aviso permanece até o envio do plano para análise.</p>
            </div>
        </div>
        <script>
        document.body.classList.add('overflow-hidden');
        function sinporgFecharModalPlanoObrigatorio() {
            var modal = document.getElementById('modal-plano-obrigatorio');
            if (modal) { modal.remove(); }
            document.body.classList.remove('overflow-hidden');
        }
        </script>
        <?php
    }

    private static function data(string $valor): string
    {
        $ts = $valor !== '' ? strtotime($valor) : false;

        return $ts !== false ? date('d/m/Y', $ts) : '—';
    }
}
