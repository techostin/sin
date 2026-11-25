<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Modal global da ficha de adesão no dashboard do associado.
 *
 * Reproduz o comportamento do sistema atual (`includes/ficha_adesao_modal.php`):
 * ao entrar, o dashboard abre e o modal aparece automaticamente;
 *  - sem ficha/rascunho/recusada: formulário completo para preencher;
 *  - pendente: aviso de "em análise" (com data), reaparecendo a cada acesso até a aprovação;
 *  - aprovada: nada é exibido.
 */
final class UiFichaModal
{
    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    public static function render(string $slug, array $app, array $identidade): void
    {
        $status = Onboarding::statusFicha((int) ($identidade['id'] ?? 0));
        $situacao = (string) ($status['status'] ?? 'sem_ficha');

        if ($situacao === 'aprovada') {
            return;
        }

        $ficha = is_array($status['ficha'] ?? null) ? $status['ficha'] : [];
        $produtos = is_array($status['produtos'] ?? null) ? $status['produtos'] : [];

        $erro = $_SESSION['ficha_modal_erro'] ?? null;
        $sucesso = $_SESSION['ficha_modal_sucesso'] ?? null;
        unset($_SESSION['ficha_modal_erro'], $_SESSION['ficha_modal_sucesso']);

        $rotulos = [
            'sem_ficha' => ['Sem ficha enviada', 'bg-amber-200 text-amber-950'],
            'rascunho' => ['Rascunho', 'bg-amber-200 text-amber-950'],
            'pendente' => ['Em análise', 'bg-sky-200 text-sky-950'],
            'recusada' => ['Recusada', 'bg-red-200 text-red-950'],
        ];
        [$rotulo, $classes] = $rotulos[$situacao] ?? $rotulos['sem_ficha'];
        ?>
        <div id="modal-ficha-adesao" role="dialog" aria-modal="true" aria-labelledby="modal-ficha-titulo"
             class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto bg-gray-900/70 p-3 sm:p-6">
            <div class="my-4 w-full max-w-5xl overflow-hidden rounded-3xl bg-gray-50 shadow-2xl">
                <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-600 px-5 py-4 text-white sm:px-6">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/70">Primeiro acesso</p>
                        <h2 id="modal-ficha-titulo" class="text-xl font-bold leading-tight sm:text-2xl">Ficha de adesão ao quadro associativo</h2>
                        <p class="mt-1 text-xs text-white/80">
                            Preencha a ficha para concluir seu cadastro. Ela é analisada pela Diretoria/Presidência.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $classes ?>"><?= self::e($rotulo) ?></span>
                        <button type="button" onclick="sinporgFecharFichaModal()" aria-label="Fechar"
                                class="rounded-lg px-2 py-1 text-2xl leading-none text-white/80 hover:bg-white/10 hover:text-white">×</button>
                    </div>
                </div>

                <div class="max-h-[78vh] overflow-y-auto px-4 py-4 sm:px-6">
                    <?php if ($erro !== null): ?>
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= self::e((string) $erro) ?></div>
                    <?php endif; ?>
                    <?php if ($sucesso !== null): ?>
                        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"><?= self::e((string) $sucesso) ?></div>
                    <?php endif; ?>

                    <?php if ($situacao === 'pendente'): ?>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl" aria-hidden="true">⏳</span>
                                <div>
                                    <h3 class="text-base font-semibold text-amber-900">Ficha em análise</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-amber-900">
                                        Sua ficha foi recebida
                                        <?php if (!empty($ficha['data_submissao'])): ?>
                                            em <strong><?= self::e(date('d/m/Y', strtotime((string) $ficha['data_submissao']))) ?></strong>
                                        <?php endif; ?>
                                        e aguarda a análise da Diretoria/Presidência. O cadastro de associado é criado na aprovação.
                                    </p>
                                    <p class="mt-2 text-xs text-amber-800">Este aviso reaparece a cada novo acesso até a aprovação da ficha.</p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="<?= self::e(Urls::app($slug, 'ficha.php')) ?>"
                                   class="rounded-full bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                                    Ver minha ficha
                                </a>
                            </div>
                            <p class="mt-3 text-[11px] text-amber-800">
                                Este aviso permanece até a aprovação pela Diretoria/Presidência.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php if ($situacao === 'recusada' && !empty($ficha['parecer_diretoria'])): ?>
                            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                <strong>Ficha recusada.</strong> Parecer: <?= self::e((string) $ficha['parecer_diretoria']) ?>
                                <span class="mt-1 block text-xs">Corrija as informações e envie novamente.</span>
                            </div>
                        <?php endif; ?>

                        <?php UiLogin::formFicha($ficha, $produtos, true, UiLogin::tipoPadraoFicha($identidade)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
        (function () {
            document.body.classList.add('overflow-hidden');

            window.sinporgFecharFichaModal = function () {
                var modal = document.getElementById('modal-ficha-adesao');
                if (modal) { modal.remove(); }
                document.body.classList.remove('overflow-hidden');
            };

            var form = document.querySelector('#modal-ficha-adesao form');
            if (!form) { return; }

            function digitos(v) { return String(v || '').replace(/\D+/g, ''); }
            function mascaraCpf(v) {
                var d = digitos(v).slice(0, 11);
                return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            function mascaraFone(v) {
                var d = digitos(v).slice(0, 11);
                if (d.length > 10) { return d.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3'); }
                return d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            }
            function mascaraCep(v) {
                var d = digitos(v).slice(0, 8);
                return d.replace(/(\d{5})(\d)/, '$1-$2');
            }

            [['cpf', mascaraCpf], ['telefone_whatsapp', mascaraFone], ['telefone_alternativo', mascaraFone], ['cep', mascaraCep]]
                .forEach(function (par) {
                    var campo = form.querySelector('[name="' + par[0] + '"]');
                    if (!campo) { return; }
                    campo.addEventListener('input', function () { this.value = par[1](this.value); });
                });

            form.addEventListener('submit', function (evento) {
                var botao = evento.submitter;
                var modo = botao && botao.value ? botao.value : 'submeter';

                ['area_total_ha', 'area_cultivada_ha', 'area_preservacao_ha', 'renda_mensal_estimada'].forEach(function (nome) {
                    var campo = form.querySelector('[name="' + nome + '"]');
                    if (!campo || campo.value.trim() === '') { return; }
                    var v = campo.value.trim().replace(/\./g, '').replace(',', '.');
                    if (!isNaN(parseFloat(v))) { campo.value = v; }
                });

                if (modo === 'rascunho') {
                    return;
                }

                var cpf = form.querySelector('[name="cpf"]');
                if (cpf && digitos(cpf.value).length !== 11) {
                    evento.preventDefault();
                    alert('Informe um CPF válido (11 dígitos).');
                    cpf.focus();
                    return;
                }
                var aceite = form.querySelector('[name="aceite_termos"]');
                if (aceite && !aceite.checked) {
                    evento.preventDefault();
                    alert('É necessário marcar a declaração de veracidade (aceite) para submeter.');
                    aceite.focus();
                }
            });
        })();

        function sinporgFecharFichaModal() {
            var modal = document.getElementById('modal-ficha-adesao');
            if (modal) { modal.remove(); }
            document.body.classList.remove('overflow-hidden');
        }
        </script>
        <?php
    }

    private static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
