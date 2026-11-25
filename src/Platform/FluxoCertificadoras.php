<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Rotas do módulo de Certificadoras: lista, nova, edição e membros.
 */
final class FluxoCertificadoras
{
    public static function executar(string $slug): void
    {
        Plataforma::iniciar($slug);
        $app = Plataforma::app();
        $identidade = Plataforma::usuario();

        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Plataforma::podeAcessar($identidade) || !Autorizacao::pode($identidade, 'fiscalizacao.read')) {
            Ui::negado($slug, $app, $identidade);
            exit;
        }

        $podeGerenciar = Autorizacao::pode($identidade, 'fiscalizacao.write')
            || Autorizacao::pode($identidade, 'fiscalizacao.certificate');

        $acao = trim((string) ($_GET['acao'] ?? ''));
        $erro = null;
        $registro = [];

        $flash = $_SESSION['certificadoras_flash'] ?? null;
        $flashErro = !empty($_SESSION['certificadoras_flash_erro']);
        unset($_SESSION['certificadoras_flash'], $_SESSION['certificadoras_flash_erro']);
        if ($flashErro && is_string($flash)) {
            $erro = $flash;
            $flash = null;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } elseif (!$podeGerenciar) {
                Ui::negado($slug, $app, $identidade);
                exit;
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $resultado = Certificadoras::salvar($_POST, $_FILES['logo'] ?? null, $id);
                if (!$resultado['ok']) {
                    $erro = $resultado['erro'];
                    $acao = $id > 0 ? 'editar' : 'nova';
                    $registro = $_POST;
                    $registro['id'] = $id;
                } else {
                    Auditoria::registrar(
                        $id > 0 ? 'CERTIFICADORA_ATUALIZADA' : 'CERTIFICADORA_CRIADA',
                        (int) ($identidade['id'] ?? 0),
                        (string) ($identidade['origem'] ?? ''),
                        $resultado['id'],
                        'Certificadora: ' . trim((string) ($_POST['nome'] ?? '')),
                        'social'
                    );
                    self::flash($id > 0 ? 'Certificadora atualizada com sucesso.' : 'Certificadora cadastrada com sucesso.');
                    self::redirecionar(Urls::app($slug, 'certificadoras.php'));
                }
            }
        }

        if ($acao === 'nova' || $acao === 'editar') {
            if (!$podeGerenciar) {
                Ui::negado($slug, $app, $identidade);
                exit;
            }
            if ($acao === 'editar' && $registro === []) {
                $id = (int) ($_GET['id'] ?? 0);
                $existente = $id > 0 ? Certificadoras::porId($id) : null;
                if ($existente === null) {
                    self::flash('Certificadora não encontrada.', true);
                    self::redirecionar(Urls::app($slug, 'certificadoras.php'));
                }
                $registro = $existente;
            }

            UiCertificadoras::formulario($slug, $app, $identidade, $acao, $registro, $erro, $podeGerenciar);
            return;
        }

        if ($acao === 'membros') {
            $id = (int) ($_GET['id'] ?? 0);
            $certificadora = $id > 0 ? Certificadoras::porId($id) : null;
            if ($certificadora === null) {
                self::flash('Certificadora não encontrada.', true);
                self::redirecionar(Urls::app($slug, 'certificadoras.php'));
            }
            UiCertificadoras::membros(
                $slug,
                $app,
                $identidade,
                $certificadora,
                Certificadoras::membros($id),
                $podeGerenciar
            );
            return;
        }

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'uf' => strtoupper(trim((string) ($_GET['uf'] ?? ''))),
        ];

        UiCertificadoras::listar(
            $slug,
            $app,
            $identidade,
            $filtros,
            Certificadoras::listar($filtros),
            Certificadoras::ufs(),
            is_string($flash) ? $flash : null,
            $erro,
            $podeGerenciar
        );
    }

    private static function flash(string $mensagem, bool $erro = false): void
    {
        $_SESSION['certificadoras_flash'] = $mensagem;
        if ($erro) {
            $_SESSION['certificadoras_flash_erro'] = true;
        }
    }

    private static function redirecionar(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
