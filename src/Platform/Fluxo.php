<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Fluxo HTTP comum aos sistemas: login, primeiro acesso, ficha de adesão,
 * painel protegido e logout.
 *
 * O primeiro acesso segue a lógica do sistema atual:
 *  - credenciais válidas;
 *  - Fiscal/Técnico confirma vínculo institucional (órgão/especialidade/setor);
 *  - conta sem telefone/CPF completa os dados essenciais;
 *  - produtor sem cadastro de associado preenche a ficha de adesão.
 */
final class Fluxo
{
    private const MAX_TENTATIVAS = 5;
    private const JANELA_TENTATIVAS = 600; // 10 minutos
    private const LOGIN_TEMP_TTL = 900;    // 15 minutos para concluir o primeiro acesso

    public static function login(string $slug): void
    {
        Plataforma::iniciar($slug);
        $app = Plataforma::app();

        if (isset($_GET['cancelar'])) {
            unset($_SESSION['login_temp']);
        }

        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Tentativa de login com OUTRA conta enquanto já existe sessão: em vez
        // de redirecionar para o painel da conta antiga (causava "logar como o
        // usuário anterior"), processa as credenciais informadas e troca a conta.
        $tentativaCredenciais = $metodo === 'POST'
            && !in_array((string) ($_POST['etapa'] ?? ''), ['vinculo', 'perfil'], true)
            && (string) ($_POST['acao'] ?? '') !== 'pre_cadastro'
            && (isset($_POST['identificador']) || isset($_POST['senha']));

        $identidade = Plataforma::usuario();
        if ($identidade !== null) {
            if (!$tentativaCredenciais) {
                if (!Plataforma::podeAcessar($identidade)) {
                    Ui::negado($slug, $app, $identidade);
                    exit;
                }
                self::redirecionar(Plataforma::url('index.php'));
            }

            // A sessão anterior é descartada ANTES de validar CSRF e
            // credenciais. Assim, um POST de login com token expirado, senha
            // errada ou área incorreta nunca mantém a conta antiga autenticada.
            Sessao::limparIdentidade();
            $identidade = null;

            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                UiLogin::login($slug, $app, [
                    'erro' => 'Sessão expirada. Recarregue a página e tente novamente.',
                    'tab' => 'login',
                    'identificador' => self::texto($_POST['identificador'] ?? ''),
                ]);
                return;
            }
        }

        if ($metodo === 'POST' && in_array((string) ($_POST['etapa'] ?? ''), ['vinculo', 'perfil'], true)) {
            self::concluirEtapa($slug, $app);
            return;
        }

        if ($metodo === 'POST' && (string) ($_POST['acao'] ?? '') === 'pre_cadastro') {
            $resultado = Cadastro::processar($_POST);

            if ($resultado['ok']) {
                // Mesmo estado do sistema atual: tela "Cadastro enviado!".
                UiLogin::login($slug, $app, [
                    'tab' => 'sucesso',
                    'sucesso' => $resultado['mensagem'],
                ]);
                return;
            }

            UiLogin::login($slug, $app, [
                'erro' => $resultado['mensagem'],
                'tab' => $resultado['tab'],
                'nome' => self::texto($_POST['nome'] ?? ''),
                'sobrenome' => self::texto($_POST['sobrenome'] ?? ''),
                'email' => self::texto($_POST['email'] ?? ''),
                'perfil' => self::texto($_POST['perfil'] ?? ''),
            ]);
            return;
        }

        $estado = [
            'erro' => null,
            'sucesso' => null,
            'tab' => 'login',
            'identificador' => '',
        ];

        if ($metodo === 'POST') {
            $identificador = self::texto($_POST['identificador'] ?? '');
            $senha = self::texto($_POST['senha'] ?? '');
            $estado['identificador'] = $identificador;

            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $estado['erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } elseif (self::bloqueadoPorTentativas()) {
                $estado['erro'] = 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.';
            } else {
                $identidade = Identidades::porCredenciais($identificador, $senha);

                if ($identidade === null) {
                    self::registrarTentativa();
                    self::auditar('LOGIN_FALHOU', null, 'App: ' . $slug);
                    $estado['erro'] = 'E-mail/CPF ou senha inválidos.';
                } elseif (($identidade['status'] ?? 'ativo') === 'pendente') {
                    // Pré-cadastro ainda não liberado pela diretoria.
                    self::auditar('LOGIN_PENDENTE', $identidade, 'App: ' . $slug);
                    UiLogin::aguardando($slug, $app, $identidade);
                    exit;
                } elseif (($identidade['status'] ?? 'ativo') !== 'ativo') {
                    self::auditar('LOGIN_INATIVO', $identidade, 'App: ' . $slug);
                    $estado['erro'] = 'Sua conta está inativa. Procure a diretoria da associação.';
                } elseif (!self::podeIniciarAcesso($slug, $identidade)) {
                    // Credenciais válidas, mas o perfil não pertence a ESTA área:
                    // não cria sessão; volta para a landing com aviso.
                    self::auditar('LOGIN_AREA_ERRADA', $identidade, 'Tentativa de login na área ' . $slug);
                    self::redirecionar(self::landingUrl() . '?acesso=negado&area=' . rawurlencode($slug));
                } else {
                    self::aposAutenticar($slug, $app, $identidade);
                    return;
                }
            }
        }

        UiLogin::login($slug, $app, $estado);
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    private static function aposAutenticar(string $slug, array $app, array $identidade): void
    {
        $etapas = Onboarding::pendencias($identidade);

        if ($etapas === []) {
            if (!Plataforma::podeAcessar($identidade)) {
                self::auditar('ACESSO_NEGADO_APP', $identidade, 'App: ' . $slug);
                Ui::negado($slug, $app, $identidade);
                exit;
            }

            Sessao::entrar($identidade);
            self::auditar('LOGIN', $identidade, 'App: ' . $slug);
            self::redirecionar(Plataforma::url('index.php'));
        }

        $primeira = $etapas[0];

        if ($primeira === 'ficha') {
            if (!self::suportaFicha($slug)) {
                Ui::negado($slug, $app, $identidade);
                exit;
            }

            Sessao::entrar($identidade);
            self::auditar('LOGIN', $identidade, 'App: ' . $slug . ' (primeiro acesso: ficha no dashboard)');
            self::redirecionar(Plataforma::url('index.php'));
        }

        $_SESSION['login_temp'] = [
            'identidade' => $identidade,
            'ts' => time(),
        ];

        self::mostrarEtapa($slug, $app, $identidade, $primeira, null);
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $identidade
     */
    private static function mostrarEtapa(string $slug, array $app, array $identidade, string $etapa, ?string $erro): void
    {
        if ($etapa === 'vinculo') {
            UiLogin::vinculo($slug, $app, $identidade, $erro);
            return;
        }

        UiLogin::perfil($slug, $app, $identidade, $erro);
    }

    /** @param array<string, mixed> $app */
    private static function concluirEtapa(string $slug, array $app): void
    {
        $temp = $_SESSION['login_temp'] ?? null;
        if (!is_array($temp) || !isset($temp['identidade']) || !is_array($temp['identidade'])) {
            self::redirecionar(Plataforma::url('login.php'));
        }

        if (time() - (int) ($temp['ts'] ?? 0) > self::LOGIN_TEMP_TTL) {
            unset($_SESSION['login_temp']);
            self::redirecionar(Plataforma::url('login.php'));
        }

        /** @var array<string, mixed> $identidade */
        $identidade = $temp['identidade'];
        $etapa = (string) ($_POST['etapa'] ?? '');

        if (!Csrf::validar($_POST['csrf'] ?? null)) {
            self::mostrarEtapa($slug, $app, $identidade, $etapa, 'Sessão expirada. Recarregue a página e tente novamente.');
            return;
        }

        $usuarioId = (int) ($identidade['id'] ?? 0);
        $resultado = $etapa === 'vinculo'
            ? Onboarding::salvarVinculo($usuarioId, $_POST)
            : Onboarding::salvarPerfil($usuarioId, $_POST);

        if (!$resultado['ok']) {
            self::mostrarEtapa($slug, $app, $identidade, $etapa, $resultado['erro']);
            return;
        }

        $atualizada = Identidades::porUsuarioId($usuarioId) ?? $identidade;
        unset($_SESSION['login_temp']);

        self::aposAutenticar($slug, $app, $atualizada);
    }

    public static function home(string $slug): void
    {
        Plataforma::iniciar($slug);

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            self::redirecionar(Plataforma::url('login.php'));
        }

        // Envio da ficha de adesão pelo modal do dashboard.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['acao'] ?? '') === 'ficha' && self::suportaFicha($slug)) {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $_SESSION['ficha_modal_erro'] = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $rascunho = ($_POST['modo'] ?? 'submeter') === 'rascunho';
                $resultado = Onboarding::salvarFicha((int) $identidade['id'], $_POST, !$rascunho);
                if ($resultado['success']) {
                    self::auditar($rascunho ? 'FICHA_RASCUNHO' : 'FICHA_SUBMETIDA', $identidade, 'App: ' . $slug);
                    $_SESSION['ficha_modal_sucesso'] = $rascunho
                        ? 'Rascunho salvo. Você pode continuar depois.'
                        : 'Ficha enviada com sucesso! Agora ela aguarda a análise da Diretoria/Presidência.';
                } else {
                    $_SESSION['ficha_modal_erro'] = $resultado['message'];
                }
                // Reabre o modal após o envio para mostrar o rascunho salvo ou
                // o estado "em análise" — sem voltar a abri-lo em navegações
                // normais dentro da mesma sessão.
                $_SESSION['mostrar_ficha_modal'] = true;
            }
            self::redirecionar(Plataforma::url('index.php'));
        }

        $precisaFicha = Onboarding::precisaFicha($identidade) && self::suportaFicha($slug);

        if (!Plataforma::podeAcessar($identidade) && !$precisaFicha) {
            Ui::negado($slug, Plataforma::app(), $identidade);
            exit;
        }

        // O "home" do Controle Social é o painel do gestor/técnico, com os
        // mesmos KPIs do /dashboard/gestor legado.
        if ($slug === 'social' && !isset($_GET['funcao']) && Autorizacao::pode($identidade, 'fiscalizacao.read')) {
            FluxoSocialModulos::painel($slug);
            return;
        }

        // Painéis de entrada de associação e produção.
        if (in_array($slug, ['assoc', 'producao'], true) && !isset($_GET['funcao'])) {
            if ($slug === 'assoc') {
                FluxoPainel::assoc($slug);
            } else {
                FluxoPainel::producao($slug);
            }
            return;
        }

        // Funções que já têm tela própria redirecionam para ela (ex.:
        // index.php?funcao=certificadoras.listar → certificadoras.php).
        $chavePedida = trim((string) ($_GET['funcao'] ?? ''));
        if ($chavePedida !== '') {
            foreach (Autorizacao::funcoesVisiveis($identidade, $slug) as $funcaoItem) {
                if ((string) ($funcaoItem['chave'] ?? '') === $chavePedida && !empty($funcaoItem['rota'])) {
                    self::redirecionar(Urls::app($slug, (string) $funcaoItem['rota']));
                }
            }
        }

        Ui::painel($slug, Plataforma::app(), $identidade);
    }

    /**
     * Agendar visita técnica — item "Agendar Visita" do menu de fiscalização.
     * Mesmos campos e efeito da tela atual (`visitas/agendar.php`).
     */
    public static function agendarVisita(string $slug): void
    {
        Plataforma::iniciar($slug);
        $app = Plataforma::app();

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            self::redirecionar(Plataforma::url('login.php'));
        }

        if (!Plataforma::podeAcessar($identidade) || !Autorizacao::pode($identidade, 'fiscalizacao.write')) {
            Ui::negado($slug, $app, $identidade);
            exit;
        }

        $pdo = Identidades::pdo();
        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar((string) ($_POST['csrf'] ?? ''))) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $associadoId = (int) ($_POST['associado_id'] ?? 0);
                $propriedadeId = (int) ($_POST['propriedade_id'] ?? 0);
                $roteiroId = (int) ($_POST['roteiro_id'] ?? 0);
                $data = self::texto($_POST['data_visita'] ?? '');
                $hora = self::texto($_POST['hora_inicio'] ?? '');
                $tecnicoId = (int) ($_POST['tecnico_responsavel_id'] ?? 0);
                $acompanhantes = self::texto($_POST['acompanhantes'] ?? '');
                $observacoes = self::texto($_POST['observacoes'] ?? '');

                if ($associadoId <= 0 || $propriedadeId <= 0 || $roteiroId <= 0 || $data === '') {
                    $erro = 'Informe o associado, a propriedade, o roteiro e a data da visita.';
                } elseif (strtotime($data) === false || $data < date('Y-m-d')) {
                    $erro = 'A data da visita deve ser hoje ou uma data futura.';
                } else {
                    try {
                        $stmtAssoc = $pdo->prepare('SELECT id FROM associados WHERE id = :id AND excluido_em IS NULL');
                        $stmtAssoc->execute(['id' => $associadoId]);
                        $stmtRoteiro = $pdo->prepare('SELECT id FROM roteiros_inspecao WHERE id = :id');
                        $stmtRoteiro->execute(['id' => $roteiroId]);
                        $stmtProp = $pdo->prepare('SELECT associado_id FROM propriedades WHERE id = :id');
                        $stmtProp->execute(['id' => $propriedadeId]);
                        $propriedade = $stmtProp->fetch();

                        if ($stmtAssoc->fetch() === false || $stmtRoteiro->fetch() === false) {
                            $erro = 'Associado ou roteiro inválido.';
                        } elseif ($propriedade === false || (int) $propriedade['associado_id'] !== $associadoId) {
                            $erro = 'A propriedade selecionada não pertence a este associado.';
                        } else {
                            $tecnico = $tecnicoId > 0
                                ? $tecnicoId
                                : (($identidade['origem'] ?? '') === 'associados'
                                    ? (int) $identidade['id']
                                    : (int) ($identidade['associado_id'] ?? 0));
                            if ($tecnico <= 0) {
                                $tecnico = null;
                            }

                            $hashid = bin2hex(random_bytes(8));
                            $stmt = $pdo->prepare(
                                "INSERT INTO visitas_tecnicas
                                    (hashid, associado_id, propriedade_id, roteiro_id, data_visita, hora_inicio,
                                     tecnico_responsavel_id, acompanhantes, observacoes, status, created_at, updated_at)
                                 VALUES
                                    (:hashid, :associado_id, :propriedade_id, :roteiro_id, :data_visita, :hora_inicio,
                                     :tecnico_responsavel_id, :acompanhantes, :observacoes, 'Agendada', NOW(), NOW())"
                            );
                            $stmt->execute([
                                'hashid' => $hashid,
                                'associado_id' => $associadoId,
                                'propriedade_id' => $propriedadeId,
                                'roteiro_id' => $roteiroId,
                                'data_visita' => $data,
                                'hora_inicio' => $hora !== '' ? $hora : null,
                                'tecnico_responsavel_id' => $tecnico,
                                'acompanhantes' => $acompanhantes !== '' ? $acompanhantes : null,
                                'observacoes' => $observacoes !== '' ? $observacoes : null,
                            ]);

                            $visitaId = (int) $pdo->lastInsertId();
                            self::auditar('VISITA_AGENDADA', $identidade, 'Visita #' . $visitaId . ' agendada para ' . $data);
                            self::redirecionar(Plataforma::url('agendar-visita.php?ok=1'));
                        }
                    } catch (\Throwable $e) {
                        error_log('SINPORG agendarVisita - ' . $e->getMessage());
                        $erro = 'Não foi possível agendar a visita. Verifique os dados e tente novamente.';
                    }
                }
            }
        }

        try {
            $associados = $pdo->query(
                "SELECT id, CONCAT(nome, ' ', sobrenome) AS nome FROM associados
                  WHERE excluido_em IS NULL AND status = 'ativo' ORDER BY nome"
            )->fetchAll();
            $propriedades = $pdo->query(
                'SELECT id, associado_id, propriedade FROM propriedades ORDER BY propriedade'
            )->fetchAll();
            $roteiros = $pdo->query(
                'SELECT id, nome FROM roteiros_inspecao WHERE ativo = 1 ORDER BY nome'
            )->fetchAll();
            $tecnicos = $pdo->query(
                "SELECT id, CONCAT(nome, ' ', sobrenome) AS nome FROM associados
                  WHERE excluido_em IS NULL AND (tipo = 'gestor' OR nivel = 2)
                  ORDER BY nome"
            )->fetchAll();
            $recentes = $pdo->query(
                "SELECT v.data_visita, CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                        COALESCE(p.propriedade, '—') AS propriedade, v.status
                   FROM visitas_tecnicas v
                   LEFT JOIN associados a ON a.id = v.associado_id
                   LEFT JOIN propriedades p ON p.id = v.propriedade_id
                  ORDER BY v.data_visita DESC LIMIT 8"
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG agendarVisita listas - ' . $e->getMessage());
            $associados = $propriedades = $roteiros = $tecnicos = $recentes = [];
            $erro = $erro ?? 'Não foi possível carregar os dados do formulário.';
        }

        UiModulo::agendarVisita(
            $slug,
            $app,
            $identidade,
            $associados,
            $propriedades,
            $roteiros,
            $tecnicos,
            $recentes,
            $erro,
            isset($_GET['ok'])
        );
    }

    public static function ficha(string $slug): void
    {
        Plataforma::iniciar($slug);

        if (!self::suportaFicha($slug)) {
            self::redirecionar(Plataforma::url('index.php'));
        }

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            self::redirecionar(Plataforma::url('login.php'));
        }

        if (!Onboarding::precisaFicha($identidade)) {
            self::redirecionar(Plataforma::url('index.php'));
        }

        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $rascunho = ($_POST['modo'] ?? 'submeter') === 'rascunho';
                $resultado = Onboarding::salvarFicha((int) $identidade['id'], $_POST, !$rascunho);

                if ($resultado['success']) {
                    self::auditar($rascunho ? 'FICHA_RASCUNHO' : 'FICHA_SUBMETIDA', $identidade, 'App: ' . $slug);
                    self::redirecionar(Plataforma::url('ficha.php') . ($rascunho ? '?rascunho=1' : '?enviada=1'));
                }

                $erro = $resultado['message'];
            }
        }

        $status = Onboarding::statusFicha((int) $identidade['id']);

        UiLogin::ficha(
            $slug,
            Plataforma::app(),
            $identidade,
            $status,
            $erro,
            isset($_GET['enviada'])
        );
    }

    /**
     * Tela da diretoria: liberar contas do pré-cadastro e aprovar fichas de adesão.
     */
    public static function pendencias(string $slug): void
    {
        Plataforma::iniciar($slug);
        $app = Plataforma::app();

        $identidade = Plataforma::usuario();
        if ($identidade === null) {
            self::redirecionar(Plataforma::url('login.php'));
        }

        if (!Autorizacao::pode($identidade, 'associados.approve')) {
            Ui::erro(403, 'Sem permissão', 'Seu perfil não pode liberar acessos nem aprovar fichas.');
            exit;
        }

        $flash = null;
        if (isset($_SESSION['pendencias_flash']) && is_array($_SESSION['pendencias_flash'])) {
            $flash = $_SESSION['pendencias_flash'];
            unset($_SESSION['pendencias_flash']);
        }

        $erro = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::validar($_POST['csrf'] ?? null)) {
                $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $tipo = self::texto($_POST['tipo'] ?? '');
                $acao = self::texto($_POST['acao'] ?? '');
                $alvo = (int) ($_POST['id'] ?? 0);
                $resultado = null;

                if ($alvo <= 0) {
                    $erro = 'Registro não informado.';
                } elseif ($tipo === 'acesso' && in_array($acao, ['liberar', 'negar'], true)) {
                    if ($acao === 'liberar') {
                        $nivel = (int) ($_POST['nivel_concedido'] ?? 0);
                        if (!in_array($nivel, [1, 2, 3], true)) {
                            $erro = 'Selecione um nível válido para liberar o acesso.';
                        } elseif ($nivel === 1 && !Autorizacao::ehAdmin($identidade)) {
                            $erro = 'Apenas o administrador pode conceder o nível Admin.';
                        } else {
                            $resultado = Pendencias::liberarConta($alvo, $nivel);
                        }
                    } else {
                        $resultado = Pendencias::negarConta($alvo, self::texto($_POST['motivo'] ?? ''));
                    }
                } elseif ($tipo === 'ficha' && in_array($acao, ['aprovar', 'recusar'], true)) {
                    $analisadoPor = (int) ($identidade['id'] ?? 0);
                    $confirmouCompleta = ($_POST['ficha_completa'] ?? '') === '1';
                    $resultado = $acao === 'aprovar'
                        ? Pendencias::aprovarFicha($alvo, $analisadoPor, $confirmouCompleta)
                        : Pendencias::recusarFicha($alvo, $analisadoPor, self::texto($_POST['motivo'] ?? ''));
                } else {
                    $erro = 'Ação não reconhecida.';
                }

                if ($resultado !== null) {
                    $_SESSION['pendencias_flash'] = [
                        'tipo' => $resultado['success'] ? 'ok' : 'erro',
                        'texto' => $resultado['message'],
                    ];
                    self::redirecionar(Plataforma::url('pendencias.php'));
                }
            }
        }

        UiPendencias::render(
            $slug,
            $app,
            $identidade,
            Pendencias::contas(),
            Pendencias::fichas(),
            $flash,
            $erro
        );
    }

    public static function logout(string $slug): void
    {
        Plataforma::iniciar($slug);

        $identidade = Plataforma::usuario();
        if ($identidade !== null) {
            self::auditar('LOGOUT', $identidade, 'App: ' . $slug);
        }

        unset($_SESSION['login_temp']);
        Sessao::sair();
        self::redirecionar(Plataforma::url('login.php'));
    }

    private static function suportaFicha(string $slug): bool
    {
        return in_array($slug, ['assoc', 'producao'], true);
    }

    /**
     * O perfil pode começar o acesso neste sistema?
     *
     * Produtor que ainda precisa da ficha entra mesmo sem permissões (a ficha
     * é o próprio primeiro acesso). Qualquer outro perfil precisa já ter
     * acesso — é o que impede o fiscal/técnico de entrar na área associativa.
     *
     * @param array<string, mixed> $identidade
     */
    private static function podeIniciarAcesso(string $slug, array $identidade): bool
    {
        if (Onboarding::precisaFicha($identidade) && self::suportaFicha($slug)) {
            return true;
        }

        return Plataforma::podeAcessar($identidade);
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) ? trim($valor) : '';
    }

    private static function redirecionar(string $url): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
        }

        exit;
    }

    /** URL da landing page (para onde voltam os perfis que tentam a área errada). */
    private static function landingUrl(): string
    {
        return Urls::base() . '/site';
    }

    /** @param array<string, mixed>|null $identidade */
    private static function auditar(string $acao, ?array $identidade, string $descricao): void
    {
        Auditoria::registrar(
            $acao,
            is_array($identidade) ? (int) ($identidade['id'] ?? 0) : null,
            is_array($identidade) ? (string) ($identidade['origem'] ?? '') : null,
            null,
            $descricao
        );
    }

    private static function bloqueadoPorTentativas(): bool
    {
        $tentativas = $_SESSION['login_tentativas'] ?? [];
        if (!is_array($tentativas)) {
            return false;
        }

        $limite = time() - self::JANELA_TENTATIVAS;
        $recentes = array_values(array_filter($tentativas, static fn($t): bool => is_int($t) && $t >= $limite));

        return count($recentes) >= self::MAX_TENTATIVAS;
    }

    private static function registrarTentativa(): void
    {
        $tentativas = $_SESSION['login_tentativas'] ?? [];
        $tentativas = is_array($tentativas) ? $tentativas : [];

        $limite = time() - self::JANELA_TENTATIVAS;
        $tentativas = array_values(array_filter($tentativas, static fn($t): bool => is_int($t) && $t >= $limite));
        $tentativas[] = time();

        $_SESSION['login_tentativas'] = array_slice($tentativas, -self::MAX_TENTATIVAS * 2);
    }
}
