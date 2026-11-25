<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Tela "Papéis e permissões": diretor/presidente e admin definem os papéis
 * do associado no módulo de associação.
 */
final class FluxoPapeis
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

        // Gestão de papéis é uma função administrativa GLOBAL: exclusiva do admin.
        if (!Autorizacao::ehAdmin($identidade)) {
            Ui::erro(403, 'Sem permissão', 'Apenas o administrador do sistema pode definir papéis.');
            exit;
        }

        $flash = $_SESSION['papeis_flash'] ?? null;
        unset($_SESSION['papeis_flash']);
        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
                $codigos = $_POST['papeis'] ?? [];
                $codigos = is_array($codigos) ? array_map('strval', $codigos) : [];
                $resultado = Papeis::salvar($usuarioId, $codigos, (int) ($identidade['id'] ?? 0));

                if ($resultado['ok']) {
                    $_SESSION['papeis_flash'] = 'Papéis atualizados com sucesso.';
                    header('Location: ' . Urls::app($slug, 'papeis.php?usuario_id=' . $usuarioId), true, 303);
                    exit;
                }
                $erro = $resultado['erro'];
            }
        }

        $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
        if ($usuarioId > 0) {
            $usuario = Papeis::porUsuario($usuarioId);
            if ($usuario === null) {
                $_SESSION['papeis_flash'] = 'Usuário não encontrado.';
                header('Location: ' . Urls::app($slug, 'papeis.php'), true, 303);
                exit;
            }

            UiPapeis::editar(
                $slug,
                $app,
                $identidade,
                $usuario,
                Papeis::disponiveis(),
                Papeis::papeisDoUsuario($usuarioId),
                is_string($flash) ? $flash : null,
                $erro
            );
            return;
        }

        UiPapeis::listar(
            $slug,
            $app,
            $identidade,
            trim((string) ($_GET['q'] ?? '')),
            Papeis::listarUsuarios(trim((string) ($_GET['q'] ?? ''))),
            is_string($flash) ? $flash : null,
            $erro
        );
    }
}
