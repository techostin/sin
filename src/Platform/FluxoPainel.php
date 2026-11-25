<?php

declare(strict_types=1);

namespace App\Platform;

/** Controllers dos painéis de entrada de associação e produção. */
final class FluxoPainel
{
    public static function assoc(string $slug): void
    {
        $identidade = self::contexto($slug);
        self::processarVisita($slug, $identidade);
        $tema = Autorizacao::ehAdmin($identidade) ? 'admin' : 'assoc';
        $painel = PainelDados::assoc($identidade);
        if ($tema === 'admin') {
            $painel['titulo'] = 'Painel do Administrador';
        }
        $flash = $_SESSION['painel_flash'] ?? null;
        unset($_SESSION['painel_flash']);

        UiPainel::render($slug, Plataforma::app(), $identidade, $painel, $tema, is_string($flash) ? $flash : null);
        self::modalFicha($slug, $identidade);
        self::modalVisitaAgricultor($slug, $identidade);
    }

    public static function producao(string $slug): void
    {
        $identidade = self::contexto($slug);
        self::processarVisita($slug, $identidade);
        $flash = $_SESSION['painel_flash'] ?? null;
        unset($_SESSION['painel_flash']);

        UiPainel::render($slug, Plataforma::app(), $identidade, PainelDados::producao($identidade), 'producao', is_string($flash) ? $flash : null);
        self::modalFicha($slug, $identidade);
        self::modalPlanoObrigatorio($slug, $identidade);
        self::modalVisitaAgricultor($slug, $identidade);
    }

    /** @return array<string, mixed> */
    private static function contexto(string $slug): array
    {
        Plataforma::iniciar($slug);
        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Plataforma::podeAcessar($identidade) && !Onboarding::precisaFicha($identidade)) {
            Ui::negado($slug, Plataforma::app(), $identidade);
            exit;
        }

        return $identidade;
    }

    /** @param array<string, mixed> $identidade */
    private static function modalFicha(string $slug, array $identidade): void
    {
        // Só no primeiro dashboard após o login. Depois disso, a ficha só
        // aparece se o usuário abrir "Minha ficha" pelo menu.
        if (empty($_SESSION['mostrar_ficha_modal'])) {
            return;
        }
        unset($_SESSION['mostrar_ficha_modal']);

        if (!in_array($slug, ['assoc', 'producao'], true) || !Onboarding::precisaFicha($identidade)) {
            return;
        }

        UiFichaModal::render($slug, Plataforma::app(), $identidade);
    }

    /** @param array<string, mixed> $identidade */
    private static function modalPlanoObrigatorio(string $slug, array $identidade): void
    {
        $associadoId = PainelDados::associadoId($identidade);
        if ($associadoId <= 0 || PlanoManejo::situacao($associadoId)['ok']) {
            return;
        }

        $propriedades = PlanoManejo::propriedades($associadoId);
        UiPainel::modalPlanoObrigatorio($slug, $propriedades === []);
    }

    /** @param array<string, mixed> $identidade */
    private static function modalVisitaAgricultor(string $slug, array $identidade): void
    {
        $associadoId = PainelDados::associadoId($identidade);
        if ($associadoId <= 0) {
            return;
        }

        $visitas = SocialModulos::visitasPendentesAgricultor($associadoId);
        if ($visitas !== []) {
            UiPainel::modalVisitasAgricultor($slug, $identidade, $visitas);
        }
    }

    /** @param array<string, mixed> $identidade */
    private static function processarVisita(string $slug, array $identidade): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $acao = (string) ($_POST['acao'] ?? '');
        if (!in_array($acao, ['visita_confirmar', 'visita_recusar'], true)) {
            return;
        }
        if (!Csrf::validar($_POST['csrf'] ?? null)) {
            $_SESSION['painel_flash'] = 'Sessão expirada. Recarregue a página.';
            header('Location: ' . Urls::app($slug, 'painel.php'), true, 303);
            exit;
        }

        $associadoId = PainelDados::associadoId($identidade);
        $resultado = $acao === 'visita_confirmar'
            ? SocialModulos::confirmarVisitaAgricultor((int) ($_POST['visita_id'] ?? 0), $associadoId)
            : SocialModulos::recusarVisitaAgricultor((int) ($_POST['visita_id'] ?? 0), $associadoId, (string) ($_POST['motivo'] ?? ''));

        $_SESSION['painel_flash'] = $resultado['ok'] ? 'Resposta registrada.' : $resultado['erro'];
        header('Location: ' . Urls::app($slug, 'painel.php'), true, 303);
        exit;
    }
}
