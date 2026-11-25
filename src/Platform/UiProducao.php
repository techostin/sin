<?php

declare(strict_types=1);

namespace App\Platform;

/** Telas de lista, formulário e exportação dos recursos de produção. */
final class UiProducao
{
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
        string $recurso,
        array $def,
        array $linhas,
        string $busca,
        ?string $flash,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, (string) $def['titulo'], (string) $def['chave']);
        $url = Urls::app($slug, 'registro.php?recurso=' . rawurlencode($recurso));
        $podeEscrever = ProducaoRegistros::podeEscreverRecurso($def, $identidade)
            && (empty($def['somente_gestor']) || ProducaoRegistros::gerenciaTudo($identidade));
        $relacoes = (array) ($def['relacoes'] ?? []);
        $rotulosRelacoes = [];
        foreach ($relacoes as $campo => $rel) {
            $rotulosRelacoes[$campo] = ProducaoRegistros::opcoesRelacao((string) $rel['recurso'], $identidade);
        }
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700"><?= self::e((string) ($def['grupo'] ?? 'Produção')) ?></p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl"><?= self::e((string) $def['titulo']) ?></h1>
                <p class="mt-1 text-sm text-gray-600">Registros da sua produção. Os dados são filtrados pelo seu vínculo de associado.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= self::e(Urls::app($slug, 'index.php')) ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
                <a href="<?= self::e($url . '&exportar=csv') ?>"
                   class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Exportar CSV</a>
                <?php if ($podeEscrever): ?>
                    <a href="<?= self::e($url . '&acao=novo') ?>"
                       class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">+ Novo registro</a>
                <?php endif; ?>
            </div>
        </div>

        <?php self::alertas($flash, $erro); ?>

        <form method="get" action="<?= self::e(Urls::app($slug, 'registro.php')) ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="recurso" value="<?= self::e($recurso) ?>">
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[240px] flex-1">
                    <label for="q" class="block text-xs font-medium text-gray-600">Buscar</label>
                    <input id="q" name="q" value="<?= self::e($busca) ?>" placeholder="Nome, descrição ou identificação"
                           class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-full bg-amber-600 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-700">Buscar</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpar</a>
            </div>
        </form>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                            <?php foreach ((array) $def['colunas'] as $coluna): ?>
                                <th class="px-3 py-3"><?= self::e(self::rotuloColuna((string) $coluna, $relacoes)) ?></th>
                            <?php endforeach; ?>
                            <?php if ($podeEscrever): ?><th class="px-3 py-3">Ações</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($linhas === []): ?>
                            <tr><td colspan="<?= count((array) $def['colunas']) + ($podeEscrever ? 1 : 0) ?>" class="px-3 py-8 text-center text-sm text-gray-500">Nenhum registro encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($linhas as $linha): ?>
                            <tr class="border-b border-gray-100 align-middle hover:bg-gray-50">
                                <?php foreach ((array) $def['colunas'] as $coluna): ?>
                                    <td class="px-3 py-3 text-gray-700">
                                        <?= self::e(self::valor($linha, (string) $coluna, $rotulosRelacoes)) ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if ($podeEscrever): ?>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            <a href="<?= self::e($url . '&acao=editar&id=' . (int) $linha['id']) ?>"
                                               class="rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-800 hover:bg-teal-100">Editar</a>
                                            <?php if (empty($def['sem_exclusao'])): ?>
                                                <form method="post" action="<?= self::e($url) ?>" onsubmit="return confirm('Excluir este registro?');">
                                                    <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
                                                    <input type="hidden" name="acao" value="excluir">
                                                    <input type="hidden" name="id" value="<?= (int) $linha['id'] ?>">
                                                    <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
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
     * @param array<string, mixed> $def
     * @param array<string, mixed> $registro
     */
    public static function formulario(
        string $slug,
        array $app,
        array $identidade,
        string $recurso,
        array $def,
        array $registro,
        ?string $erro
    ): void {
        Ui::abrirLayout($slug, $app, $identidade, (string) $def['titulo'], (string) $def['chave']);
        $url = Urls::app($slug, 'registro.php?recurso=' . rawurlencode($recurso));
        $campos = ProducaoRegistros::campos($def, $identidade);
        $editando = (int) ($registro['id'] ?? 0) > 0;
        $temArquivo = in_array('file', array_column($campos, 'tipo'), true);
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700"><?= self::e((string) ($def['grupo'] ?? 'Produção')) ?></p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900"><?= $editando ? 'Editar registro' : 'Novo registro' ?></h1>
            </div>
            <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Voltar</a>
        </div>

        <?php self::alertas(null, $erro); ?>

        <form method="post" action="<?= self::e($url) ?>" enctype="<?= $temArquivo ? 'multipart/form-data' : 'application/x-www-form-urlencoded' ?>" class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <input type="hidden" name="csrf" value="<?= self::e(Csrf::token()) ?>">
            <input type="hidden" name="acao" value="salvar">
            <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int) $registro['id'] ?>"><?php endif; ?>

            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($campos as $campo): ?>
                    <?php
                    $nome = (string) $campo['nome'];
                    $tipo = (string) $campo['tipo'];
                    $valor = (string) ($registro[$nome] ?? '');
                    $idCampo = 'campo-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $nome);
                    $obrigatorio = (bool) $campo['obrigatorio'];
                    ?>
                    <div class="<?= in_array($tipo, ['textarea'], true) ? 'sm:col-span-2' : '' ?>">
                        <label for="<?= self::e($idCampo) ?>" class="block text-xs font-medium text-gray-600">
                            <?= self::e((string) $campo['rotulo']) ?><?= $obrigatorio ? ' *' : '' ?>
                        </label>
                        <?php if (!empty($campo['ajuda'])): ?>
                            <p class="mt-0.5 text-[11px] leading-snug text-gray-400"><?= self::e((string) $campo['ajuda']) ?></p>
                        <?php endif; ?>
                        <?php if ($tipo === 'file'): ?>
                            <?php $pastaImagem = self::pastaImagem($recurso, $nome); ?>
                            <?php $imagemAtual = ProducaoImagens::url($valor, $pastaImagem); ?>
                            <?php if ($imagemAtual !== ''): ?>
                                <div class="mt-2">
                                    <img src="<?= self::e($imagemAtual) ?>" alt="<?= self::e((string) $campo['rotulo']) ?>" class="max-h-40 rounded-xl border border-gray-200 object-cover">
                                    <p class="mt-1 text-[10px] text-gray-400">Imagem atual. Envie um novo arquivo para substituir.</p>
                                </div>
                            <?php endif; ?>
                            <input id="<?= self::e($idCampo) ?>" name="<?= self::e($nome) ?>" type="file"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-xs">
                        <?php elseif ($tipo === 'textarea'): ?>
                            <textarea id="<?= self::e($idCampo) ?>" name="<?= self::e($nome) ?>" rows="3" <?= $obrigatorio ? 'required' : '' ?>
                                      class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"><?= self::e($valor) ?></textarea>
                        <?php elseif ($tipo === 'select'): ?>
                            <select id="<?= self::e($idCampo) ?>" name="<?= self::e($nome) ?>" <?= $obrigatorio ? 'required' : '' ?>
                                    class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— Selecione —</option>
                                <?php if ($nome === 'cidade' && $valor !== ''): ?>
                                    <option value="<?= self::e($valor) ?>" selected><?= self::e($valor) ?></option>
                                <?php endif; ?>
                                <?php foreach ((array) $campo['opcoes'] as $chave => $rotulo): ?>
                                    <option value="<?= self::e((string) $chave) ?>" <?= (string) $chave === $valor ? 'selected' : '' ?>><?= self::e((string) $rotulo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($tipo === 'checkbox'): ?>
                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" id="<?= self::e($idCampo) ?>" name="<?= self::e($nome) ?>" value="1"
                                       class="h-4 w-4 rounded border-gray-300 accent-amber-600" <?= (int) $valor === 1 ? 'checked' : '' ?>>
                                Sim
                            </label>
                        <?php else: ?>
                            <?php
                            $tipoInput = match ($tipo) {
                                'date' => 'date',
                                'datetime' => 'datetime-local',
                                'time' => 'time',
                                'number' => 'number',
                                default => 'text',
                            };
                            $valorInput = $tipo === 'datetime' ? str_replace(' ', 'T', substr($valor, 0, 16)) : $valor;
                            ?>
                            <input id="<?= self::e($idCampo) ?>" name="<?= self::e($nome) ?>" type="<?= $tipoInput ?>"
                                   value="<?= self::e($valorInput) ?>" <?= $obrigatorio ? 'required' : '' ?>
                                   <?= $tipo === 'number' ? 'step="any"' : '' ?>
                                   class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 flex flex-wrap gap-2">
                <button type="submit" class="rounded-full bg-amber-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Salvar</button>
                <a href="<?= self::e($url) ?>" class="rounded-full border border-gray-300 px-6 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</a>
            </div>
        </form>
        <?php if ($recurso === 'propriedades'): ?>
            <script>
            (function () {
                var estado = document.querySelector('[name="estado"]');
                var cidade = document.querySelector('[name="cidade"]');
                if (!estado || !cidade) { return; }
                var cidadeAtual = cidade.value;
                var cidades = null;

                function preencher() {
                    var uf = estado.value;
                    cidade.innerHTML = '';
                    if (!cidadeAtual) {
                        cidade.innerHTML = '<option value="">' + (uf ? 'Selecione uma cidade' : 'Selecione um estado primeiro') + '</option>';
                    }
                    cidade.disabled = !uf;
                    if (!uf || !cidades) { return; }

                    var achou = false;
                    var vazio = document.createElement('option');
                    vazio.value = '';
                    vazio.textContent = 'Selecione uma cidade';
                    cidade.appendChild(vazio);

                    cidades.forEach(function (c) {
                        if (c.estado_sigla !== uf) { return; }
                        var option = document.createElement('option');
                        option.value = c.nome;
                        option.textContent = c.nome;
                        if (cidadeAtual && (cidadeAtual === c.nome || String(cidadeAtual) === String(c.id))) {
                            option.selected = true;
                            achou = true;
                        }
                        cidade.appendChild(option);
                    });
                    if (cidadeAtual && !achou) {
                        var atual = document.createElement('option');
                        atual.value = cidadeAtual;
                        atual.textContent = cidadeAtual + ' (valor atual)';
                        atual.selected = true;
                        cidade.appendChild(atual);
                    }
                    cidadeAtual = '';
                }

                fetch(<?= json_encode(Urls::base() . '/api/get_cidades.php?todos=1') ?>)
                    .then(function (r) { return r.json(); })
                    .then(function (data) { cidades = Array.isArray(data) ? data : []; preencher(); })
                    .catch(function () {
                        cidade.innerHTML = '<option value="">Não foi possível carregar as cidades</option>';
                    });

                estado.addEventListener('change', function () {
                    cidadeAtual = '';
                    if (!cidades) {
                        cidade.innerHTML = '<option value="">Carregando cidades...</option>';
                        return;
                    }
                    preencher();
                });

                if (estado.value) {
                    cidade.innerHTML = '<option value="">Carregando cidades...</option>';
                    cidade.disabled = true;
                } else {
                    preencher();
                }
            })();
            </script>
        <?php endif; ?>
        <?php Ui::fecharLayout();
    }

    /**
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $linhas
     * @param array<string, array<int, string>> $rotulosRelacoes
     */
    public static function csv(array $def, array $linhas, array $rotulosRelacoes = []): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="producao-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) ($def['tabela'] ?? 'dados')) . '.csv"');

        $saida = fopen('php://output', 'wb');
        if ($saida === false) {
            return;
        }

        $colunas = (array) $def['colunas'];
        fputcsv($saida, array_map(static fn(string $coluna): string => self::rotuloColuna($coluna, (array) ($def['relacoes'] ?? [])), $colunas), ';');
        foreach ($linhas as $linha) {
            $valores = [];
            foreach ($colunas as $coluna) {
                $valores[] = self::valor($linha, (string) $coluna, $rotulosRelacoes);
            }
            fputcsv($saida, $valores, ';');
        }

        fclose($saida);
    }

    /**
     * @param array<string, mixed> $linha
     * @param array<string, array<int, string>> $rotulosRelacoes
     */
    private static function valor(array $linha, string $coluna, array $rotulosRelacoes): string
    {
        $valor = $linha[$coluna] ?? '';
        if (isset($rotulosRelacoes[$coluna])) {
            return (string) ($rotulosRelacoes[$coluna][(int) $valor] ?? ('#' . (int) $valor));
        }
        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }
        if (str_contains($coluna, 'data') && trim((string) $valor) !== '') {
            $ts = strtotime((string) $valor);

            return $ts !== false ? date('d/m/Y', $ts) : (string) $valor;
        }
        if (is_numeric($valor) && preg_match('/^(valor|preco|quantidade|area|percentual|estoq|quant)/', $coluna)) {
            return number_format((float) $valor, 2, ',', '.');
        }

        return (string) $valor;
    }

    /** @param array<string, mixed> $relacoes */
    private static function rotuloColuna(string $coluna, array $relacoes): string
    {
        $mapa = [
            'nome' => 'Nome',
            'nome_lote' => 'Lote',
            'nome_animal' => 'Animal',
            'nome_instalacao' => 'Instalação',
            'nome_produto_final' => 'Produto final',
            'nome_comum' => 'Nome comum',
            'nome_fantasia' => 'Nome fantasia',
            'razao_social' => 'Razão social',
            'data_atividade' => 'Data',
            'data_producao' => 'Data produção',
            'data_plantio' => 'Plantio',
            'data_colheita' => 'Colheita',
            'data_deteccao' => 'Detecção',
            'data_processamento' => 'Processamento',
            'data_alimentacao' => 'Data',
            'data_tratamento' => 'Data',
            'data_evento' => 'Data',
            'data_ocorrencia' => 'Data',
            'data_hora_inicio' => 'Início',
            'quantidade' => 'Quantidade',
            'quantidade_estoque' => 'Estoque',
            'quantidade_kg' => 'Quantidade (kg)',
            'quantidade_final_obtida' => 'Quantidade final',
            'area_total' => 'Área (ha)',
            'area' => 'Área',
            'area_total_m2' => 'Área (m²)',
            'preco_unitario' => 'Preço unitário',
            'percentual_organico_calculado' => '% orgânico',
            'tipo_insumo' => 'Tipo',
            'tipo_atividade' => 'Tipo',
            'tipo_cultura' => 'Cultura',
            'tipo_alimento' => 'Tipo de alimento',
            'tipo_tratamento' => 'Tipo de tratamento',
            'tipo_evento' => 'Evento',
            'estoq_anterior' => 'Estoque anterior',
            'quant_recebida' => 'Recebida',
            'equipamento_linha' => 'Equipamento/linha',
            'sanitizante_utilizado' => 'Sanitizante',
            'responsavel_nome' => 'Responsável',
            'produto_utilizado' => 'Produto',
            'lote_processado_final' => 'Lote processado',
            'status_auditoria' => 'Auditoria',
        ];
        if (isset($mapa[$coluna])) {
            return $mapa[$coluna];
        }
        if (isset($relacoes[$coluna])) {
            $rel = ProducaoRecursos::relacionamentos()[(string) $relacoes[$coluna]['recurso']] ?? null;

            return $rel !== null ? (string) $rel['rotulo'] : mb_convert_case(str_replace('_', ' ', $coluna), MB_CASE_TITLE, 'UTF-8');
        }

        return mb_convert_case(str_replace('_', ' ', $coluna), MB_CASE_TITLE, 'UTF-8');
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

    private static function pastaImagem(string $recurso, string $campo): string
    {
        if ($recurso === 'ocorrencias' && $campo === 'foto_url') {
            return 'ocorrencias';
        }
        if ($recurso === 'propriedades') {
            return 'propriedades';
        }

        return 'propriedades';
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
