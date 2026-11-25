<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Controller das telas "Meu perfil" e "Configurações" dos três sistemas.
 */
final class FluxoConta
{
    public static function meuPerfil(string $slug): void
    {
        $identidade = self::abrir($slug);
        $app = Plataforma::app();
        $flash = self::consumirFlash();
        $erro = null;
        $dados = Conta::dados($identidade);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $resultado = Conta::atualizarPerfil($identidade, $_POST);
                if (!$resultado['ok']) {
                    $erro = $resultado['erro'];
                } else {
                    Auditoria::registrar(
                        'PERFIL_ATUALIZADO',
                        (int) ($identidade['id'] ?? 0),
                        (string) ($identidade['origem'] ?? ''),
                        (int) ($identidade['id'] ?? 0),
                        'Dados do próprio perfil atualizados',
                        $slug
                    );
                    self::flash('Dados atualizados com sucesso.');
                    self::redirecionar(Urls::app($slug, 'meu-perfil.php'));
                }
            }
            $dados = Conta::dados($identidade);
        }

        UiConta::perfil($slug, $app, $identidade, $dados, $flash, $erro);
    }

    public static function configuracoes(string $slug): void
    {
        $identidade = self::abrir($slug);
        $app = Plataforma::app();
        $flash = self::consumirFlash();
        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $acao = (string) ($_POST['acao'] ?? '');
                if ($acao === 'preferencias') {
                    $resultado = Conta::salvarPreferencias($identidade, $_POST);
                    if ($resultado['ok']) {
                        self::flash('Preferências salvas com sucesso.');
                        self::redirecionar(Urls::app($slug, 'configuracoes.php'));
                    }
                    $erro = $resultado['erro'];
                } elseif ($acao === 'notificacoes') {
                    $resultado = Conta::salvarNotificacoes($identidade, $_POST);
                    if ($resultado['ok']) {
                        self::flash('Notificações atualizadas com sucesso.');
                        self::redirecionar(Urls::app($slug, 'configuracoes.php'));
                    }
                    $erro = $resultado['erro'];
                } elseif ($acao === 'senha') {
                    $resultado = Conta::alterarSenha(
                        $identidade,
                        (string) ($_POST['senha_atual'] ?? ''),
                        (string) ($_POST['senha_nova'] ?? ''),
                        (string) ($_POST['senha_confirma'] ?? '')
                    );
                    if ($resultado['ok']) {
                        Auditoria::registrar(
                            'SENHA_ALTERADA',
                            (int) ($identidade['id'] ?? 0),
                            (string) ($identidade['origem'] ?? ''),
                            (int) ($identidade['id'] ?? 0),
                            'Senha da própria conta alterada',
                            $slug
                        );
                        self::flash('Senha alterada com sucesso.');
                        self::redirecionar(Urls::app($slug, 'configuracoes.php'));
                    }
                    $erro = $resultado['erro'];
                }
            }
        }

        UiConta::configuracoes($slug, $app, $identidade, Conta::dados($identidade), Conta::preferencias(), $flash, $erro);
    }

    /** @return array<string, mixed> */
    private static function abrir(string $slug): array
    {
        Plataforma::iniciar($slug);
        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Plataforma::podeAcessar($identidade)) {
            Ui::negado($slug, Plataforma::app(), $identidade);
            exit;
        }

        return $identidade;
    }

    private static function flash(string $mensagem): void
    {
        $_SESSION['conta_flash'] = $mensagem;
    }

    private static function consumirFlash(): ?string
    {
        $flash = $_SESSION['conta_flash'] ?? null;
        unset($_SESSION['conta_flash']);

        return is_string($flash) ? $flash : null;
    }

    private static function redirecionar(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
