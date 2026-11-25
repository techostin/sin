<?php

declare(strict_types=1);

namespace App\Platform;

/** Rota "Minha ficha": visualização e impressão da ficha do próprio associado. */
final class FluxoMinhaFicha
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

        if (!Autorizacao::pode($identidade, 'associados.self') && !Autorizacao::ehAdmin($identidade)) {
            Ui::erro(403, 'Sem permissão', 'Você só pode consultar a sua própria ficha.');
            exit;
        }

        $dados = MinhaFicha::carregar($identidade);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['acao'] ?? '') === 'ficha') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $_SESSION['ficha_modal_erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $usuarioId = ($identidade['origem'] ?? '') === 'usuarios' ? (int) $identidade['id'] : 0;
                $rascunho = ($_POST['modo'] ?? 'submeter') === 'rascunho';
                $resultado = Onboarding::salvarFicha($usuarioId, $_POST, !$rascunho);
                $_SESSION[$resultado['success'] ? 'ficha_modal_sucesso' : 'ficha_modal_erro'] = $resultado['message'];
                $_SESSION['mostrar_ficha_modal'] = true;
            }
            header('Location: ' . Urls::app($slug, 'minha-ficha.php'), true, 303);
            exit;
        }

        UiMinhaFicha::render($slug, $app, $identidade, $dados['ficha'], $dados['produtos']);
    }
}
