<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Telas de Certificações (SPG/OCS) no novo módulo social.
 *
 * Espelha `modulos/certificacao/listar.php`, `cadastrar.php`, `editar.php`,
 * `renovar.php`, `historico.php` e `certificado.php?format=html`, incluindo
 * filtros da listagem e todos os links da coluna Ações.
 */
final class UiCertificacoes
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $filtros
     * @param list<array<string, mixed>> $linhas
     * @param array<string, int> $resumo
     */
    public static function listar(
        string $slug,
        array $app,
        array $identidade,
        array $filtros,
        array $linhas,
        array $resumo,
        ?string $flash,
        ?string $erro,
        bool $podeGerenciar
    ): void {
        self::topo($slug, $app, $identidade, 'Certificações (SPG/OCS)', 'Certificação participativa: emita, renove, consulte o histórico e gere o certificado.');
        ?>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificações</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">Certificações (SPG/OCS)</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-600">
                    Certificação participativa com emissão, validade, organismo e histórico por associado.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($podeGerenciar): ?>
                    <a href="<?= self::e(self::url($slug, ['acao' => 'nova'])) ?>"
                       class="rounded-full bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-teal-800">
                        + Nova certificação
                    </a>
                <?php endif; ?>
                <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <?php foreach ([
                ['Total', $resumo['total'], 'text-gray-900'],
                ['Ativas', $resumo['ativas'], 'text-green-700'],
                ['Vencem em 30 dias', $resumo['proximas'], 'text-amber-600'],
                ['Vencidas', $resumo['vencidas'], 'text-red-600'],
            ] as [$rotulo, $valor, $cor]): ?>
                <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500"><?= self::e($rotulo) ?></p>
                    <p class="mt-1 text-2xl font-bold <?= $cor ?>"><?= (int) $valor ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
              class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label for="q" class="block text-xs font-medium text-gray-600">Buscar</label>
                    <input id="q" name="q" value="<?= self::e($filtros['q']) ?>" placeholder="Nome do associado ou nº do certificado"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="tipo" class="block text-xs font-medium text-gray-600">Tipo</label>
                    <select id="tipo" name="tipo" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <?php foreach (Certificacoes::TIPOS as $tipo): ?>
                            <option value="<?= self::e($tipo) ?>" <?= $filtros['tipo'] === $tipo ? 'selected' : '' ?>><?= self::e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-600">Status</label>
                    <select id="status" name="status" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        <?php foreach (Certificacoes::STATUS as $status): ?>
                            <option value="<?= self::e($status) ?>" <?= $filtros['status'] === $status ? 'selected' : '' ?>><?= self::e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="validade" class="block text-xs font-medium text-gray-600">Validade</label>
                    <select id="validade" name="validade" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option value="proximas" <?= $filtros['validade'] === 'proximas' ? 'selected' : '' ?>>Vence em 30 dias</option>
                        <option value="vencidas" <?= $filtros['validade'] === 'vencidas' ? 'selected' : '' ?>>Vencidas</option>
                    </select>
                </div>
            </div>
            <?php if ($filtros['associado_id'] > 0): ?>
                <input type="hidden" name="associado_id" value="<?= (int) $filtros['associado_id'] ?>">
            <?php endif; ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Aplicar filtros</button>
                <a href="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
                   class="rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpar</a>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Nº</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Associado</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Emissão</th>
                            <th class="px-3 py-3">Validade</th>
                            <th class="px-3 py-3">Organismo</th>
                            <th class="px-3 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">Nenhuma certificação encontrada com estes filtros.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <tr class="border-b border-gray-100 align-top hover:bg-gray-50">
                                <td class="px-3 py-3 font-medium text-gray-800"><?= self::e((string) ($linha['numero_certificado'] ?: '—')) ?></td>
                                <td class="px-3 py-3"><?= self::badge((string) $linha['tipo_certificacao']) ?></td>
                                <td class="px-3 py-3">
                                    <p class="font-medium text-gray-800"><?= self::e(trim((string) $linha['associado_nome'] . ' ' . (string) $linha['associado_sobrenome'])) ?></p>
                                </td>
                                <td class="px-3 py-3"><?= self::badge((string) $linha['status']) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::data((string) ($linha['data_emissao'] ?? '')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::data((string) ($linha['data_validade'] ?? '')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) (($linha['organismo_certificador'] ?: $linha['certificadora_nome']) ?: '—')) ?></td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <a href="<?= self::e(self::url($slug, ['acao' => 'detalhes', 'id' => (int) $linha['id']])) ?>"
                                           class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100">Detalhes</a>
                                        <?php if ($podeGerenciar): ?>
                                            <a href="<?= self::e(self::url($slug, ['acao' => 'editar', 'id' => (int) $linha['id']])) ?>"
                                               class="rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[11px] font-medium text-teal-800 hover:bg-teal-100">Editar</a>
                                            <a href="<?= self::e(self::url($slug, ['acao' => 'renovar', 'id' => (int) $linha['id']])) ?>"
                                               class="rounded-lg border border-green-200 bg-green-50 px-2 py-1 text-[11px] font-medium text-green-800 hover:bg-green-100">Renovar</a>
                                        <?php endif; ?>
                                        <a href="<?= self::e(self::url($slug, ['acao' => 'historico', 'associado_id' => (int) $linha['associado_id']])) ?>"
                                           class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100">Histórico</a>
                                        <a href="<?= self::e(self::url($slug, ['acao' => 'certificado', 'id' => (int) $linha['id']])) ?>" target="_blank" rel="noopener"
                                           class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-800 hover:bg-emerald-100">Certificado</a>
                                        <?php if (!empty($linha['documento_url'])): ?>
                                            <a href="<?= self::e((string) $linha['documento_url']) ?>" target="_blank" rel="noopener"
                                               class="rounded-lg border border-gray-300 bg-gray-50 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-100">Assinado</a>
                                        <?php endif; ?>
                                        <?php if ($podeGerenciar): ?>
                                            <form method="post" action="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
                                                  onsubmit="return confirm('Excluir esta certificação? O histórico continuará no banco como excluído.');" class="inline">
                                                <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="id" value="<?= (int) $linha['id'] ?>">
                                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-100">Excluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500">
                <?= count($linhas) ?> certificação(ões) listada(s) · filtros aplicados no servidor
            </div>
        </div>
        <?php
        self::fim();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $registro
     * @param list<array<string, mixed>> $associados
     * @param list<array<string, mixed>> $certificadoras
     */
    public static function formulario(
        string $slug,
        array $app,
        array $identidade,
        string $modo,
        array $registro,
        array $associados,
        array $certificadoras,
        ?string $erro,
        bool $podeGerenciar
    ): void {
        $renovacao = $modo === 'renovar';
        $editando = $modo === 'editar';
        $titulo = $renovacao ? 'Renovar certificação' : ($editando ? 'Editar certificação' : 'Nova certificação');
        self::topo($slug, $app, $identidade, $titulo, 'Formulário de certificação participativa.');
        ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificações</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl"><?= self::e($titulo) ?></h1>
            </div>
            <a href="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
               class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar à lista</a>
        </div>

        <?php self::alertas(null, $erro); ?>

        <?php if ($renovacao): ?>
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p><strong>Certificação atual:</strong> <?= self::e((string) ($registro['numero_certificado'] ?: 'sem número')) ?>
                    · <?= self::e((string) $registro['tipo_certificacao']) ?>
                    · validade <?= self::data((string) ($registro['data_validade'] ?? '')) ?></p>
                <p class="mt-1 text-xs">Ao renovar, o status volta para <strong>Ativa</strong>, a validade é atualizada e o histórico registra a data de renovação.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
              class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="modo" value="<?= self::e($modo) ?>">
            <?php if (!empty($registro['id'])): ?>
                <input type="hidden" name="id" value="<?= (int) $registro['id'] ?>">
            <?php endif; ?>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (!$renovacao): ?>
                    <div>
                        <label for="associado_id" class="block text-xs font-medium text-gray-600">Associado <span class="text-red-600">*</span></label>
                        <select id="associado_id" name="associado_id" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Selecione…</option>
                            <?php foreach ($associados as $associado): ?>
                                <option value="<?= (int) $associado['id'] ?>" <?= (int) ($registro['associado_id'] ?? 0) === (int) $associado['id'] ? 'selected' : '' ?>>
                                    <?= self::e(trim((string) $associado['nome'] . ' ' . (string) $associado['sobrenome'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="certificadora_id" class="block text-xs font-medium text-gray-600">Organismo certificador</label>
                        <select id="certificadora_id" name="certificadora_id" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <option value="0">—</option>
                            <?php foreach ($certificadoras as $certificadora): ?>
                                <option value="<?= (int) $certificadora['id'] ?>" <?= (int) ($registro['certificadora_id'] ?? 0) === (int) $certificadora['id'] ? 'selected' : '' ?>>
                                    <?= self::e((string) $certificadora['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="tipo_certificacao" class="block text-xs font-medium text-gray-600">Tipo <span class="text-red-600">*</span></label>
                        <select id="tipo_certificacao" name="tipo_certificacao" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <?php foreach (Certificacoes::TIPOS as $tipo): ?>
                                <option value="<?= self::e($tipo) ?>" <?= ($registro['tipo_certificacao'] ?? 'SPG') === $tipo ? 'selected' : '' ?>><?= self::e($tipo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="numero_certificado" class="block text-xs font-medium text-gray-600">Número do certificado <span class="text-red-600">*</span></label>
                    <input id="numero_certificado" name="numero_certificado" required value="<?= self::e((string) ($registro['numero_certificado'] ?? '')) ?>"
                           placeholder="Ex: SPG-2026-001"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label for="data_emissao" class="block text-xs font-medium text-gray-600">Emissão <span class="text-red-600">*</span></label>
                    <input id="data_emissao" name="data_emissao" type="date" required
                           value="<?= self::e((string) ($registro['data_emissao'] ?: date('Y-m-d'))) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="data_validade" class="block text-xs font-medium text-gray-600">Validade <span class="text-red-600">*</span></label>
                    <input id="data_validade" name="data_validade" type="date" required
                           value="<?= self::e((string) ($registro['data_validade'] ?: date('Y-m-d', strtotime('+1 year')))) ?>"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>

                <?php if (!$renovacao): ?>
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-600">Status <span class="text-red-600">*</span></label>
                        <select id="status" name="status" required class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                            <?php foreach (Certificacoes::STATUS as $status): ?>
                                <option value="<?= self::e($status) ?>" <?= ($registro['status'] ?? 'Ativa') === $status ? 'selected' : '' ?>><?= self::e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="organismo_certificador" class="block text-xs font-medium text-gray-600">Organismo (texto)</label>
                        <input id="organismo_certificador" name="organismo_certificador" value="<?= self::e((string) ($registro['organismo_certificador'] ?? '')) ?>"
                               placeholder="Ex: OPAC/OCS"
                               class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="nome_grupo" class="block text-xs font-medium text-gray-600">Grupo</label>
                        <input id="nome_grupo" name="nome_grupo" value="<?= self::e((string) ($registro['nome_grupo'] ?? '')) ?>"
                               class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="documento_url" class="block text-xs font-medium text-gray-600">URL do certificado</label>
                        <input id="documento_url" name="documento_url" type="url" value="<?= self::e((string) ($registro['documento_url'] ?? '')) ?>"
                               placeholder="https://…/certificado.pdf"
                               class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="declaracao_compromisso_url" class="block text-xs font-medium text-gray-600">Declaração de compromisso (URL)</label>
                        <input id="declaracao_compromisso_url" name="declaracao_compromisso_url" type="url"
                               value="<?= self::e((string) ($registro['declaracao_compromisso_url'] ?? '')) ?>"
                               class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <label for="observacoes" class="block text-xs font-medium text-gray-600">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="3" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e((string) ($registro['observacoes'] ?? '')) ?></textarea>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-teal-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                    <?= $renovacao ? 'Renovar certificação' : 'Salvar certificação' ?>
                </button>
                <a href="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>"
                   class="rounded-full border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>
        <?php
        self::fim();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $registro
     */
    public static function detalhes(string $slug, array $app, array $identidade, array $registro, bool $podeGerenciar): void
    {
        self::topo($slug, $app, $identidade, 'Detalhes da certificação', 'Dados completos da certificação participativa.');
        $id = (int) $registro['id'];
        ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificações</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Detalhes da certificação</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($podeGerenciar): ?>
                    <a href="<?= self::e(self::url($slug, ['acao' => 'editar', 'id' => $id])) ?>" class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Editar</a>
                    <a href="<?= self::e(self::url($slug, ['acao' => 'renovar', 'id' => $id])) ?>" class="rounded-full bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">Renovar</a>
                <?php endif; ?>
                <a href="<?= self::e(self::url($slug, ['acao' => 'certificado', 'id' => $id])) ?>" target="_blank" rel="noopener"
                   class="rounded-full border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">Certificado</a>
                <a href="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">← Lista</a>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Associado</dt><dd class="mt-1 text-sm font-medium text-gray-900"><?= self::e(trim((string) $registro['associado_nome'] . ' ' . (string) $registro['associado_sobrenome'])) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">CPF</dt><dd class="mt-1 text-sm text-gray-800"><?= self::cpf((string) ($registro['associado_cpf'] ?? '')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Tipo</dt><dd class="mt-1"><?= self::badge((string) $registro['tipo_certificacao']) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Número</dt><dd class="mt-1 text-sm font-medium"><?= self::e((string) ($registro['numero_certificado'] ?: '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Status</dt><dd class="mt-1"><?= self::badge((string) $registro['status']) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Emissão</dt><dd class="mt-1 text-sm"><?= self::data((string) ($registro['data_emissao'] ?? '')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Validade</dt><dd class="mt-1 text-sm"><?= self::data((string) ($registro['data_validade'] ?? '')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Organismo</dt><dd class="mt-1 text-sm"><?= self::e((string) (($registro['organismo_certificador'] ?: $registro['certificadora_nome']) ?: '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Grupo</dt><dd class="mt-1 text-sm"><?= self::e((string) ($registro['nome_grupo'] ?: '—')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Renovação</dt><dd class="mt-1 text-sm"><?= self::data((string) ($registro['data_renovacao'] ?? '')) ?></dd></div>
                </dl>

                <?php if (!empty($registro['assinatura_data'])): ?>
                    <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs text-green-900">
                        <strong>Assinado digitalmente</strong> por <?= self::e((string) ($registro['assinatura_por'] ?: 'Administrador')) ?>
                        em <?= self::e(date('d/m/Y H:i', strtotime((string) $registro['assinatura_data']))) ?>.
                    </div>
                <?php endif; ?>

                <?php if (!empty($registro['observacoes'])): ?>
                    <div class="mt-4">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500">Observações</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700"><?= self::e((string) $registro['observacoes']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Documentos</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php if (!empty($registro['documento_url'])): ?>
                        <li><a class="text-teal-700 underline" href="<?= self::e((string) $registro['documento_url']) ?>" target="_blank" rel="noopener">Certificado assinado (URL)</a></li>
                    <?php else: ?>
                        <li class="text-gray-500">Nenhum certificado assinado anexado.</li>
                    <?php endif; ?>
                    <?php if (!empty($registro['declaracao_compromisso_url'])): ?>
                        <li><a class="text-teal-700 underline" href="<?= self::e((string) $registro['declaracao_compromisso_url']) ?>" target="_blank" rel="noopener">Declaração de compromisso</a></li>
                    <?php endif; ?>
                    <li><a class="text-teal-700 underline" href="<?= self::e(self::url($slug, ['acao' => 'historico', 'associado_id' => (int) $registro['associado_id']])) ?>">Histórico do associado</a></li>
                </ul>
            </aside>
        </div>
        <?php
        self::fim();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $associado
     * @param list<array<string, mixed>> $linhas
     */
    public static function historico(
        string $slug,
        array $app,
        array $identidade,
        array $associado,
        array $linhas,
        bool $podeGerenciar
    ): void {
        self::topo($slug, $app, $identidade, 'Histórico de certificações', 'Histórico do associado.');
        $associadoId = (int) $associado['id'];
        ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">Fiscalização · Certificações</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Histórico de certificações</h1>
                <p class="mt-1 text-sm text-gray-600">
                    <?= self::e(trim((string) $associado['nome'] . ' ' . (string) $associado['sobrenome'])) ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($podeGerenciar): ?>
                    <a href="<?= self::e(self::url($slug, ['acao' => 'nova', 'associado_id' => $associadoId])) ?>"
                       class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">+ Nova certificação</a>
                <?php endif; ?>
                <a href="<?= self::e(Urls::app($slug, 'certificacoes.php')) ?>" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">← Lista</a>
            </div>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <th class="px-3 py-3">Nº</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Emissão</th>
                            <th class="px-3 py-3">Validade</th>
                            <th class="px-3 py-3">Organismo</th>
                            <th class="px-3 py-3">Observações</th>
                            <th class="px-3 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="8" class="px-3 py-8 text-center text-sm text-gray-500">Este associado ainda não possui certificações.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <tr class="border-b border-gray-100 align-top hover:bg-gray-50">
                                <td class="px-3 py-3 font-medium"><?= self::e((string) ($linha['numero_certificado'] ?: '—')) ?></td>
                                <td class="px-3 py-3"><?= self::badge((string) $linha['tipo_certificacao']) ?></td>
                                <td class="px-3 py-3"><?= self::badge((string) $linha['status']) ?><?= (int) ($linha['excluido'] ?? 0) === 1 ? ' <span class="text-[10px] text-red-600">(excluída)</span>' : '' ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::data((string) ($linha['data_emissao'] ?? '')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::data((string) ($linha['data_validade'] ?? '')) ?></td>
                                <td class="px-3 py-3 text-gray-600"><?= self::e((string) (($linha['organismo_certificador'] ?: $linha['certificadora_nome']) ?: '—')) ?></td>
                                <td class="max-w-xs px-3 py-3 text-gray-500"><span class="line-clamp-2"><?= self::e((string) ($linha['observacoes'] ?: '—')) ?></span></td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <a href="<?= self::e(self::url($slug, ['acao' => 'detalhes', 'id' => (int) $linha['id']])) ?>"
                                           class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] text-gray-600">Detalhes</a>
                                        <a href="<?= self::e(self::url($slug, ['acao' => 'certificado', 'id' => (int) $linha['id']])) ?>" target="_blank" rel="noopener"
                                           class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] text-emerald-800">Certificado</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        self::fim();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $registro
     * @param list<array<string, mixed>> $propriedades
     */
    public static function certificado(string $slug, array $app, array $identidade, array $registro, array $propriedades): void
    {
        $nome = trim((string) $registro['associado_nome'] . ' ' . (string) $registro['associado_sobrenome']);
        $cpf = self::cpf((string) ($registro['associado_cpf'] ?? ''));
        $municipio = trim((string) (($registro['associado_cidade'] ?? '') . '/' . ($registro['associado_estado'] ?? '')), '/');
        $organismo = (string) (($registro['organismo_certificador'] ?: $registro['certificadora_nome']) ?: '—');
        $status = (string) $registro['status'];

        Ui::abrirLayout($slug, $app, $identidade, 'Certificado', 'certificacoes.listar');
        ?>
<div class="mx-auto max-w-4xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 print:hidden">
        <a href="<?= Ui::e(self::url($slug, ['acao' => 'detalhes', 'id' => (int) $registro['id']])) ?>"
           class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700">← Voltar</a>
        <button type="button" onclick="window.print()"
                class="rounded-full bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">
            🖨 Imprimir / Salvar como PDF
        </button>
    </div>

    <article class="rounded-2xl border-4 border-teal-800 bg-white p-8 shadow-xl sm:p-12">
        <header class="border-b-2 border-teal-800 pb-5 text-center">
            <p class="text-xs uppercase tracking-[0.3em] text-gray-500">SINPORG · Agricultura Orgânica</p>
            <h1 class="mt-2 text-2xl font-bold tracking-wide text-teal-900 sm:text-3xl">CERTIFICADO DE CONFORMIDADE ORGÂNICA</h1>
            <p class="mt-1 text-sm text-gray-600">Certificação participativa — <?= Ui::e((string) $registro['tipo_certificacao']) ?></p>
        </header>

        <p class="mt-6 text-center text-sm text-gray-600">Certificamos que</p>
        <p class="mt-2 text-center text-2xl font-bold text-gray-900"><?= Ui::e($nome) ?></p>
        <?php if ($cpf !== '—'): ?>
            <p class="text-center text-sm text-gray-600">CPF <?= Ui::e($cpf) ?><?= $municipio !== '' ? ' · ' . Ui::e($municipio) : '' ?></p>
        <?php endif; ?>

        <p class="mt-6 text-center text-sm leading-relaxed text-gray-700">
            tem sua produção reconhecida como <strong>orgânica</strong>, em conformidade com a Lei 10.831/2003
            e a Portaria MAPA 52/2021, no âmbito do Sistema Participativo de Garantia.
        </p>

        <?php if ($propriedades !== []): ?>
            <div class="mt-5 rounded-xl bg-gray-50 p-4 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Unidades de produção</p>
                <ul class="mt-1 list-disc pl-5 text-gray-700">
                    <?php foreach ($propriedades as $propriedade): ?>
                        <li><?= Ui::e((string) $propriedade['propriedade']) ?><?= !empty($propriedade['area_total']) ? ' — ' . Ui::e((string) $propriedade['area_total']) . ' ha' : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Número do certificado</dt><dd class="mt-0.5 font-semibold"><?= Ui::e((string) ($registro['numero_certificado'] ?: '—')) ?></dd></div>
            <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Organismo certificador</dt><dd class="mt-0.5 font-semibold"><?= Ui::e($organismo) ?></dd></div>
            <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Data de emissão</dt><dd class="mt-0.5 font-semibold"><?= self::data((string) ($registro['data_emissao'] ?? '')) ?></dd></div>
            <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Validade</dt><dd class="mt-0.5 font-semibold"><?= self::data((string) ($registro['data_validade'] ?? '')) ?></dd></div>
            <?php if (!empty($registro['nome_grupo'])): ?>
                <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Grupo</dt><dd class="mt-0.5 font-semibold"><?= Ui::e((string) $registro['nome_grupo']) ?></dd></div>
            <?php endif; ?>
            <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Situação</dt><dd class="mt-0.5 font-semibold"><?= Ui::e($status) ?></dd></div>
        </dl>

        <div class="mt-10 grid gap-8 text-center text-xs text-gray-600 sm:grid-cols-2">
            <div class="border-t border-gray-400 pt-2">Organismo Certificador<br><strong><?= Ui::e($organismo) ?></strong></div>
            <div class="border-t border-gray-400 pt-2">SINPORG<br><strong>Associação de Agricultura Orgânica</strong></div>
        </div>
        <p class="mt-6 text-center text-[11px] text-gray-400">
            Documento gerado em <?= date('d/m/Y H:i') ?> · validade sujeita à situação cadastral.
        </p>
    </article>
</div>
<script>if (window.location.hash === '#print') { window.print(); }</script>
        <?php self::fim(); ?>
        <?php
    }

    private static function fim(): void
    {
        Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    private static function topo(string $slug, array $app, array $identidade, string $titulo, string $descricao): void
    {
        Ui::abrirLayout($slug, $app, $identidade, $titulo, 'certificacoes.listar');
    }

    private static function alertas(?string $flash, ?string $erro): void
    {
        if ($flash !== null && $flash !== '') {
            ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= Ui::e($flash) ?></div>
            <?php
        }
        if ($erro !== null && $erro !== '') {
            ?>
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= Ui::e($erro) ?></div>
            <?php
        }
    }

    /** @param array<string, mixed> $params */
    public static function url(string $slug, array $params = []): string
    {
        $url = Urls::app($slug, 'certificacoes.php');

        return $params === [] ? $url : $url . '?' . http_build_query($params);
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private static function data(string $valor): string
    {
        if ($valor === '' || $valor === '0000-00-00') {
            return '—';
        }
        $ts = strtotime($valor);

        return $ts === false ? '—' : date('d/m/Y', $ts);
    }

    private static function cpf(string $cpf): string
    {
        $digitos = preg_replace('/\D+/', '', $cpf) ?? '';

        return strlen($digitos) === 11
            ? substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2)
            : '—';
    }

    private static function badge(string $valor): string
    {
        $normalizado = mb_strtolower($valor);
        $classe = 'bg-gray-100 text-gray-700 ring-gray-200';
        foreach (['ativa', 'aprovad', 'conclu', 'emitid'] as $positivo) {
            if (str_contains($normalizado, $positivo)) {
                $classe = 'bg-green-50 text-green-800 ring-green-200';
            }
        }
        foreach (['conversão', 'conversao', 'suspensa'] as $atencao) {
            if (str_contains($normalizado, $atencao)) {
                $classe = 'bg-amber-50 text-amber-800 ring-amber-200';
            }
        }
        foreach (['vencida', 'cancelada', 'excluída', 'excluida'] as $negativo) {
            if (str_contains($normalizado, $negativo)) {
                $classe = 'bg-red-50 text-red-800 ring-red-200';
            }
        }

        return '<span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ' . $classe . '">' . self::e($valor) . '</span>';
    }
}
