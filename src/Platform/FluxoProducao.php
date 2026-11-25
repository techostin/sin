<?php

declare(strict_types=1);

namespace App\Platform;

/** Controller do CRUD de recursos do painel Produção do Agricultor. */
final class FluxoProducao
{
    public static function executar(string $slug): void
    {
        Plataforma::iniciar($slug);

        if ($slug !== 'producao') {
            header('Location: ' . Urls::app($slug, 'index.php'), true, 303);
            exit;
        }

        $app = Plataforma::app();
        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            header('Location: ' . Urls::app($slug, 'login.php'), true, 303);
            exit;
        }
        if (!Plataforma::podeAcessar($identidade)) {
            Ui::erro(403, 'Sem acesso', 'Seu perfil não tem acesso ao painel de produção.');
            exit;
        }

        $recurso = trim((string) ($_GET['recurso'] ?? ''));
        $def = ProducaoRecursos::definicao($recurso);
        if ($def === null) {
            Ui::erro(404, 'Recurso não encontrado', 'Este recurso de produção não existe.');
            exit;
        }
        if (!ProducaoRegistros::podeAcessarRecurso($recurso, $identidade)) {
            Ui::erro(403, 'Área não habilitada', 'Sua produção não inclui este módulo (vegetal, animal ou agroindústria).');
            exit;
        }

        $flash = $_SESSION['producao_flash'] ?? null;
        unset($_SESSION['producao_flash']);
        $erro = null;
        $url = Urls::app($slug, 'registro.php?recurso=' . rawurlencode($recurso));

        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($metodo === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['producao_flash'] = 'Sessão expirada. Recarregue a página e tente novamente.';
                header('Location: ' . $url, true, 303);
                exit;
            }

            $acao = (string) ($_POST['acao'] ?? 'salvar');
            $id = (int) ($_POST['id'] ?? 0);

            if ($acao === 'excluir') {
                $resultado = ProducaoRegistros::excluir($recurso, $id, $identidade);
            } else {
                $post = $_POST;
                $imagensSubstituidas = [];
                $uploads = [];
                if ($recurso === 'propriedades') {
                    $uploads = [
                        'imagem_principal' => ['principal', 'propriedades'],
                        'imagem_secundaria' => ['secundaria', 'propriedades'],
                    ];
                } elseif ($recurso === 'ocorrencias') {
                    $uploads = ['foto_url' => ['ocorrencia', 'ocorrencias']];
                }

                if ($uploads !== []) {
                    $atual = $id > 0 ? ProducaoRegistros::obter($recurso, $id, $identidade) : [];
                    foreach ($uploads as $campo => [$prefixo, $pasta]) {
                        $arquivo = $_FILES[$campo] ?? null;
                        if (is_array($arquivo) && (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $upload = ProducaoImagens::upload($arquivo, $prefixo, $pasta);
                            if (!$upload['ok']) {
                                $_SESSION['producao_flash'] = $upload['erro'];
                                header('Location: ' . $url . ($id > 0 ? '&acao=editar&id=' . $id : '&acao=novo'), true, 303);
                                exit;
                            }
                            $post[$campo] = $upload['filename'];
                            $imagensSubstituidas[$campo] = ['antiga' => (string) ($atual[$campo] ?? ''), 'pasta' => $pasta];
                        } else {
                            unset($post[$campo]);
                        }
                    }
                }

                $resultado = ProducaoRegistros::salvar($recurso, $id > 0 ? $id : null, $post, $identidade);

                if (!empty($resultado['ok'])) {
                    foreach ($imagensSubstituidas as $substituida) {
                        if ($substituida['antiga'] !== '') {
                            ProducaoImagens::remover($substituida['antiga'], $substituida['pasta']);
                        }
                    }
                }
            }

            $_SESSION['producao_flash'] = $resultado['ok']
                ? 'Registro salvo com sucesso.'
                : (string) ($resultado['erro'] ?? 'Não foi possível salvar o registro.');
            header('Location: ' . $url, true, 303);
            exit;
        }

        $busca = trim((string) ($_GET['q'] ?? ''));

        if (isset($_GET['exportar']) && (string) $_GET['exportar'] === 'csv') {
            $linhas = ProducaoRegistros::listar($recurso, $identidade, $busca);
            $rotulos = [];
            foreach ((array) ($def['relacoes'] ?? []) as $campo => $rel) {
                $rotulos[$campo] = ProducaoRegistros::opcoesRelacao((string) $rel['recurso'], $identidade);
            }
            UiProducao::csv($def, $linhas, $rotulos);
            exit;
        }

        $acao = (string) ($_GET['acao'] ?? '');
        if ($acao === 'novo' || $acao === 'editar') {
            if (!ProducaoRegistros::podeEscreverRecurso($def, $identidade)) {
                Ui::erro(403, 'Sem permissão', 'Seu perfil não pode alterar registros deste módulo de produção.');
                exit;
            }

            $registro = [];
            if ($acao === 'editar') {
                $id = (int) ($_GET['id'] ?? 0);
                $registro = ProducaoRegistros::obter($recurso, $id, $identidade);
                if ($registro === null) {
                    Ui::erro(404, 'Registro não encontrado', 'O registro não existe ou está fora do seu escopo.');
                    exit;
                }
            }

            UiProducao::formulario($slug, $app, $identidade, $recurso, $def, $registro, $erro);

            return;
        }

        UiProducao::lista(
            $slug,
            $app,
            $identidade,
            $recurso,
            $def,
            ProducaoRegistros::listar($recurso, $identidade, $busca),
            $busca,
            is_string($flash) ? $flash : null,
            $erro
        );
    }
}
