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

// Processar ativação/rejeição via POST
$msg = '';
$msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['id'])) {
    $id     = (int)$_POST['id'];
    $acao   = $_POST['acao'];
    $origem = (($_POST['origem'] ?? 'associado') === 'usuario') ? 'usuario' : 'associado';
    $novoStatus = match($acao) {
        'ativar'   => 'ativo',
        'inativar' => 'inativo',
        default    => null,
    };
    if ($novoStatus && $id > 0) {
        try {
            $db    = Database::getInstance()->getConnection();
            $tabela = $origem === 'usuario' ? 'usuarios' : 'associados';
            $stmt  = $db->prepare("UPDATE {$tabela} SET status = :s WHERE id = :id");
            $stmt->execute([':s' => $novoStatus, ':id' => $id]);
            $msg      = $novoStatus === 'ativo' ? 'Usuário ativado com sucesso!' : 'Usuário marcado como inativo.';
            $msg_type = $novoStatus === 'ativo' ? 'success' : 'warning';
        } catch (Exception $e) {
            $msg      = 'Erro ao atualizar status.';
            $msg_type = 'danger';
        }
    }
}

// Buscar pendentes
$pendentes = [];
try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, nome, sobrenome, email, telefone, tipo_usuario, data_cadastro, 'associado' AS origem
        FROM associados
        WHERE status = 'pendente'
        UNION ALL
        SELECT id, nome COLLATE utf8mb4_general_ci, sobrenome COLLATE utf8mb4_general_ci,
               email COLLATE utf8mb4_general_ci, NULL AS telefone,
               tipo_usuario COLLATE utf8mb4_general_ci, created_at AS data_cadastro, 'usuario' AS origem
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
                                <th>Data do Cadastro</th>
                                <th class="text-center" style="width:180px;">Ações</th>
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
                                <td data-label="Cadastro">
                                    <?php echo date('d/m/Y H:i', strtotime($p['data_cadastro'])); ?>
                                </td>
                                <td data-label="Ações" class="text-center">
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Ativar este usuário?')">
                                        <input type="hidden" name="id"   value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="origem" value="<?php echo $p['origem']; ?>">
                                        <input type="hidden" name="acao" value="ativar">
                                        <button class="btn btn-success btn-sm" title="Ativar">
                                            <i class="bi bi-check-lg"></i> Ativar
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Rejeitar este cadastro?')">
                                        <input type="hidden" name="id"   value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="origem" value="<?php echo $p['origem']; ?>">
                                        <input type="hidden" name="acao" value="inativar">
                                        <button class="btn btn-outline-danger btn-sm" title="Rejeitar">
                                            <i class="bi bi-x-lg"></i> Rejeitar
                                        </button>
                                    </form>
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
