<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Tela da diretoria: liberar acessos (pré-cadastros) e aprovar fichas.
 *
 * Reproduz a lógica das telas do sistema atual (`modulos/associados/pendentes.php`
 * + fichas): a conta precisa ser liberada primeiro; depois a ficha de adesão é
 * submetida e aprovada — só então o cadastro de associado é criado.
 */
final class UiPendencias
{
    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     * @param list<array<string, mixed>> $contas
     * @param list<array<string, mixed>> $fichas
     * @param array{tipo: string, texto: string}|null $flash
     */
    public static function render(
        string $slug,
        array $app,
        array $identidade,
        array $contas,
        array $fichas,
        ?array $flash,
        ?string $erro
    ): void {
        $isAdmin = Autorizacao::ehAdmin($identidade);
        $csrf = Csrf::token();

        Ui::abrirLayout($slug, $app, $identidade, 'Pendências e aprovações', 'aprovacoes.pendencias');
        ?>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Pendências e aprovações</h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">
                Primeiro libere o acesso da conta. Depois da ficha enviada pelo produtor, aprove o
                cadastro para criar o vínculo de associado.
            </p>
        </div>
        <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-200">
            <?= count($contas) ?> conta(s) · <?= count($fichas) ?> ficha(s)
        </span>
    </div>

    <?php if ($flash !== null): ?>
        <div role="status" class="mt-5 rounded-2xl border px-4 py-3 text-sm <?= $flash['tipo'] === 'ok'
            ? 'border-green-200 bg-green-50 text-green-900'
            : 'border-red-200 bg-red-50 text-red-800' ?>">
            <?= self::e($flash['texto']) ?>
        </div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div role="alert" class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e($erro) ?></div>
    <?php endif; ?>

    <!-- 1. Contas aguardando liberação -->
    <section class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900">1. Contas aguardando liberação</h2>
        <p class="mt-1 text-sm text-gray-500">Cadastros do pré-cadastro. A conta só entra no sistema após esta liberação.</p>

        <?php if ($contas === []): ?>
            <p class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-500">
                Nenhuma conta pendente.
            </p>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($contas as $conta): ?>
                    <?php
                    $contaId = (int) ($conta['id'] ?? 0);
                    $solicitado = Pendencias::nivelSolicitado($conta);
                    $perfilSolicitado = (string) ($conta['perfil_solicitado'] ?? '');
                    $rotuloPerfil = $perfilSolicitado !== ''
                        ? (Cadastro::SITUACOES[$perfilSolicitado]['rotulo'] ?? $perfilSolicitado)
                        : 'não informado';
                    $nome = trim((string) ($conta['nome'] ?? '') . ' ' . (string) ($conta['sobrenome'] ?? ''));
                    ?>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900"><?= self::e($nome) ?></p>
                                <p class="text-xs text-gray-500"><?= self::e((string) ($conta['email'] ?? '')) ?>
                                    <?php if (!empty($conta['telefone'])): ?> · <?= self::e((string) $conta['telefone']) ?><?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-600">
                                    Situação: <strong><?= self::e($rotuloPerfil) ?></strong>
                                    · nível solicitado: <strong><?= self::e(Pendencias::rotuloNivel($solicitado)) ?></strong>
                                </p>
                                <p class="text-[11px] text-gray-400">recebido em <?= self::e((string) ($conta['created_at'] ?? '')) ?></p>
                            </div>

                            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <form method="post" action="<?= self::e(Urls::app($slug, 'pendencias.php')) ?>" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="csrf" value="<?= self::e($csrf) ?>">
                                    <input type="hidden" name="tipo" value="acesso">
                                    <input type="hidden" name="acao" value="liberar">
                                    <input type="hidden" name="id" value="<?= $contaId ?>">
                                    <select name="nivel_concedido" class="rounded-xl border border-gray-300 px-3 py-2 text-xs" aria-label="Nível concedido">
                                        <?php if ($isAdmin): ?>
                                            <option value="1" <?= $solicitado === 1 ? 'selected' : '' ?>>Admin</option>
                                        <?php endif; ?>
                                        <option value="2" <?= $solicitado === 2 ? 'selected' : '' ?>>Fiscal/Técnico</option>
                                        <option value="3" <?= $solicitado === 3 ? 'selected' : '' ?>>Usuário</option>
                                    </select>
                                    <button type="submit" class="rounded-full bg-green-700 px-4 py-2 text-xs font-semibold text-white hover:bg-green-800">
                                        Liberar acesso
                                    </button>
                                </form>

                                <form method="post" action="<?= self::e(Urls::app($slug, 'pendencias.php')) ?>" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="csrf" value="<?= self::e($csrf) ?>">
                                    <input type="hidden" name="tipo" value="acesso">
                                    <input type="hidden" name="acao" value="negar">
                                    <input type="hidden" name="id" value="<?= $contaId ?>">
                                    <input type="text" name="motivo" required placeholder="Motivo da recusa"
                                           class="rounded-xl border border-gray-300 px-3 py-2 text-xs">
                                    <button type="submit" class="rounded-full border border-red-300 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                        Negar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- 2. Fichas aguardando aprovação -->
    <section class="mt-10">
        <h2 class="text-lg font-semibold text-gray-900">2. Fichas de adesão aguardando aprovação</h2>
        <p class="mt-1 text-sm text-gray-500">
            A aprovação cria o cadastro em <code>associados</code>, gera número/data de cadastro e vincula a conta.
        </p>

        <?php if ($fichas === []): ?>
            <p class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-500">
                Nenhuma ficha pendente.
            </p>
        <?php else: ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($fichas as $ficha): ?>
                    <?php
                    $fichaId = (int) ($ficha['id'] ?? 0);
                    $nomeFicha = (string) ($ficha['nome'] ?? $ficha['nome_completo'] ?? '—');
                    $cpf = preg_replace('/\D+/', '', (string) ($ficha['cpf'] ?? '')) ?? '';
                    $cpfMascarado = strlen($cpf) === 11 ? '***.***.***-' . substr($cpf, -2) : '—';
                    $tipo = (string) ($ficha['tipo_associado'] ?? '');
                    $rotuloTipo = \FichaAdesao::TIPOS_ASSOCIADO[$tipo] ?? $tipo;
                    $completa = Onboarding::completudeFicha($ficha);
                    ?>
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900"><?= self::e($nomeFicha) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= self::e((string) ($ficha['email'] ?? '—')) ?>
                                    <?php if (!empty($ficha['telefone'])): ?> · <?= self::e((string) $ficha['telefone']) ?><?php endif; ?>
                                </p>
                                <p class="mt-1 text-xs text-gray-600">
                                    <?= self::e($rotuloTipo) ?> · CPF <?= self::e($cpfMascarado) ?>
                                    · <?= self::e((string) ($ficha['municipio'] ?? '—')) ?>/<?= self::e((string) ($ficha['estado'] ?? '—')) ?>
                                </p>
                                <p class="text-[11px] text-gray-400">
                                    propriedade: <?= self::e((string) ($ficha['nome_propriedade'] ?? '—')) ?>
                                    · enviada em <?= self::e((string) ($ficha['data_submissao'] ?? '—')) ?>
                                </p>
                                <p class="mt-2">
                                    <?php if ($completa['ok']): ?>
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-800 ring-1 ring-green-200">Ficha completa</span>
                                    <?php else: ?>
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-800 ring-1 ring-red-200">
                                            Ficha incompleta: <?= self::e(implode(', ', $completa['faltando'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <a href="<?= self::e(Urls::app($slug, 'ficha-analise.php?id=' . $fichaId)) ?>"
                                   class="rounded-full border border-teal-300 bg-teal-50 px-4 py-2 text-center text-xs font-semibold text-teal-800 hover:bg-teal-100">
                                    Conferir ficha completa
                                </a>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                                    <form method="post" action="<?= self::e(Urls::app($slug, 'pendencias.php')) ?>" class="flex flex-col gap-2">
                                        <input type="hidden" name="csrf" value="<?= self::e($csrf) ?>">
                                        <input type="hidden" name="tipo" value="ficha">
                                        <input type="hidden" name="acao" value="aprovar">
                                        <input type="hidden" name="id" value="<?= $fichaId ?>">
                                        <label class="flex max-w-[220px] items-start gap-2 text-[11px] leading-snug text-gray-600">
                                            <input type="checkbox" name="ficha_completa" value="1" required <?= $completa['ok'] ? '' : 'disabled' ?> class="mt-0.5">
                                            Confirmo que conferi a ficha e que ela está completa.
                                        </label>
                                        <button type="submit" <?= $completa['ok'] ? '' : 'disabled' ?>
                                                class="w-full rounded-full bg-green-700 px-4 py-2 text-xs font-semibold text-white hover:bg-green-800 disabled:cursor-not-allowed disabled:bg-gray-300">
                                            Aprovar ficha
                                        </button>
                                    </form>

                                    <form method="post" action="<?= self::e(Urls::app($slug, 'pendencias.php')) ?>" class="flex flex-wrap items-start gap-2">
                                        <input type="hidden" name="csrf" value="<?= self::e($csrf) ?>">
                                        <input type="hidden" name="tipo" value="ficha">
                                        <input type="hidden" name="acao" value="recusar">
                                        <input type="hidden" name="id" value="<?= $fichaId ?>">
                                        <input type="text" name="motivo" required placeholder="Parecer da recusa"
                                               class="rounded-xl border border-gray-300 px-3 py-2 text-xs">
                                        <button type="submit" class="rounded-full border border-red-300 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Recusar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
        <?php Ui::fecharLayout(); ?>
        <?php
    }
}
