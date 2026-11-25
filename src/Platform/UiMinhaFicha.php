<?php

declare(strict_types=1);

namespace App\Platform;

/** Tela "Minha ficha": consulta e impressão da ficha de adesão do próprio associado. */
final class UiMinhaFicha
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param array<string, mixed>|null $ficha
     * @param list<array<string, mixed>> $produtos
     */
    public static function render(
        string $slug,
        array $app,
        array $identidade,
        ?array $ficha,
        array $produtos,
        bool $analise = false
    ): void {
        Ui::abrirLayout(
            $slug,
            $app,
            $identidade,
            $analise ? 'Ficha de adesão — análise' : 'Minha ficha',
            $analise ? 'aprovacoes.pendencias' : 'membro.minha-ficha'
        );
        $painel = Urls::app($slug, 'index.php');
        ?>
        <div class="flex flex-wrap items-end justify-between gap-3 print:hidden">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-teal-700">
                    <?= $analise ? 'Análise de adesão' : 'Meu cadastro' ?>
                </p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">
                    <?= $analise ? 'Ficha de adesão — análise' : 'Minha ficha de adesão' ?>
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    <?= $analise
                        ? 'Confira os dados completos antes de aprovar ou recusar a ficha.'
                        : 'Dados registrados na ficha de adesão ao quadro associativo.' ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($ficha !== null): ?>
                    <button type="button" onclick="window.print()"
                            class="rounded-full bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">
                        🖨 Imprimir / Salvar PDF
                    </button>
                <?php endif; ?>
                <?php if ($analise): ?>
                    <a href="<?= self::e(Urls::app($slug, 'pendencias.php')) ?>"
                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Pendências</a>
                <?php else: ?>
                    <?php if ($ficha !== null && Onboarding::precisaFicha($identidade)): ?>
                        <a href="<?= self::e(Urls::app($slug, 'ficha.php')) ?>"
                           class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">✏ Editar ficha</a>
                    <?php endif; ?>
                    <a href="<?= self::e(Urls::app($slug, 'meu-perfil.php')) ?>"
                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Meu cadastro</a>
                    <a href="<?= self::e($painel) ?>"
                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">← Painel</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($ficha === null): ?>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                Nenhuma ficha de adesão registrada para a sua conta.
                <?php if (Onboarding::precisaFicha($identidade)): ?>
                    <a class="font-semibold underline" href="<?= self::e(Urls::app($slug, 'ficha.php')) ?>">Preencher minha ficha</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            $situacao = (string) ($ficha['status'] ?? 'rascunho');
            $rotulo = MinhaFicha::rotuloStatus($situacao);
            $classes = match ($situacao) {
                'aprovada' => 'bg-green-100 text-green-900 ring-green-300',
                'pendente' => 'bg-amber-100 text-amber-900 ring-amber-300',
                'recusada' => 'bg-red-100 text-red-900 ring-red-300',
                default => 'bg-gray-100 text-gray-700 ring-gray-300',
            };
            $completa = Onboarding::completudeFicha($ficha);
            $secoes = [
                '1. Identificação' => [
                    ['Tipo de associado', MinhaFicha::rotuloTipo($ficha)],
                    ['Nome completo', $ficha['nome_completo'] ?? ''],
                    ['Nome social', $ficha['nome_social'] ?? ''],
                    ['Data de nascimento', self::data($ficha['data_nascimento'] ?? '')],
                    ['CPF', self::cpf($ficha['cpf'] ?? '')],
                    ['RG / órgão emissor', $ficha['rg_orgao_emissor'] ?? ''],
                    ['Sexo / gênero', $ficha['sexo_genero'] ?? ''],
                    ['Nacionalidade', $ficha['nacionalidade'] ?? ''],
                    ['Estado civil', $ficha['estado_civil'] ?? ''],
                    ['Escolaridade', $ficha['escolaridade'] ?? ''],
                ],
                '2. Contato' => [
                    ['Telefone / WhatsApp', $ficha['telefone_whatsapp'] ?? ''],
                    ['Telefone alternativo', $ficha['telefone_alternativo'] ?? ''],
                    ['E-mail', $ficha['email'] ?? ''],
                ],
                '3. Endereço' => [
                    ['Logradouro', $ficha['logradouro'] ?? ''],
                    ['Número', $ficha['numero'] ?? ''],
                    ['Complemento', $ficha['complemento'] ?? ''],
                    ['Bairro', $ficha['bairro'] ?? ''],
                    ['Município/UF', trim(($ficha['municipio'] ?? '') . '/' . ($ficha['estado'] ?? ''), '/')],
                    ['CEP', $ficha['cep'] ?? ''],
                    ['Zona', $ficha['zona'] ?? ''],
                    ['Georreferenciamento (SNCR)', $ficha['georreferenciamento_sncr'] ?? ''],
                    ['Latitude / longitude', $ficha['latitude_longitude'] ?? ''],
                ],
                '4. Propriedade e produção' => [
                    ['Nome da propriedade', $ficha['nome_propriedade'] ?? ''],
                    ['NIRF / ITR', $ficha['nirf_itr'] ?? ''],
                    ['Área total (ha)', self::decimal($ficha['area_total_ha'] ?? '')],
                    ['Área cultivada (ha)', self::decimal($ficha['area_cultivada_ha'] ?? '')],
                    ['Área de preservação (ha)', self::decimal($ficha['area_preservacao_ha'] ?? '')],
                    ['Situação fundiária', $ficha['situacao_fundiaria'] ?? ''],
                    ['DAP / CAF', $ficha['dap_caf'] ?? ''],
                    ['Pessoas na família', $ficha['pessoas_familia'] ?? ''],
                    ['Pessoas que trabalham', $ficha['pessoas_trabalham'] ?? ''],
                    ['Renda mensal estimada', self::decimal($ficha['renda_mensal_estimada'] ?? '')],
                    ['Tempo de produção agroecológica', $ficha['tempo_producao_agroecologica'] ?? ''],
                    ['Certificação orgânica', $ficha['certificacao_organica'] ?? ''],
                    ['Tipo de certificação', $ficha['tipo_certificacao'] ?? ''],
                    ['Sistemas de produção', self::lista($ficha['sistemas_producao'] ?? '')],
                    ['Canais de comercialização', self::lista($ficha['canais_comercializacao'] ?? '')],
                    ['Outra organização', $ficha['outra_organizacao'] ?? ''],
                ],
                '5. Dados bancários' => [
                    ['Banco', $ficha['banco'] ?? ''],
                    ['Agência', $ficha['agencia'] ?? ''],
                    ['Conta', $ficha['conta'] ?? ''],
                    ['Chave PIX', $ficha['chave_pix'] ?? ''],
                    ['Titular da conta', $ficha['titular_conta'] ?? ''],
                ],
            ];
            ?>
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-500">Nº de cadastro</p>
                        <p class="text-xl font-bold text-gray-900"><?= self::e((string) ($ficha['numero_cadastro'] ?: 'Aguardando aprovação')) ?></p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= $classes ?>"><?= self::e($rotulo) ?></span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Submetida em</dt><dd class="mt-0.5 text-gray-800"><?= self::e(self::data($ficha['data_submissao'] ?? '')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Analisada em</dt><dd class="mt-0.5 text-gray-800"><?= self::e(self::data($ficha['data_analise'] ?? '')) ?></dd></div>
                    <div><dt class="text-[11px] uppercase tracking-wide text-gray-500">Tipo</dt><dd class="mt-0.5 text-gray-800"><?= self::e(MinhaFicha::rotuloTipo($ficha)) ?></dd></div>
                </dl>
                <?php if ($situacao === 'recusada' && !empty($ficha['parecer_diretoria'])): ?>
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <strong>Parecer da Diretoria:</strong> <?= self::e((string) $ficha['parecer_diretoria']) ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($analise): ?>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= $completa['ok'] ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-800' ?>">
                    <?php if ($completa['ok']): ?>
                        <strong>Ficha completa.</strong> Confira os dados e confirme a completude na tela de pendências para aprovar.
                    <?php else: ?>
                        <strong>Ficha incompleta.</strong> Campos pendentes: <?= self::e(implode(', ', $completa['faltando'])) ?>.
                        A aprovação fica bloqueada até a correção.
                    <?php endif; ?>
                </div>
            <?php elseif (!$completa['ok'] && Onboarding::precisaFicha($identidade)): ?>
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <strong>Sua ficha ainda está incompleta.</strong> Faltam: <?= self::e(implode(', ', $completa['faltando'])) ?>.
                    Use <strong>Editar ficha</strong> para completar e reenviar.
                </div>
            <?php endif; ?>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <?php foreach ($secoes as $titulo => $campos): ?>
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold text-gray-900"><?= self::e($titulo) ?></h2>
                        <dl class="mt-3 grid gap-3 text-sm">
                            <?php foreach ($campos as [$rotuloCampo, $valor]): ?>
                                <div class="grid grid-cols-[140px_1fr] gap-2">
                                    <dt class="text-gray-500"><?= self::e($rotuloCampo) ?></dt>
                                    <dd class="break-words text-gray-800"><?= self::e(trim((string) $valor) !== '' ? (string) $valor : '—') ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endforeach; ?>
            </div>

            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">6. Produtos / culturas</h2>
                <?php if ($produtos === []): ?>
                    <p class="mt-2 text-sm text-gray-500">Nenhum produto/cultura informado na ficha.</p>
                <?php else: ?>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-2">Produto/cultura</th>
                                    <th class="px-3 py-2">Variedade/espécie</th>
                                    <th class="px-3 py-2">Produção estimada</th>
                                    <th class="px-3 py-2">Unidade</th>
                                    <th class="px-3 py-2">Destino principal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr class="border-b border-gray-100">
                                        <td class="px-3 py-2"><?= self::e((string) ($produto['produto_cultura'] ?? '—')) ?></td>
                                        <td class="px-3 py-2"><?= self::e((string) ($produto['variedade_especie'] ?? '—')) ?></td>
                                        <td class="px-3 py-2"><?= self::e(self::decimal($produto['producao_estimada'] ?? '')) ?></td>
                                        <td class="px-3 py-2"><?= self::e((string) ($produto['unidade'] ?? '—')) ?></td>
                                        <td class="px-3 py-2"><?= self::e((string) ($produto['destino_principal'] ?? '—')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">7. Observações e aceite</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700"><?= self::e((string) (($ficha['observacoes'] ?? '') !== '' ? $ficha['observacoes'] : '—')) ?></p>
                <p class="mt-3 text-xs text-gray-500">
                    Aceite dos termos: <?= (int) ($ficha['aceite_termos'] ?? 0) === 1 ? 'sim' : 'não' ?>
                </p>
            </section>
        <?php endif; ?>
        <?php
        Ui::fecharLayout();

        // Ao abrir "Minha ficha" (ou a análise), a ficha é exibida como modal,
        // da mesma forma que no sistema legado, enquanto não estiver aprovada.
        // O acesso explícito pelo menu também consome o aviso automático do login.
        if (!$analise && Onboarding::precisaFicha($identidade)) {
            unset($_SESSION['mostrar_ficha_modal']);
            UiFichaModal::render($slug, $app, $identidade);
        }
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private static function data(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '' || $texto === '0000-00-00') {
            return '—';
        }
        $ts = strtotime($texto);

        return $ts === false ? '—' : date('d/m/Y', $ts);
    }

    private static function decimal(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((float) $valor, 2, ',', '.');
    }

    private static function cpf(mixed $valor): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return strlen($digitos) === 11
            ? substr($digitos, 0, 3) . '.' . substr($digitos, 3, 3) . '.' . substr($digitos, 6, 3) . '-' . substr($digitos, 9, 2)
            : '—';
    }

    private static function lista(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        return implode(', ', array_map('trim', explode(',', $texto)));
    }
}
