<?php

declare(strict_types=1);

namespace App\Platform;

/** Tela "Meu plano de manejo" do agricultor. */
final class UiPlanoManejo
{
    /**
     * Lista os planos do próprio agricultor, com filtros por status.
     *
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $planos
     */
    public static function lista(
        string $slug,
        array $app,
        array $identidade,
        array $planos,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Planos de Manejo', 'producao.meu-plano', true);
        $url = Urls::app($slug, 'meu-plano.php');
        $agricultor = trim((string) ($identidade['nome'] ?? '') . ' ' . (string) ($identidade['sobrenome'] ?? ''));
        $contagens = ['Todos' => count($planos), 'Aprovado' => 0, 'Em Análise' => 0];
        foreach ($planos as $plano) {
            $statusPlano = (string) ($plano['status'] ?? '');
            if (isset($contagens[$statusPlano])) {
                $contagens[$statusPlano]++;
            }
        }
        ?>
        <div class="rounded-3xl bg-gradient-to-br from-amber-700 via-orange-600 to-yellow-600 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-50">Produção · Obrigatório</p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Planos de Manejo Orgânico</h1>
                    <p class="mt-1 text-sm text-amber-50">Acompanhamento e análise dos planos de manejo por safra.</p>
                </div>
                <a href="<?= self::e($url . '?modo=novo') ?>"
                   class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">+ Novo plano</a>
            </div>
        </div>

        <?php if ($flash !== null && $flash !== ''): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div>
        <?php endif; ?>
        <?php if ($erro !== null && $erro !== ''): ?>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
        <?php endif; ?>

        <div class="mt-5 rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900"><span class="text-teal-700">▦</span> Lista de Planos por Safra</span>
                </div>
                <div class="flex flex-wrap gap-1.5" id="filtros-planos">
                    <?php foreach (['Todos', 'Aprovado', 'Em Análise'] as $filtro): ?>
                        <button type="button" data-filtro="<?= self::e($filtro) ?>"
                                class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition <?= $filtro === 'Todos' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
                            <?= self::e($filtro === 'Aprovado' ? 'Aprovados' : $filtro) ?>
                            <span class="ml-1 rounded-full bg-white/80 px-1.5 py-0.5 text-[10px]"><?= (int) $contagens[$filtro] ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="overflow-x-auto p-3">
                <table id="planos-datatable" class="display w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Safra</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Propriedade</th>
                            <th class="px-3 py-3">Agricultor</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Versão</th>
                            <th class="px-3 py-3">Data Criação</th>
                            <th class="px-3 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($planos as $plano): ?>
                            <?php
                            $id = (int) ($plano['id'] ?? 0);
                            $statusPlano = (string) ($plano['status'] ?? 'Rascunho');
                            $editavel = PlanoManejo::podeEditar($plano);
                            ?>
                            <tr>
                                <td class="px-3 py-3 font-medium text-gray-900"><?= self::e((string) ($plano['ano_safra'] ?? '—')) ?></td>
                                <td class="px-3 py-3 text-gray-700">Plano</td>
                                <td class="px-3 py-3 text-gray-700"><?= self::e((string) ($plano['propriedade_nome'] ?? '—')) ?></td>
                                <td class="px-3 py-3 text-gray-700"><?= self::e($agricultor) ?></td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 <?= self::classeStatus($statusPlano) ?>"><?= self::e($statusPlano) ?></span></td>
                                <td class="px-3 py-3 text-gray-600"><?= (int) ($plano['versao'] ?? 1) ?></td>
                                <td class="px-3 py-3 text-gray-600" data-order="<?= self::e((string) ($plano['created_at'] ?? '')) ?>">
                                    <?= self::e(($plano['created_at'] ?? '') !== '' ? date('d/m/Y', strtotime((string) $plano['created_at'])) : '—') ?>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <a href="<?= self::e($url . '?modo=ver&id=' . $id) ?>"
                                           class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800">Ver</a>
                                        <?php if ($editavel): ?>
                                            <a href="<?= self::e($url . '?modo=editar&id=' . $id) ?>"
                                               class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Editar</a>
                                            <form method="post" action="<?= self::e($url) ?>" onsubmit="return confirm('Excluir este plano?');">
                                                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">Excluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($planos === []): ?>
                    <div class="mt-2 rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500">
                        Nenhum plano encontrado.
                        <a href="<?= self::e($url . '?modo=novo') ?>" class="ml-1 font-semibold text-amber-700 underline">Criar meu plano de manejo</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script src="<?= self::e(Urls::asset('vendor/datatables.min.js')) ?>"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof DataTable === 'undefined') { return; }
            var tabela = new DataTable('#planos-datatable', {
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
                order: [[0, 'desc']],
                autoWidth: false,
                columnDefs: [{ targets: -1, orderable: false, searchable: false }],
                language: {
                    emptyTable: 'Nenhum plano encontrado.',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ planos',
                    infoEmpty: 'Nenhum plano',
                    infoFiltered: '(filtrado de _MAX_ registros)',
                    lengthMenu: 'Mostrar _MENU_ planos',
                    search: 'Buscar:',
                    zeroRecords: 'Nenhum plano encontrado',
                    paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' }
                }
            });
            var botoes = document.querySelectorAll('#filtros-planos [data-filtro]');
            botoes.forEach(function (botao) {
                botao.addEventListener('click', function () {
                    var filtro = this.getAttribute('data-filtro');
                    botoes.forEach(function (b) {
                        b.classList.remove('border-amber-300', 'bg-amber-50', 'text-amber-800');
                        b.classList.add('border-gray-200', 'bg-white', 'text-gray-600');
                    });
                    this.classList.remove('border-gray-200', 'bg-white', 'text-gray-600');
                    this.classList.add('border-amber-300', 'bg-amber-50', 'text-amber-800');
                    tabela.column(4).search(filtro === 'Todos' ? '' : '^' + filtro + '$', true).draw();
                });
            });
        });
        </script>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed>|null $plano
     * @param list<array<string, mixed>> $propriedades
     */
    public static function render(
        string $slug,
        array $app,
        array $identidade,
        ?array $plano,
        array $propriedades,
        ?string $flash,
        ?string $erro,
        bool $visualizar = false
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, 'Meu plano de manejo', 'producao.meu-plano', true);
        $podeEditar = !$visualizar && ($plano === null || PlanoManejo::podeEditar($plano));
        $status = $plano === null ? 'Sem plano' : (string) ($plano['status'] ?? 'Rascunho');
        $url = Urls::app($slug, 'meu-plano.php');
        $areas = ProducaoAreas::doUsuario($identidade);
        $ehAgro = in_array('agroindustria', $areas, true) && !in_array('vegetal', $areas, true);
        $cultivos = array_values((array) ($plano['cultivos'] ?? []));
        if ($cultivos === []) {
            $cultivos = [['cultura' => '', 'variedade' => '', 'area_cultivada_ha' => '', 'producao_estimada_kg' => '', 'tipo_semente' => 'Orgânica Certificada']];
        }
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">Produção · Obrigatório</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl"><?= $visualizar ? 'Visualizar plano de manejo' : ($plano === null ? 'Novo plano de manejo' : 'Editar plano de manejo') ?></h1>
                <p class="mt-1 text-sm text-gray-600">O plano é obrigatório para todas as áreas de produção: vegetal, animal e agroindústria.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e($url) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Planos</a>
                <?php if ($plano !== null && !$podeEditar): ?>
                    <?php if (!$visualizar): ?>
                        <a href="<?= self::e($url . '?modo=novo') ?>" class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">+ Novo plano</a>
                    <?php endif; ?>
                    <button type="button" onclick="window.print()" class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">🖨 Imprimir</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash !== null && $flash !== ''): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e($flash) ?></div>
        <?php endif; ?>
        <?php if ($erro !== null && $erro !== ''): ?>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
        <?php endif; ?>

        <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Situação</span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= self::classeStatus($status) ?>"><?= self::e($status) ?></span>
            <span class="text-xs text-gray-500"><?= self::e($ehAgro ? 'Plano específico de agroindústria.' : 'O envio para análise é feito após preencher os dados e os cultivos.') ?></span>
        </div>

        <?php if ($ehAgro): $agro = json_decode((string) ($plano['agroindustria_dados'] ?? ''), true); $agro = is_array($agro) ? $agro : []; ?>
            <form method="post" action="<?= self::e($url) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="acao" value="salvar">
                <?php if ($plano !== null): ?><input type="hidden" name="id" value="<?= (int) ($plano['id'] ?? 0) ?>"><?php endif; ?>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="block text-xs font-medium text-gray-600">Propriedade *</label>
                        <select name="propriedade_id" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Selecione...</option>
                            <?php foreach ($propriedades as $propriedade): ?><option value="<?= (int) $propriedade['id'] ?>" <?= (int) ($plano['propriedade_id'] ?? 0) === (int) $propriedade['id'] ? 'selected' : '' ?>><?= self::e((string) $propriedade['propriedade']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div><label class="block text-xs font-medium text-gray-600">Safra *</label><input name="ano_safra" required value="<?= self::e((string) ($plano['ano_safra'] ?? (date('Y') . '/' . (date('Y') + 1)))) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600">Título</label><input name="titulo" value="<?= self::e((string) ($plano['titulo'] ?? 'Plano de Manejo da Agroindústria')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></div>
                </div>

                <h2 class="mt-6 text-sm font-semibold text-gray-900">Dados da agroindústria e processamento</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <?php foreach ([
                        'nome_agroindustria' => 'Nome da unidade de processamento',
                        'produtos_processados' => 'Produtos processados',
                        'boas_praticas_fabricacao' => 'Boas Práticas de Fabricação (BPF)',
                        'higienizacao' => 'Controle de higienização',
                        'controle_pragas_unidade' => 'Controle de pragas da unidade (PPHO)',
                        'rastreabilidade_lotes' => 'Rastreabilidade por lote (matéria-prima → processamento → expedição)',
                        'insumos_processamento' => 'Aditivos/ingredientes permitidos (IN 46/2011)',
                        'agua_processamento' => 'Água utilizada no processamento (potabilidade)',
                        'gestao_residuos' => 'Gestão de resíduos',
                    ] as $campo => $rotulo): ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-600"><?= self::e($rotulo) ?></label>
                            <textarea name="agroindustria[<?= self::e($campo) ?>]" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($agro[$campo] ?? '')) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5"><label class="block text-xs font-medium text-gray-600">Observações</label><textarea name="observacoes" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($plano['observacoes'] ?? '')) ?></textarea></div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="submit" name="modo" value="rascunho" formnovalidate class="rounded-full border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Salvar rascunho</button>
                    <button type="submit" name="modo" value="submeter" class="rounded-full bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Enviar plano para análise</button>
                </div>
            </form>
            <?php Ui::fecharLayout(); return; ?>
        <?php endif; ?>

        <?php if ($propriedades === []): ?>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                Nenhuma propriedade vinculada ao seu cadastro. Cadastre uma propriedade em
                <a class="font-semibold underline" href="<?= self::e(Urls::app($slug, 'registro.php?recurso=propriedades')) ?>">Propriedades</a>
                antes de preencher o plano de manejo.
            </div>
        <?php elseif ($podeEditar): ?>
            <form method="post" action="<?= self::e($url) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                <input type="hidden" name="acao" value="salvar">
                <?php if ($plano !== null): ?><input type="hidden" name="id" value="<?= (int) ($plano['id'] ?? 0) ?>"><?php endif; ?>

                <style>
                    .wizard-step { display: none; }
                    .wizard-step.active { display: block; }
                    .wizard-item .wizard-circle { width: 2.35rem; height: 2.35rem; line-height: 2.35rem; margin: 0 auto; border-radius: 9999px; background: #e5e7eb; color: #6b7280; font-weight: 700; text-align: center; position: relative; z-index: 2; }
                    .wizard-item.active .wizard-circle, .wizard-item.completed .wizard-circle { background: #b45309; color: #fff; }
                    .wizard-item.active .wizard-label { color: #92400e; font-weight: 700; }
                    .wizard-item .wizard-label { font-size: 11px; color: #6b7280; margin-top: .35rem; text-align: center; }
                </style>
                <div id="wizard-steps" class="mb-6 grid grid-cols-5 gap-1">
                    <?php foreach (['Identificação', 'Propriedade', 'Práticas', 'Cultivos', 'Finalização'] as $indice => $etapa): ?>
                        <div class="wizard-item <?= $indice === 0 ? 'active' : '' ?>" data-step="<?= $indice + 1 ?>">
                            <div class="wizard-circle"><?= $indice + 1 ?></div>
                            <div class="wizard-label"><?= self::e($etapa) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="wizard-step active" data-step="1">
                    <h2 class="text-sm font-semibold text-gray-900">Etapa 1 · Informações básicas</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Propriedade *</label>
                            <select name="propriedade_id" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                <option value="">Selecione sua propriedade...</option>
                                <?php
                                $idsPropriedades = array_map(static fn(array $p): int => (int) $p['id'], $propriedades);
                                $propriedadeAtual = (int) ($plano['propriedade_id'] ?? 0);
                                if ($propriedadeAtual > 0 && !in_array($propriedadeAtual, $idsPropriedades, true)):
                                    ?>
                                    <option value="<?= $propriedadeAtual ?>" selected><?= self::e((string) ($plano['propriedade_nome'] ?? 'Propriedade atual')) ?> (atual)</option>
                                <?php endif; ?>
                                <?php foreach ($propriedades as $propriedade): ?>
                                    <option value="<?= (int) $propriedade['id'] ?>" <?= $propriedadeAtual === (int) $propriedade['id'] ? 'selected' : '' ?>>
                                        <?= self::e((string) $propriedade['propriedade']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Agricultor responsável</label>
                            <input value="<?= self::e(trim((string) ($identidade['nome'] ?? '') . ' ' . (string) ($identidade['sobrenome'] ?? ''))) ?>" readonly class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600">Safra *</label>
                            <input name="ano_safra" required value="<?= self::e((string) ($plano['ano_safra'] ?? (date('Y') . '/' . (date('Y') + 1)))) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="2026/2027">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600">Título do plano</label>
                            <input name="titulo" value="<?= self::e((string) ($plano['titulo'] ?? 'Plano de Manejo')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Ex.: Plano Anual de Produção Orgânica">
                        </div>
                    </div>
                </div>

                <div class="wizard-step" data-step="2">
                    <h2 class="text-sm font-semibold text-gray-900">Etapa 2 · Caracterização da área</h2>
                    <p class="mt-1 text-xs text-gray-500">Informe as áreas e as condições físicas da propriedade.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <?php foreach ([
                        'area_total_ha' => 'Área total (ha)',
                        'area_organica_ha' => 'Área orgânica (ha)',
                        'area_conversao_ha' => 'Área em conversão (ha)',
                        'area_convencional_ha' => 'Área convencional (ha)',
                    ] as $campo => $rotulo): ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-600"><?= self::e($rotulo) ?></label>
                            <input name="<?= self::e($campo) ?>" type="number" step="0.01" value="<?= self::e((string) ($plano[$campo] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Topografia</label>
                        <select name="topografia" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <?php foreach (['Plana', 'Ondulada', 'Montanhosa', 'Irregular'] as $opcao): ?>
                                <option value="<?= self::e($opcao) ?>" <?= (string) ($plano['topografia'] ?? '') === $opcao ? 'selected' : '' ?>><?= self::e($opcao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                </div>

                <div class="wizard-step" data-step="3">
                    <h2 class="text-sm font-semibold text-gray-900">Etapa 3 · Práticas de manejo e conservação</h2>
                    <p class="mt-1 text-xs text-gray-500">Descreva detalhadamente as práticas adotadas na propriedade.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <?php foreach ([
                        'descricao_solo' => 'Descrição do solo',
                        'fonte_agua' => 'Fontes de água',
                        'clima' => 'Clima',
                        'manejo_pragas' => 'Manejo de pragas',
                        'manejo_doencas' => 'Manejo de doenças',
                        'praticas_conservacao' => 'Práticas de conservação',
                        'rotacao_culturas' => 'Rotação de culturas',
                        'compostagem' => 'Compostagem',
                        'biofertilizantes' => 'Biofertilizantes',
                        'adubacao_verde' => 'Adubação verde / solo',
                    ] as $campo => $rotulo): ?>
                        <div>
                            <label class="block text-xs font-medium text-gray-600"><?= self::e($rotulo) ?></label>
                            <textarea name="<?= self::e($campo) ?>" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($plano[$campo] ?? '')) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <div class="wizard-step" data-step="4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900">Etapa 4 · Planejamento de cultivos</h2>
                        <button type="button" onclick="sinporgAddCultivo()" class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">+ Adicionar cultura</button>
                    </div>
                <div id="cultivos" class="mt-3 space-y-2">
                    <?php foreach ($cultivos as $indice => $cultivo): ?>
                        <div class="cultivo-row grid gap-2 rounded-xl border border-gray-200 p-3 sm:grid-cols-6">
                            <input name="cultivos[<?= (int) $indice ?>][cultura]" value="<?= self::e((string) ($cultivo['cultura'] ?? '')) ?>" placeholder="Cultura" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-2">
                            <input name="cultivos[<?= (int) $indice ?>][variedade]" value="<?= self::e((string) ($cultivo['variedade'] ?? '')) ?>" placeholder="Variedade" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                            <input name="cultivos[<?= (int) $indice ?>][area_cultivada_ha]" value="<?= self::e((string) ($cultivo['area_cultivada_ha'] ?? '')) ?>" type="number" step="0.01" placeholder="Área (ha)" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                            <input name="cultivos[<?= (int) $indice ?>][producao_estimada_kg]" value="<?= self::e((string) ($cultivo['producao_estimada_kg'] ?? '')) ?>" type="number" step="0.01" placeholder="Estimativa (kg)" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                            <select name="cultivos[<?= (int) $indice ?>][tipo_semente]" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                                <?php foreach (['Orgânica Certificada', 'Convencional Não Tratada', 'Crioula', 'Própria'] as $semente): ?>
                                    <option value="<?= self::e($semente) ?>" <?= (string) ($cultivo['tipo_semente'] ?? '') === $semente ? 'selected' : '' ?>><?= self::e($semente) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="this.closest('.cultivo-row').remove()" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1.5 text-xs font-semibold text-red-700">Remover</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <div class="wizard-step" data-step="5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-gray-900">Etapa 5 · Revisão final</h2>
                        <button type="button" id="wizard-refresh" class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">⟳ Atualizar preview</button>
                    </div>
                    <div class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
                        Revise todas as informações antes de salvar. O plano será registrado permanentemente e enviado para análise.
                    </div>

                    <div class="mt-4 rounded-2xl border border-gray-200 p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resumo do plano</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><span class="text-[11px] text-gray-500">Safra:</span> <strong id="preview-safra" class="text-sm">—</strong></div>
                            <div><span class="text-[11px] text-gray-500">Título:</span> <strong id="preview-titulo" class="text-sm">—</strong></div>
                            <div><span class="text-[11px] text-gray-500">Propriedade:</span> <strong id="preview-propriedade" class="text-sm">—</strong></div>
                            <div><span class="text-[11px] text-gray-500">Agricultor:</span> <strong id="preview-agricultor" class="text-sm">—</strong></div>
                            <div><span class="text-[11px] text-gray-500">Área orgânica:</span> <strong id="preview-area-org" class="text-sm">—</strong> ha</div>
                            <div><span class="text-[11px] text-gray-500">Área em conversão:</span> <strong id="preview-area-conv" class="text-sm">—</strong> ha</div>
                            <div><span class="text-[11px] text-gray-500">Solo:</span> <strong id="preview-solo" class="text-sm">—</strong></div>
                            <div><span class="text-[11px] text-gray-500">Topografia:</span> <strong id="preview-topo" class="text-sm">—</strong></div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cultivos planejados</h3>
                            <span id="preview-total-cultivos" class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800">0 cultivos</span>
                        </div>
                        <div class="mt-2 overflow-x-auto">
                            <table id="preview-cultivos" class="w-full text-sm">
                                <thead><tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wide text-gray-500"><th class="px-2 py-2">Cultura</th><th class="px-2 py-2">Área (ha)</th><th class="px-2 py-2">Semente</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-5">
                    <label class="block text-xs font-medium text-gray-600">Observações finais</label>
                    <textarea name="observacoes" rows="2" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="Comentários adicionais..."><?= self::e((string) ($plano['observacoes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-4">
                    <button type="button" id="wizard-prev" disabled
                            class="rounded-full border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40">← Anterior</button>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="modo" value="rascunho" formnovalidate
                                class="rounded-full border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Salvar rascunho</button>
                        <button type="button" id="wizard-next"
                                class="rounded-full bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Próximo →</button>
                        <button type="submit" name="modo" value="submeter" id="wizard-submit"
                                class="hidden rounded-full bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Enviar plano para análise</button>
                    </div>
                </div>
            </form>

            <script src="<?= self::e(Urls::asset('vendor/datatables.min.js')) ?>"></script>
            <script>
            (function () {
                var totalEtapas = 5;
                var etapaAtual = 1;
                var form = document.querySelector('form[action="<?= self::e($url) ?>"]');
                var prev = document.getElementById('wizard-prev');
                var next = document.getElementById('wizard-next');
                var submit = document.getElementById('wizard-submit');
                var previewTabela = null;

                function somenteNumeros(valor) {
                    return String(valor || '').replace(',', '.');
                }

                function atualizarPreview() {
                    if (!form) { return; }
                    var propriedade = form.querySelector('[name="propriedade_id"]');
                    var safra = form.querySelector('[name="ano_safra"]');
                    var titulo = form.querySelector('[name="titulo"]');
                    var agricultor = form.querySelector('input[readonly]');
                    document.getElementById('preview-safra').textContent = safra ? (safra.value || '—') : '—';
                    document.getElementById('preview-titulo').textContent = titulo ? (titulo.value || '—') : '—';
                    document.getElementById('preview-propriedade').textContent = propriedade && propriedade.selectedOptions[0] ? propriedade.selectedOptions[0].textContent : '—';
                    document.getElementById('preview-agricultor').textContent = agricultor ? (agricultor.value || '—') : '—';
                    var areaOrg = form.querySelector('[name="area_organica_ha"]');
                    var areaConv = form.querySelector('[name="area_conversao_ha"]');
                    var solo = form.querySelector('[name="descricao_solo"]');
                    var topo = form.querySelector('[name="topografia"]');
                    document.getElementById('preview-area-org').textContent = areaOrg ? (areaOrg.value || '—') : '—';
                    document.getElementById('preview-area-conv').textContent = areaConv ? (areaConv.value || '—') : '—';
                    document.getElementById('preview-solo').textContent = solo ? (solo.value || '—') : '—';
                    document.getElementById('preview-topo').textContent = topo ? (topo.value || '—') : '—';

                    var linhas = [];
                    form.querySelectorAll('#cultivos .cultivo-row').forEach(function (row) {
                        var cultura = row.querySelector('[name$="[cultura]"]');
                        if (!cultura || !cultura.value.trim()) { return; }
                        var area = row.querySelector('[name$="[area_cultivada_ha]"]');
                        var semente = row.querySelector('[name$="[tipo_semente]"]');
                        linhas.push({
                            cultura: cultura.value.trim(),
                            area: area ? area.value : '—',
                            semente: semente ? semente.value : '—'
                        });
                    });
                    document.getElementById('preview-total-cultivos').textContent = linhas.length + (linhas.length === 1 ? ' cultivo' : ' cultivos');

                    if (typeof DataTable !== 'undefined') {
                        if (previewTabela) {
                            previewTabela.destroy();
                        }
                        previewTabela = new DataTable('#preview-cultivos', {
                            data: linhas,
                            columns: [
                                { data: 'cultura', title: 'Cultura' },
                                { data: 'area', title: 'Área (ha)' },
                                { data: 'semente', title: 'Semente' }
                            ],
                            pageLength: 5,
                            lengthMenu: [5, 10, -1],
                            order: [],
                            autoWidth: false,
                            searching: false,
                            language: {
                                emptyTable: 'Nenhum cultivo adicionado.',
                                info: 'Mostrando _START_ a _END_ de _TOTAL_ cultivos',
                                infoEmpty: 'Nenhum cultivo',
                                lengthMenu: 'Mostrar _MENU_ cultivos',
                                paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' }
                            }
                        });
                    } else {
                        var tbody = document.querySelector('#preview-cultivos tbody');
                        if (tbody) {
                            tbody.innerHTML = linhas.length === 0
                                ? '<tr><td colspan="3" class="px-2 py-3 text-center text-xs text-gray-500">Nenhum cultivo adicionado.</td></tr>'
                                : linhas.map(function (l) { return '<tr class="border-b border-gray-100"><td class="px-2 py-2">' + l.cultura + '</td><td class="px-2 py-2">' + l.area + '</td><td class="px-2 py-2">' + l.semente + '</td></tr>'; }).join('');
                        }
                    }
                }

                function atualizarWizard() {
                    document.querySelectorAll('.wizard-step').forEach(function (step) {
                        step.classList.toggle('active', Number(step.getAttribute('data-step')) === etapaAtual);
                    });
                    document.querySelectorAll('#wizard-steps .wizard-item').forEach(function (item) {
                        var step = Number(item.getAttribute('data-step'));
                        item.classList.toggle('active', step === etapaAtual);
                        item.classList.toggle('completed', step < etapaAtual);
                    });
                    prev.disabled = etapaAtual === 1;
                    next.classList.toggle('hidden', etapaAtual === totalEtapas);
                    submit.classList.toggle('hidden', etapaAtual !== totalEtapas);
                    if (etapaAtual === totalEtapas) {
                        atualizarPreview();
                    }
                }

                if (prev) {
                    prev.addEventListener('click', function () {
                        if (etapaAtual > 1) { etapaAtual--; atualizarWizard(); }
                    });
                }
                if (next) {
                    next.addEventListener('click', function () {
                        if (etapaAtual === 1 && form && !form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        if (etapaAtual < totalEtapas) { etapaAtual++; atualizarWizard(); }
                    });
                }
                var refresh = document.getElementById('wizard-refresh');
                if (refresh) { refresh.addEventListener('click', atualizarPreview); }
                atualizarWizard();
            })();
            </script>
        <?php else: ?>
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Dados do plano</h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php foreach (PlanoManejo::resumo($plano) as [$rotulo, $valor]): ?>
                        <div><dt class="text-[11px] uppercase tracking-wide text-gray-500"><?= self::e($rotulo) ?></dt><dd class="mt-0.5 text-sm text-gray-800"><?= self::e($valor !== '' ? $valor : '—') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <p class="mt-3 text-xs text-gray-500">O plano está em análise/aprovado e não pode ser editado. Consulte o técnico para ajustes.</p>
            </section>

            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Caracterização e práticas</h2>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    <?php foreach ([
                        'area_total_ha' => 'Área total', 'area_organica_ha' => 'Área orgânica',
                        'area_conversao_ha' => 'Área em conversão', 'area_convencional_ha' => 'Área convencional',
                        'topografia' => 'Topografia', 'descricao_solo' => 'Solo', 'fonte_agua' => 'Água',
                        'clima' => 'Clima', 'manejo_pragas' => 'Manejo de pragas', 'manejo_doencas' => 'Manejo de doenças',
                        'praticas_conservacao' => 'Conservação', 'rotacao_culturas' => 'Rotação de culturas',
                        'compostagem' => 'Compostagem', 'biofertilizantes' => 'Biofertilizantes', 'adubacao_verde' => 'Adubação verde',
                    ] as $campo => $rotulo): ?>
                        <div><dt class="text-[11px] uppercase tracking-wide text-gray-500"><?= self::e($rotulo) ?></dt><dd class="mt-0.5 whitespace-pre-line text-sm text-gray-800"><?= self::e(trim((string) ($plano[$campo] ?? '')) !== '' ? (string) $plano[$campo] : '—') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Cultivos planejados</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-gray-200 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-2 py-2">Cultura</th><th class="px-2 py-2">Área (ha)</th><th class="px-2 py-2">Semente</th><th class="px-2 py-2">Estimativa (kg)</th>
                        </tr></thead>
                        <tbody>
                            <?php if (empty($plano['cultivos'])): ?><tr><td colspan="4" class="px-2 py-4 text-center text-xs text-gray-500">Nenhum cultivo registrado.</td></tr><?php endif; ?>
                            <?php foreach ((array) ($plano['cultivos'] ?? []) as $cultivo): ?>
                                <tr class="border-b border-gray-100">
                                    <td class="px-2 py-2"><?= self::e((string) ($cultivo['cultura'] ?? '—')) ?></td>
                                    <td class="px-2 py-2"><?= self::e((string) ($cultivo['area_cultivada_ha'] ?? '—')) ?></td>
                                    <td class="px-2 py-2"><?= self::e((string) ($cultivo['tipo_semente'] ?? '—')) ?></td>
                                    <td class="px-2 py-2"><?= self::e((string) ($cultivo['producao_estimada_kg'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($plano !== null && $propriedades !== []): ?>
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Croqui da propriedade</h2>
                <?php if (!empty($plano['croqui_propriedade_url'])): ?>
                    <p class="mt-2 text-sm"><a class="font-semibold text-teal-700 underline" href="<?= self::e((string) $plano['croqui_propriedade_url']) ?>" target="_blank" rel="noopener">Abrir croqui enviado</a></p>
                <?php endif; ?>
                <form method="post" action="<?= self::e($url) ?>" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                    <input type="hidden" name="acao" value="croqui">
                    <input type="hidden" name="id" value="<?= (int) ($plano['id'] ?? 0) ?>">
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Enviar imagem/PDF (máx. 5 MB)</label>
                        <input type="file" name="croqui" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" required class="mt-1 text-xs">
                    </div>
                    <button type="submit" class="rounded-full bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">Enviar croqui</button>
                </form>
            </section>
        <?php endif; ?>

        <script>
        function sinporgAddCultivo() {
            var container = document.getElementById('cultivos');
            var index = container.querySelectorAll('.cultivo-row').length;
            var html = '<div class="cultivo-row grid gap-2 rounded-xl border border-gray-200 p-3 sm:grid-cols-6">'
                + '<input name="cultivos[' + index + '][cultura]" placeholder="Cultura" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs sm:col-span-2">'
                + '<input name="cultivos[' + index + '][variedade]" placeholder="Variedade" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">'
                + '<input name="cultivos[' + index + '][area_cultivada_ha]" type="number" step="0.01" placeholder="Área (ha)" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">'
                + '<input name="cultivos[' + index + '][producao_estimada_kg]" type="number" step="0.01" placeholder="Estimativa (kg)" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs">'
                + '<select name="cultivos[' + index + '][tipo_semente]" class="rounded-lg border border-gray-300 px-2 py-1.5 text-xs"><option>Orgânica Certificada</option><option>Convencional Não Tratada</option><option>Crioula</option><option>Própria</option></select>'
                + '<button type="button" onclick="this.closest(\'.cultivo-row\').remove()" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1.5 text-xs font-semibold text-red-700">Remover</button></div>';
            container.insertAdjacentHTML('beforeend', html);
        }
        </script>
        <?php Ui::fecharLayout();
    }

    private static function classeStatus(string $status): string
    {
        return match ($status) {
            'Aprovado' => 'bg-green-50 text-green-800 ring-green-200',
            'Em Análise' => 'bg-sky-50 text-sky-800 ring-sky-200',
            'Reprovado' => 'bg-red-50 text-red-700 ring-red-200',
            'Revisão Necessária' => 'bg-amber-50 text-amber-800 ring-amber-200',
            default => 'bg-gray-50 text-gray-700 ring-gray-200',
        };
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
