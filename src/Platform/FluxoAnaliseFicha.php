<?php

declare(strict_types=1);

namespace App\Platform;

/** Página de conferência da ficha pela Diretoria/Presidência antes da aprovação. */
final class FluxoAnaliseFicha
{
    public static function executar(string $slug): void
    {
        Plataforma::iniciar($slug);

        if ($slug !== 'assoc') {
            header('Location: ' . Urls::app($slug, 'index.php'), true, 303);
            exit;
        }

        $app = Plataforma::app();
        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }

        if (!Autorizacao::pode($identidade, 'associados.approve')) {
            Ui::erro(403, 'Sem permissão', 'Apenas a Diretoria/Presidência ou o administrador podem analisar fichas.');
            exit;
        }

        $fichaId = (int) ($_GET['id'] ?? 0);
        $dados = MinhaFicha::carregarPorId($fichaId);
        if ($dados['ficha'] === null) {
            Ui::erro(404, 'Ficha não encontrada', 'A ficha solicitada não existe ou foi removida.');
            exit;
        }

        UiMinhaFicha::render($slug, $app, $identidade, $dados['ficha'], $dados['produtos'], true);
    }
}
