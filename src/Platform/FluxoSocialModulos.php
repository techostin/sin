<?php

declare(strict_types=1);

namespace App\Platform;

/** Controller dos módulos de fiscalização do perfil técnico/gestor. */
final class FluxoSocialModulos
{
    public static function painel(string $slug): void
    {
        $identidade = self::contexto($slug, 'fiscalizacao.read');
        $flash = $_SESSION['social_flash'] ?? null;
        unset($_SESSION['social_flash']);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['social_flash'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $acao = (string) ($_POST['acao'] ?? '');
                $visitaId = (int) ($_POST['visita_id'] ?? 0);
                if ($acao === 'visita_ciente') {
                    $resultado = SocialModulos::registrarCienteVisita($visitaId, $identidade);
                } elseif ($acao === 'visita_reagendar') {
                    $resultado = SocialModulos::reagendarVisita(
                        $visitaId,
                        (string) ($_POST['data_visita'] ?? ''),
                        (string) ($_POST['hora_inicio'] ?? ''),
                        $identidade
                    );
                } else {
                    $resultado = ['ok' => false, 'erro' => 'Ação inválida.'];
                }
                $_SESSION['social_flash'] = $resultado['ok'] ? 'Visita atualizada.' : $resultado['erro'];
            }
            header('Location: ' . Urls::app($slug, 'painel.php'), true, 303);
            exit;
        }

        UiSocialModulos::painel(
            $slug,
            Plataforma::app(),
            $identidade,
            SocialModulos::kpis(),
            SocialModulos::proximasVisitas(),
            SocialModulos::visitasConfirmacao(),
            is_string($flash) ? $flash : null
        );
    }

    public static function modulo(string $slug): void
    {
        $identidade = self::contexto($slug);

        $modulo = (string) ($_GET['modulo'] ?? '');
        $def = SocialModulos::modulo($modulo);
        if ($def === null) {
            Ui::erro(404, 'Módulo não encontrado', 'Este módulo de fiscalização não existe.');
            exit;
        }

        $permissao = $modulo === 'validacao' ? 'fiscalizacao.write' : 'fiscalizacao.read';
        if (!Autorizacao::pode($identidade, $permissao)) {
            Ui::erro(403, 'Sem permissão', 'Seu perfil não pode acessar este módulo.');
            exit;
        }

        $flash = $_SESSION['social_flash'] ?? null;
        unset($_SESSION['social_flash']);
        $erro = null;
        $url = Urls::app($slug, 'modulo.php?modulo=' . rawurlencode($modulo));

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['social_flash'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $acao = (string) ($_POST['acao'] ?? '');
                $alvoId = (int) ($_POST['id'] ?? 0);
                if ($modulo === 'planos' && $acao === 'plano_parecer') {
                    $resultado = SocialModulos::registrarParecer(
                        $alvoId,
                        (string) ($_POST['status'] ?? ''),
                        (string) ($_POST['parecer'] ?? ''),
                        (string) ($_POST['sugestoes'] ?? ''),
                        $identidade
                    );
                    $_SESSION['social_flash'] = $resultado['ok'] ? 'Parecer registrado no plano.' : $resultado['erro'];
                    header('Location: ' . $url . '&ver=' . $alvoId, true, 303);
                    exit;
                }
                $resultado = SocialModulos::atualizar($modulo, $alvoId, $acao, $identidade);
                $_SESSION['social_flash'] = $resultado['ok'] ? 'Registro atualizado.' : $resultado['erro'];
            }
            header('Location: ' . $url, true, 303);
            exit;
        }

        if (isset($_GET['ver']) && $modulo === 'roteiros') {
            $roteiro = SocialModulos::roteiro((int) $_GET['ver']);
            if ($roteiro === null) {
                Ui::erro(404, 'Roteiro não encontrado', 'O roteiro solicitado não existe.');
                exit;
            }
            UiSocialModulos::roteiro($slug, Plataforma::app(), $identidade, $roteiro);
            return;
        }

        if (isset($_GET['ver']) && $modulo === 'planos') {
            $plano = SocialModulos::planoCompleto((int) $_GET['ver']);
            if ($plano === null) {
                Ui::erro(404, 'Plano não encontrado', 'O plano de manejo solicitado não existe.');
                exit;
            }
            UiSocialModulos::plano(
                $slug,
                Plataforma::app(),
                $identidade,
                $plano,
                is_string($flash) ? $flash : null
            );
            return;
        }

        $busca = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $linhas = SocialModulos::listar($modulo, $busca, $status);

        if (isset($_GET['exportar']) && (string) $_GET['exportar'] === 'csv') {
            self::csv($modulo, $linhas);
            exit;
        }

        UiSocialModulos::lista(
            $slug,
            Plataforma::app(),
            $identidade,
            $modulo,
            $def,
            $linhas,
            $busca,
            $status,
            is_string($flash) ? $flash : null,
            $erro
        );
    }

    public static function relatorios(string $slug): void
    {
        $identidade = self::contexto($slug, 'relatorios.read');

        UiSocialModulos::relatorios($slug, Plataforma::app(), $identidade, SocialModulos::kpis());
    }

    public static function visita(string $slug): void
    {
        $identidade = self::contexto($slug, 'fiscalizacao.read');
        $visita = SocialModulos::visitaCompleta((int) ($_GET['id'] ?? 0));
        if ($visita === null) {
            Ui::erro(404, 'Visita não encontrada', 'A visita solicitada não existe.');
            exit;
        }

        UiSocialModulos::visita($slug, Plataforma::app(), $identidade, $visita);
    }

    public static function realizar(string $slug): void
    {
        $identidade = self::contexto($slug, 'fiscalizacao.write');
        $visitaId = (int) ($_GET['id'] ?? 0);
        $flash = $_SESSION['social_flash'] ?? null;
        $erro = $_SESSION['social_erro'] ?? null;
        unset($_SESSION['social_flash'], $_SESSION['social_erro']);

        $visita = SocialModulos::visitaCompleta($visitaId);
        if ($visita === null) {
            Ui::erro(404, 'Visita não encontrada', 'A visita solicitada não existe.');
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['social_erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $post = $_POST;
                $evidencias = [];
                $arquivos = $_FILES['evidencias'] ?? [];
                if (is_array($arquivos['name'] ?? null)) {
                    $total = count($arquivos['name']);
                    for ($i = 0; $i < $total; $i++) {
                        $arquivo = [
                            'name' => $arquivos['name'][$i] ?? '',
                            'type' => $arquivos['type'][$i] ?? '',
                            'tmp_name' => $arquivos['tmp_name'][$i] ?? '',
                            'error' => $arquivos['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                            'size' => $arquivos['size'][$i] ?? 0,
                        ];
                        $upload = SocialModulos::uploadEvidenciaVisita($arquivo);
                        if ($upload['ok']) {
                            $evidencias[] = $upload['url'];
                        }
                    }
                }
                $post['evidencias_fotos'] = $evidencias;
                $resultado = SocialModulos::realizarVisita($visitaId, $post, $identidade);
                if ($resultado['ok']) {
                    $_SESSION['social_flash'] = 'Inspeção finalizada com sucesso.';
                    header('Location: ' . Urls::app($slug, 'visita.php?id=' . $visitaId), true, 303);
                    exit;
                }
                $_SESSION['social_erro'] = $resultado['erro'];
            }
            header('Location: ' . Urls::app($slug, 'realizar-visita.php?id=' . $visitaId), true, 303);
            exit;
        }

        UiSocialModulos::realizar(
            $slug,
            Plataforma::app(),
            $identidade,
            $visita,
            SocialModulos::checklistVisita($visita),
            SocialModulos::contextoInspecao($visitaId),
            is_string($flash) ? $flash : null,
            is_string($erro) ? $erro : null
        );
    }

    public static function nc(string $slug): void
    {
        $identidade = self::contexto($slug, 'fiscalizacao.read');
        $ncId = (int) ($_GET['id'] ?? 0);
        $nc = SocialModulos::ncCompleta($ncId);
        if ($nc === null) {
            Ui::erro(404, 'NC não encontrada', 'A não conformidade solicitada não existe.');
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['social_flash'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $acao = (string) ($_POST['acao'] ?? '');
                if ($acao === 'nc_salvar') {
                    $resultado = SocialModulos::atualizarNc($ncId, $_POST, $identidade);
                } elseif ($acao === 'nc_evidencia') {
                    $resultado = SocialModulos::uploadEvidenciaNc(
                        $ncId,
                        is_array($_FILES['evidencia_foto'] ?? null) ? $_FILES['evidencia_foto'] : [],
                        $identidade
                    );
                } else {
                    $resultado = ['ok' => false, 'erro' => 'Ação inválida.'];
                }
                $_SESSION['social_flash'] = $resultado['ok'] ? 'Não conformidade atualizada.' : $resultado['erro'];
            }
            header('Location: ' . Urls::app($slug, 'nc.php?id=' . $ncId), true, 303);
            exit;
        }

        $flash = $_SESSION['social_flash'] ?? null;
        unset($_SESSION['social_flash']);
        UiSocialModulos::nc($slug, Plataforma::app(), $identidade, $nc, is_string($flash) ? $flash : null);
    }

    public static function roteiroForm(string $slug): void
    {
        $identidade = self::contexto($slug, 'fiscalizacao.write');
        $flash = $_SESSION['social_flash'] ?? null;
        $erro = $_SESSION['social_erro'] ?? null;
        unset($_SESSION['social_flash'], $_SESSION['social_erro']);

        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $roteiro = $id !== null && $id > 0 ? SocialModulos::roteiro($id) : null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['social_erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } elseif ((string) ($_POST['acao'] ?? '') === 'excluir') {
                $resultado = SocialModulos::excluirRoteiro((int) ($_POST['id'] ?? 0), $identidade);
                $_SESSION['social_flash'] = $resultado['ok'] ? 'Roteiro excluído.' : $resultado['erro'];
                header('Location: ' . Urls::app($slug, 'modulo.php?modulo=roteiros'), true, 303);
                exit;
            } else {
                $resultado = SocialModulos::salvarRoteiro($id, $_POST, $identidade);
                if ($resultado['ok']) {
                    $_SESSION['social_flash'] = 'Roteiro salvo.';
                    header('Location: ' . Urls::app($slug, 'modulo.php?modulo=roteiros'), true, 303);
                    exit;
                }
                $_SESSION['social_erro'] = $resultado['erro'];
            }
            header('Location: ' . Urls::app($slug, 'roteiro.php' . ($id !== null && $id > 0 ? '?id=' . $id : '?novo=1')), true, 303);
            exit;
        }

        UiSocialModulos::roteiroForm(
            $slug,
            Plataforma::app(),
            $identidade,
            $roteiro,
            is_string($flash) ? $flash : null,
            is_string($erro) ? $erro : null
        );
    }

    public static function relatorio(string $slug): void
    {
        $identidade = self::contexto($slug, 'relatorios.read');
        $tipo = (string) ($_GET['tipo'] ?? 'visitas');
        if (!in_array($tipo, ['visitas', 'ncs'], true)) {
            $tipo = 'visitas';
        }
        $inicio = (string) ($_GET['inicio'] ?? '');
        $fim = (string) ($_GET['fim'] ?? '');
        $dados = SocialModulos::relatorio($tipo, $inicio, $fim);

        if (isset($_GET['formato']) && (string) $_GET['formato'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="relatorio-' . $tipo . '.csv"');
            $saida = fopen('php://output', 'wb');
            if ($saida !== false) {
                if ($dados['colunas'] !== []) {
                    fputcsv($saida, $dados['colunas'], ';');
                    foreach ($dados['linhas'] as $linha) {
                        fputcsv($saida, array_map(static fn($v): string => is_scalar($v) ? (string) $v : json_encode($v), $linha), ';');
                    }
                }
                fclose($saida);
            }
            exit;
        }

        UiSocialModulos::relatorio($slug, Plataforma::app(), $identidade, $tipo, $inicio, $fim, $dados);
    }

    /**
     * @return array<string, mixed>
     */
    private static function contexto(string $slug, string $permissao = ''): array
    {
        Plataforma::iniciar($slug);

        if ($slug !== 'social') {
            header('Location: ' . Urls::app($slug, 'index.php'), true, 303);
            exit;
        }

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Autorizacao::podeAcessar($identidade, $slug)) {
            Ui::erro(403, 'Sem acesso', 'Seu perfil não tem acesso ao controle social.');
            exit;
        }
        if ($permissao !== '' && !Autorizacao::pode($identidade, $permissao)) {
            Ui::erro(403, 'Sem permissão', 'Seu perfil não pode acessar esta funcionalidade.');
            exit;
        }

        return $identidade;
    }

    /** @param list<array<string, mixed>> $linhas */
    private static function csv(string $modulo, array $linhas): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="social-' . preg_replace('/[^a-z0-9_-]/i', '-', $modulo) . '.csv"');
        $saida = fopen('php://output', 'wb');
        if ($saida === false) {
            return;
        }
        if ($linhas !== []) {
            fputcsv($saida, array_keys($linhas[0]), ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, array_map(static fn($v): string => is_scalar($v) ? (string) $v : json_encode($v), $linha), ';');
            }
        }
        fclose($saida);
    }
}
