<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas internas do módulo de fiscalização que exigem formulário próprio
 * (por enquanto "Agendar Visita"), mantendo o mesmo layout e os mesmos
 * campos da tela do sistema atual (`modulos/roteiros_inspecao/visitas/agendar.php`).
 */
final class UiModulo
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $associados
     * @param list<array<string, mixed>> $propriedades
     * @param list<array<string, mixed>> $roteiros
     * @param list<array<string, mixed>> $tecnicos
     * @param list<array<string, mixed>> $recentes
     */
    public static function agendarVisita(
        string $slug,
        array $app,
        array $identidade,
        array $associados,
        array $propriedades,
        array $roteiros,
        array $tecnicos,
        array $recentes,
        ?string $erro,
        bool $sucesso
    ): void {
        $nome = (string) ($identidade['nome'] ?? '');
        $perfil = (string) ($identidade['perfil'] ?? '');
        $urlPainel = Urls::app($slug, 'index.php');

        Ui::abrirLayout($slug, $app, $identidade, 'Agendar Visita', 'agendar.visita');
        ?>
    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização</p>
    <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Agendar visita técnica</h1>
    <p class="mt-2 max-w-2xl text-sm text-gray-600">
        Selecione o agricultor, a propriedade e o roteiro de inspeção. O agendamento alimenta a
        listagem <strong>Visitas e Inspeções</strong> e o fluxo de execução da visita.
    </p>

    <?php if ($sucesso): ?>
        <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
            Visita agendada com sucesso.
        </div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <?= Ui::e($erro) ?>
        </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="post" action="<?= Ui::e(Urls::app($slug, 'agendar-visita.php')) ?>" class="lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="csrf" value="<?= Ui::e(Csrf::token()) ?>">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="associado_id" class="block text-sm font-medium text-gray-700">Associado <span class="text-red-600">*</span></label>
                        <select id="associado_id" name="associado_id" required
                                class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                            <option value="">Selecione o associado…</option>
                            <?php foreach ($associados as $associado): ?>
                                <option value="<?= (int) $associado['id'] ?>"><?= Ui::e((string) $associado['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="propriedade_id" class="block text-sm font-medium text-gray-700">Propriedade <span class="text-red-600">*</span></label>
                        <select id="propriedade_id" name="propriedade_id" required
                                class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                            <option value="">Selecione o associado primeiro</option>
                            <?php foreach ($propriedades as $propriedade): ?>
                                <option value="<?= (int) $propriedade['id'] ?>" data-associado="<?= (int) $propriedade['associado_id'] ?>">
                                    <?= Ui::e((string) ($propriedade['propriedade'] ?? '—')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="roteiro_id" class="block text-sm font-medium text-gray-700">Roteiro de inspeção <span class="text-red-600">*</span></label>
                        <select id="roteiro_id" name="roteiro_id" required
                                class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                            <option value="">Selecione o roteiro…</option>
                            <?php foreach ($roteiros as $roteiro): ?>
                                <option value="<?= (int) $roteiro['id'] ?>"><?= Ui::e((string) $roteiro['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="data_visita" class="block text-sm font-medium text-gray-700">Data da visita <span class="text-red-600">*</span></label>
                        <input id="data_visita" name="data_visita" type="date" required min="<?= date('Y-m-d') ?>"
                               class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                    </div>

                    <div>
                        <label for="hora_inicio" class="block text-sm font-medium text-gray-700">Horário</label>
                        <input id="hora_inicio" name="hora_inicio" type="time"
                               class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="tecnico_responsavel_id" class="block text-sm font-medium text-gray-700">Técnico responsável</label>
                        <select id="tecnico_responsavel_id" name="tecnico_responsavel_id"
                                class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                            <option value="">Usar meu usuário</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?= (int) $tecnico['id'] ?>"><?= Ui::e((string) $tecnico['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="acompanhantes" class="block text-sm font-medium text-gray-700">Acompanhantes</label>
                        <input id="acompanhantes" name="acompanhantes" type="text"
                               placeholder="Ex: João da Silva (Comissão de Ética), Maria Oliveira"
                               class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="observacoes" class="block text-sm font-medium text-gray-700">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"
                                  class="mt-1.5 w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button type="submit" data-carregando="Agendando…"
                            class="rounded-full bg-teal-700 px-6 py-3 text-sm font-semibold text-white shadow hover:bg-teal-800">
                        Agendar visita
                    </button>
                    <a href="<?= Ui::e($urlPainel . '?funcao=visitas.inspecoes') ?>"
                       class="rounded-full border border-gray-300 px-6 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100">
                        Ver agenda
                    </a>
                </div>
            </div>
        </form>

        <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Últimos agendamentos</h2>
            <?php if ($recentes === []): ?>
                <p class="mt-2 text-xs text-gray-500">Nenhuma visita agendada ainda.</p>
            <?php else: ?>
                <ul class="mt-3 divide-y divide-gray-100 text-xs">
                    <?php foreach ($recentes as $visita): ?>
                        <li class="py-2.5">
                            <p class="font-medium text-gray-800"><?= Ui::e((string) ($visita['agricultor'] ?: '—')) ?></p>
                            <p class="text-gray-500"><?= Ui::e((string) ($visita['propriedade'] ?? '—')) ?></p>
                            <p class="mt-0.5 text-gray-400">
                                <?= Ui::e(date('d/m/Y', strtotime((string) $visita['data_visita']))) ?>
                                · <?= Ui::e((string) ($visita['status'] ?? '')) ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var associado = document.getElementById('associado_id');
    var propriedade = document.getElementById('propriedade_id');
    if (associado && propriedade) {
        var opcoes = Array.prototype.slice.call(propriedade.querySelectorAll('option[data-associado]'));
        associado.addEventListener('change', function () {
            var id = associado.value;
            propriedade.innerHTML = '';
            var vazio = document.createElement('option');
            vazio.value = '';
            vazio.textContent = id ? 'Selecione a propriedade…' : 'Selecione o associado primeiro';
            propriedade.appendChild(vazio);
            opcoes.forEach(function (opcao) {
                if (id && opcao.getAttribute('data-associado') === id) {
                    propriedade.appendChild(opcao);
                }
            });
            propriedade.disabled = !id;
        });
    }

    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () {
            var botao = form.querySelector('button[type="submit"]');
            if (!botao || botao.disabled) { return; }
            botao.disabled = true;
            botao.classList.add('opacity-70');
            botao.textContent = 'Agendando…';
        });
    }
});
</script>
        <?php Ui::fecharLayout(); ?>
        <?php
    }
}
