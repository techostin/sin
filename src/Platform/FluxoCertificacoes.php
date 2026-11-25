<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Módulo de Certificações (SPG/OCS) do sistema social.
 *
 * Porta a tela atual `/certificacoes` e suas rotas internas:
 *  - filtros da listagem (tipo, status, validade, busca);
 *  - nova certificação, edição, renovação e exclusão;
 *  - ver detalhes, histórico do associado e certificado para impressão.
 */
final class FluxoCertificacoes
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
        $modo = $acao;
        $erro = null;
        $registro = [];

        $flash = $_SESSION['certificacoes_flash'] ?? null;
        $flashErro = !empty($_SESSION['certificacoes_flash_erro']);
        unset($_SESSION['certificacoes_flash'], $_SESSION['certificacoes_flash_erro']);
        if ($flashErro && is_string($flash)) {
            $erro = $flash;
            $flash = null;
        }

        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($metodo === 'POST') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } elseif (!$podeGerenciar) {
                Ui::negado($slug, $app, $identidade);
                exit;
            } else {
                $acaoPost = trim((string) ($_POST['acao'] ?? ''));

                if ($acaoPost === 'excluir') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $existente = $id > 0 ? Certificacoes::porId($id) : null;

                    if ($existente === null) {
                        $erro = 'Certificação não encontrada.';
                    } else {
                        Certificacoes::excluir($id);
                        Auditoria::registrar(
                            'CERTIFICACAO_EXCLUIDA',
                            (int) ($identidade['id'] ?? 0),
                            (string) ($identidade['origem'] ?? ''),
                            $id,
                            'Certificação excluída: ' . (string) ($existente['numero_certificado'] ?? ''),
                            'social'
                        );
                        self::flash('Certificação excluída com sucesso.');
                        self::redirecionar(Urls::app($slug, 'certificacoes.php'));
                    }
                } elseif ($acaoPost === 'salvar') {
                    $modoPost = trim((string) ($_POST['modo'] ?? 'nova'));
                    $id = (int) ($_POST['id'] ?? 0);
                    $renovacao = $modoPost === 'renovar';
                    $validado = Certificacoes::validar($_POST, $renovacao);

                    if (!$validado['ok']) {
                        $erro = $validado['erro'];
                        $acao = $renovacao ? 'renovar' : ($id > 0 ? 'editar' : 'nova');
                        $modo = $acao;
                        $registro = $validado['dados'];
                        if ($id > 0) {
                            $registro['id'] = $id;
                        }
                    } elseif ($renovacao) {
                        $existente = $id > 0 ? Certificacoes::porId($id) : null;
                        if ($existente === null) {
                            $erro = 'Certificação não encontrada para renovação.';
                        } else {
                            Certificacoes::renovar($id, $validado['dados']);
                            Auditoria::registrar(
                                'CERTIFICACAO_RENOVADA',
                                (int) ($identidade['id'] ?? 0),
                                (string) ($identidade['origem'] ?? ''),
                                $id,
                                'Certificação renovada até ' . $validado['dados']['data_validade'],
                                'social'
                            );
                            self::flash('Certificação renovada com sucesso.');
                            self::redirecionar(Urls::app($slug, 'certificacoes.php?acao=detalhes&id=' . $id));
                        }
                    } elseif ($id > 0) {
                        if (Certificacoes::porId($id) === null) {
                            $erro = 'Certificação não encontrada para edição.';
                        } else {
                            Certificacoes::atualizar($id, $validado['dados']);
                            Auditoria::registrar(
                                'CERTIFICACAO_ATUALIZADA',
                                (int) ($identidade['id'] ?? 0),
                                (string) ($identidade['origem'] ?? ''),
                                $id,
                                'Certificação atualizada: ' . (string) $validado['dados']['numero_certificado'],
                                'social'
                            );
                            self::flash('Certificação atualizada com sucesso.');
                            self::redirecionar(Urls::app($slug, 'certificacoes.php?acao=detalhes&id=' . $id));
                        }
                    } else {
                        $novoId = Certificacoes::criar($validado['dados']);
                        Auditoria::registrar(
                            'CERTIFICACAO_CRIADA',
                            (int) ($identidade['id'] ?? 0),
                            (string) ($identidade['origem'] ?? ''),
                            $novoId,
                            'Certificação criada: ' . (string) $validado['dados']['numero_certificado'],
                            'social'
                        );
                        self::flash('Certificação criada com sucesso.');
                        self::redirecionar(Urls::app($slug, 'certificacoes.php?acao=detalhes&id=' . $novoId));
                    }
                }
            }
        }

        switch ($acao) {
            case 'nova':
            case 'editar':
            case 'renovar':
                if (!$podeGerenciar) {
                    Ui::negado($slug, $app, $identidade);
                    exit;
                }

                if ($acao === 'nova') {
                    $registro = $registro !== [] ? $registro : [
                        'associado_id' => (int) ($_GET['associado_id'] ?? 0),
                        'tipo_certificacao' => 'SPG',
                        'status' => 'Ativa',
                        'data_emissao' => date('Y-m-d'),
                        'data_validade' => date('Y-m-d', strtotime('+1 year')),
                    ];
                } else {
                    $id = (int) ($_GET['id'] ?? ($registro['id'] ?? 0));
                    if ($registro === [] || !isset($registro['id'])) {
                        $existente = $id > 0 ? Certificacoes::porId($id) : null;
                        if ($existente === null) {
                            self::flash('Certificação não encontrada.', true);
                            self::redirecionar(Urls::app($slug, 'certificacoes.php'));
                        }
                        $registro = $existente;
                    }
                    if ($acao === 'renovar') {
                        $registro['data_emissao'] = date('Y-m-d');
                        $registro['data_validade'] = date('Y-m-d', strtotime('+1 year'));
                    }
                }

                UiCertificacoes::formulario(
                    $slug,
                    $app,
                    $identidade,
                    $acao,
                    $registro,
                    Certificacoes::associados(),
                    Certificacoes::certificadoras(),
                    $erro,
                    $podeGerenciar
                );
                break;

            case 'detalhes':
                $id = (int) ($_GET['id'] ?? 0);
                $existente = $id > 0 ? Certificacoes::porId($id) : null;
                if ($existente === null) {
                    self::flash('Certificação não encontrada.', true);
                    self::redirecionar(Urls::app($slug, 'certificacoes.php'));
                }
                UiCertificacoes::detalhes($slug, $app, $identidade, $existente, $podeGerenciar);
                break;

            case 'historico':
                $associadoId = (int) ($_GET['associado_id'] ?? 0);
                $stmt = Identidades::pdo()->prepare(
                    'SELECT id, nome, sobrenome FROM associados WHERE id = :id LIMIT 1'
                );
                $stmt->execute(['id' => $associadoId]);
                $associado = $stmt->fetch();
                if ($associado === false) {
                    self::flash('Associado não encontrado.', true);
                    self::redirecionar(Urls::app($slug, 'certificacoes.php'));
                }
                UiCertificacoes::historico(
                    $slug,
                    $app,
                    $identidade,
                    $associado,
                    Certificacoes::historico($associadoId),
                    $podeGerenciar
                );
                break;

            case 'certificado':
                $id = (int) ($_GET['id'] ?? 0);
                $existente = $id > 0 ? Certificacoes::porId($id) : null;
                if ($existente === null) {
                    self::flash('Certificação não encontrada.', true);
                    self::redirecionar(Urls::app($slug, 'certificacoes.php'));
                }
                UiCertificacoes::certificado(
                    $slug,
                    $app,
                    $identidade,
                    $existente,
                    Certificacoes::propriedades((int) $existente['associado_id'])
                );
                break;

            default:
                $filtros = Certificacoes::filtrosDaQuery();
                UiCertificacoes::listar(
                    $slug,
                    $app,
                    $identidade,
                    $filtros,
                    Certificacoes::listar($filtros),
                    Certificacoes::resumo($filtros),
                    is_string($flash) ? $flash : null,
                    $erro,
                    $podeGerenciar
                );
                break;
        }
    }

    private static function flash(string $mensagem, bool $erro = false): void
    {
        $_SESSION['certificacoes_flash'] = $mensagem;
        if ($erro) {
            $_SESSION['certificacoes_flash_erro'] = true;
        }
    }

    private static function redirecionar(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
