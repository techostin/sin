<?php

declare(strict_types=1);

namespace App\Platform;

/** Rota "Meu plano de manejo" do agricultor (obrigatório em todas as áreas). */
final class FluxoPlanoManejo
{
    public static function executar(string $slug): void
    {
        Plataforma::iniciar($slug);
        if ($slug !== 'producao') {
            header('Location: ' . Urls::app($slug, 'index.php'), true, 303);
            exit;
        }

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Autorizacao::pode($identidade, 'caderno.read')) {
            Ui::erro(403, 'Sem permissão', 'Seu perfil não pode acessar o plano de manejo.');
            exit;
        }

        $associadoId = PlanoManejo::associadoId($identidade);
        if ($associadoId <= 0) {
            Ui::erro(403, 'Sem cadastro de associado', 'O plano de manejo é preenchido pelo agricultor vinculado a um associado.');
            exit;
        }

        $flash = $_SESSION['plano_flash'] ?? null;
        $erro = $_SESSION['plano_erro'] ?? null;
        unset($_SESSION['plano_flash'], $_SESSION['plano_erro']);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['plano_erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
                header('Location: ' . Urls::app($slug, 'meu-plano.php'), true, 303);
                exit;
            }

            $acao = (string) ($_POST['acao'] ?? '');
            if ($acao === 'excluir') {
                $resultado = PlanoManejo::excluir((int) ($_POST['id'] ?? 0), $associadoId);
            } elseif ($acao === 'croqui') {
                $resultado = PlanoManejo::uploadCroqui(
                    $associadoId,
                    (int) ($_POST['id'] ?? 0),
                    is_array($_FILES['croqui'] ?? null) ? $_FILES['croqui'] : []
                );
            } else {
                $resultado = PlanoManejo::salvar($associadoId, $_POST, (string) ($_POST['modo'] ?? 'submeter') !== 'rascunho');
            }

            if ($resultado['ok']) {
                $_SESSION['plano_flash'] = 'Plano de manejo atualizado.';
            } else {
                $_SESSION['plano_erro'] = $resultado['erro'];
            }
            header('Location: ' . Urls::app($slug, 'meu-plano.php'), true, 303);
            exit;
        }

        $modo = (string) ($_GET['modo'] ?? '');
        $plano = null;
        $visualizar = false;
        if ($modo === 'novo') {
            $plano = null;
        } elseif ($modo === 'editar' || $modo === 'ver') {
            $plano = PlanoManejo::porId((int) ($_GET['id'] ?? 0), $associadoId);
            if ($plano === null) {
                Ui::erro(404, 'Plano não encontrado', 'O plano solicitado não pertence ao seu cadastro.');
                exit;
            }
            if ($modo === 'editar' && !PlanoManejo::podeEditar($plano)) {
                $_SESSION['plano_erro'] = 'Este plano não pode ser editado no status atual.';
                header('Location: ' . Urls::app($slug, 'meu-plano.php'), true, 303);
                exit;
            }
            $visualizar = $modo === 'ver';
        } else {
            $lista = PlanoManejo::listar($associadoId);
            UiPlanoManejo::lista(
                $slug,
                Plataforma::app(),
                $identidade,
                $lista,
                is_string($flash) ? $flash : null,
                is_string($erro) ? $erro : null
            );
            return;
        }

        UiPlanoManejo::render(
            $slug,
            Plataforma::app(),
            $identidade,
            $plano,
            PlanoManejo::propriedades($associadoId),
            is_string($flash) ? $flash : null,
            is_string($erro) ? $erro : null,
            $visualizar
        );
    }
}
