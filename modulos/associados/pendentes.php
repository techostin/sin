<?php
$page_title = "Usuários Pendentes de Ativação";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

// Apenas admin
if (!$user->hasPermission(NIVEL_ADMIN)) {
    echo "<div class='alert alert-danger m-4'>Acesso negado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Confirmação de privilégios dos pedidos de acesso (Gestor/Fiscal etc.)
require_once __DIR__ . '/../../classes/AcessoPendente.php';
$acessoPendente = new AcessoPendente();

// Processar ativação/rejeição via POST
$msg = '';
$msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['id'])) {
    // Ação de escrita: exige token CSRF (antes um POST sem token ativava contas)
    if (!CSRFProtection::validatePostRequest()) {
        $msg      = 'Erro de segurança: token CSRF inválido. Recarregue a página e tente novamente.';
        $msg_type = 'danger';
    } else {
    $id     = (int)$_POST['id'];
    $acao   = $_POST['acao'];
    $origem = (($_POST['origem'] ?? 'associado') === 'usuario') ? 'usuario' : 'associado';
    if ($id > 0 && $origem === 'usuario') {
        // ---------------------------------------------------------------------
        // CONFIRMAÇÃO DE PRIVILÉGIOS (classes/AcessoPendente.php)
        // O Admin confere o que foi solicitado (ex.: Gestor/Fiscal) e decide o
        // nível efetivamente concedido — confirmando, rebaixando ou elevando.
        // ---------------------------------------------------------------------
        if ($acao === 'confirmar_acesso' || $acao === 'ativar') {
            // "ativar" (compatibilidade) concede exatamente o nível solicitado
            $nivelConcedido = $acao === 'ativar'
                ? AcessoPendente::nivelSolicitado($acessoPendente->solicitacao($id) ?: [])
                : (int) ($_POST['nivel_concedido'] ?? 0);

            $res = $acessoPendente->confirmarPrivilegios($id, $nivelConcedido, [
                'orgao'         => (string) ($_POST['orgao'] ?? ''),
                'setor'         => (string) ($_POST['setor'] ?? ''),
                'especialidade' => (string) ($_POST['especialidade'] ?? ''),
            ], $user);
            $msg      = $res['message'];
            $msg_type = $res['success'] ? 'success' : 'danger';
        } elseif ($acao === 'negar_acesso' || $acao === 'inativar') {
            $motivo = (string) ($_POST['motivo'] ?? '');
            if ($acao === 'inativar' && $motivo === '') {
                $motivo = 'Solicitação recusada na análise de acesso.';
            }
            $res = $acessoPendente->negarSolicitacao($id, $motivo, $user);
            $msg      = $res['message'];
            $msg_type = $res['success'] ? 'warning' : 'danger';
        } else {
            $msg      = 'Ação não reconhecida para esta solicitação.';
            $msg_type = 'danger';
        }
    } elseif ($id > 0) {
        // Cadastros da tabela associados (fluxo anterior): mantém ativar/inativar
        $novoStatus = match($acao) {
            'ativar'   => 'ativo',
            'inativar' => 'inativo',
            default    => null,
        };
        if ($novoStatus) {
            try {
                $db   = Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE associados SET status = :s WHERE id = :id");
                $stmt->execute([':s' => $novoStatus, ':id' => $id]);
                $msg      = $novoStatus === 'ativo' ? 'Cadastro ativado com sucesso!' : 'Cadastro marcado como inativo.';
                $msg_type = $novoStatus === 'ativo' ? 'success' : 'warning';
            } catch (Exception $e) {
                $msg      = 'Erro ao atualizar status.';
                $msg_type = 'danger';
            }
        }
    }
    }
}

// Buscar pendentes
$pendentes = [];
try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, nome, sobrenome, email, telefone, tipo_usuario, data_cadastro, 'associado' AS origem,
               NULL AS nivel, NULL AS nivel_solicitado, NULL AS perfil_solicitado,
               NULL AS orgao, NULL AS setor, NULL AS especialidade
        FROM associados
        WHERE status = 'pendente'
        UNION ALL
        SELECT id, nome COLLATE utf8mb4_general_ci, sobrenome COLLATE utf8mb4_general_ci,
               email COLLATE utf8mb4_general_ci, telefone COLLATE utf8mb4_general_ci,
               tipo_usuario COLLATE utf8mb4_general_ci, created_at AS data_cadastro, 'usuario' AS origem,
               nivel, nivel_solicitado, perfil_solicitado COLLATE utf8mb4_general_ci,
               orgao COLLATE utf8mb4_general_ci, setor COLLATE utf8mb4_general_ci, especialidade COLLATE utf8mb4_general_ci
        FROM usuarios
        WHERE status = 'pendente'
        ORDER BY data_cadastro DESC
    ");
    $stmt->execute();
    $pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro listagem pendentes: " . $e->getMessage());
}
?>

<div class="assoc-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/associados-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<div class="assoc-hero">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="mb-1">
                <i class="bi bi-person-fill-exclamation me-2" style="color:var(--am-gold);"></i>Usuários Pendentes
            </h1>
            <p class="mb-0">
                Novos cadastros aguardando ativação.
                <?php if (count($pendentes) > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo count($pendentes); ?> pendente(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-assoc-ghost btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Ver Todos os Associados
        </a>
    </div>
</div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-1"></i>
            <?php echo htmlspecialchars($msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pendentes)): ?>
        <div class="assoc-card empty-state">
            <div class="card-body text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
                <h5 class="mt-3 mb-1">Nenhum cadastro pendente</h5>
                <p class="text-muted">Todos os usuários já foram analisados.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="assoc-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-responsive-stack" id="pendentesTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Telefone</th>
                                <th>Perfil</th>
                                <th>Privilégio solicitado</th>
                                <th>Data do Cadastro</th>
                                <th class="text-center" style="min-width:320px;">Confirmação do Admin (privilégios)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendentes as $p): ?>
                            <tr>
                                <td data-label="#"><?php echo $p['id']; ?></td>
                                <td data-label="Nome">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center text-dark fw-bold"
                                             style="width:36px;height:36px;font-size:.85rem;flex-shrink:0;">
                                            <?php echo mb_strtoupper(mb_substr($p['nome'], 0, 1) . mb_substr($p['sobrenome'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']); ?></span>
                                    </div>
                                </td>
                                <td data-label="E-mail">
                                    <a href="mailto:<?php echo htmlspecialchars($p['email']); ?>">
                                        <?php echo htmlspecialchars($p['email']); ?>
                                    </a>
                                </td>
                                <td data-label="Telefone"><?php echo htmlspecialchars($p['telefone'] ?? '—'); ?></td>
                                <td data-label="Perfil">
                                    <?php
                                    $perfis = [
                                        'gestor'                   => ['Gestor', 'bg-secondary'],
                                        'agricultor'               => ['Agricultor', 'bg-info'],
                                        'produtor_agroindustria'   => ['Produtor de Agroindústria', 'bg-success'],
                                        'agricultor_agroindustria' => ['Agricultor com Agroindústria', 'bg-warning text-dark'],
                                    ];
                                    $perf = $perfis[$p['tipo_usuario']] ?? $perfis['agricultor'];
                                    ?>
                                    <span class="badge <?php echo $perf[1]; ?>"><?php echo $perf[0]; ?></span>
                                    <?php if ($p['origem'] === 'usuario'): ?>
                                    <span class="badge bg-dark ms-1" title="Acesso interno (usuário do sistema)">Interno</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Privilégio solicitado">
                                    <?php if ($p['origem'] === 'usuario'):
                                        $nivelSolicitado = AcessoPendente::nivelSolicitado($p);
                                        $corNivel = $nivelSolicitado === NIVEL_GESTOR ? 'bg-warning text-dark' : ($nivelSolicitado === NIVEL_ADMIN ? 'bg-danger' : 'bg-info text-dark');
                                    ?>
                                        <span class="badge <?php echo $corNivel; ?>">
                                            <?php echo htmlspecialchars(AcessoPendente::rotulo($nivelSolicitado)); ?>
                                        </span>
                                        <?php if (!empty($p['perfil_solicitado'])): ?>
                                        <div class="small text-muted">perfil: <?php echo htmlspecialchars((string) $p['perfil_solicitado']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">cadastro de associado</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Cadastro">
                                    <?php echo date('d/m/Y H:i', strtotime($p['data_cadastro'])); ?>
                                </td>
                                <td data-label="Confirmação do Admin (privilégios)">
                                    <?php if ($p['origem'] === 'usuario'):
                                        $nivelSolicitado = AcessoPendente::nivelSolicitado($p);
                                    ?>
                                    <form method="POST" class="d-flex flex-column gap-2"
                                          onsubmit="return confirm('Confirmar os privilégios selecionados e liberar o acesso deste usuário?')">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <input type="hidden" name="id"     value="<?php echo (int) $p['id']; ?>">
                                        <input type="hidden" name="origem" value="usuario">
                                        <input type="hidden" name="acao"   value="confirmar_acesso">

                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <label class="small text-muted mb-0" for="nivel_<?php echo (int) $p['id']; ?>">Conceder:</label>
                                            <select name="nivel_concedido" id="nivel_<?php echo (int) $p['id']; ?>"
                                                    class="form-select form-select-sm" style="width:auto;">
                                                <option value="<?php echo NIVEL_ADMIN; ?>" <?php echo $nivelSolicitado === NIVEL_ADMIN ? 'selected' : ''; ?>>Admin</option>
                                                <option value="<?php echo NIVEL_GESTOR; ?>" <?php echo $nivelSolicitado === NIVEL_GESTOR ? 'selected' : ''; ?>>Gestor/Fiscal</option>
                                                <option value="<?php echo NIVEL_USUARIO; ?>" <?php echo $nivelSolicitado === NIVEL_USUARIO ? 'selected' : ''; ?>>Usuário</option>
                                            </select>
                                            <button class="btn btn-success btn-sm" title="Confirmar privilégios e liberar o acesso">
                                                <i class="bi bi-shield-check"></i> Confirmar acesso
                                            </button>
                                        </div>

                                        <?php if ($nivelSolicitado === NIVEL_GESTOR): ?>
                                        <div class="row g-1">
                                            <div class="col-4">
                                                <input type="text" name="orgao" class="form-control form-control-sm"
                                                       placeholder="Órgão" maxlength="100"
                                                       value="<?php echo htmlspecialchars((string) ($p['orgao'] ?? '')); ?>">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" name="setor" class="form-control form-control-sm"
                                                       placeholder="Setor" maxlength="100"
                                                       value="<?php echo htmlspecialchars((string) ($p['setor'] ?? '')); ?>">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" name="especialidade" class="form-control form-control-sm"
                                                       placeholder="Especialidade/Cargo" maxlength="100"
                                                       value="<?php echo htmlspecialchars((string) ($p['especialidade'] ?? '')); ?>">
                                            </div>
                                        </div>
                                        <div class="small text-muted">
                                            Vínculo profissional é usado no perfil de Gestor/Fiscal
                                            (pode ser completado depois em <em>Meu Perfil</em>).
                                        </div>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" class="d-flex gap-1 mt-1"
                                          onsubmit="return confirm('Recusar esta solicitação de acesso?')">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <input type="hidden" name="id"     value="<?php echo (int) $p['id']; ?>">
                                        <input type="hidden" name="origem" value="usuario">
                                        <input type="hidden" name="acao"   value="negar_acesso">
                                        <input type="text" name="motivo" class="form-control form-control-sm"
                                               placeholder="Motivo da recusa" maxlength="255" required>
                                        <button class="btn btn-outline-danger btn-sm" title="Recusar a solicitação">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Ativar este cadastro?')">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <input type="hidden" name="id"   value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="origem" value="<?php echo $p['origem']; ?>">
                                        <input type="hidden" name="acao" value="ativar">
                                        <button class="btn btn-success btn-sm" title="Ativar">
                                            <i class="bi bi-check-lg"></i> Ativar
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Rejeitar este cadastro?')">
                                        <?php echo CSRFProtection::getTokenField(); ?>
                                        <input type="hidden" name="id"   value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="origem" value="<?php echo $p['origem']; ?>">
                                        <input type="hidden" name="acao" value="inativar">
                                        <button class="btn btn-outline-danger btn-sm" title="Rejeitar">
                                            <i class="bi bi-x-lg"></i> Rejeitar
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div> <!-- /page-bg -->
</div> <!-- /assoc-modern -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
