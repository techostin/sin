<?php
$page_title = "Acompanhamento de Presenças";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->hasPermission(NIVEL_USUARIO)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT id, nome, data_evento, local, status, confirmados FROM eventos ORDER BY data_evento DESC");
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_associados = $db->prepare("SELECT id, nome, sobrenome, email, tipo FROM associados WHERE status = 'ativo' ORDER BY nome");
$stmt_associados->execute();
$todos_associados = $stmt_associados->fetchAll(PDO::FETCH_ASSOC);

$stmt_usuarios = $db->prepare("SELECT id, nome, email, tipo, associado_id FROM usuarios WHERE status = 'ativo' ORDER BY nome");
$stmt_usuarios->execute();
$todos_usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

$cpfs_cadastrados_como_associado = [];
$stmt_cpf = $db->prepare("SELECT cpf FROM associados WHERE cpf IS NOT NULL AND cpf != ''");
$stmt_cpf->execute();
foreach ($stmt_cpf->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cpfs_cadastrados_como_associado[] = $row['cpf'];
}
?>
<h1 class="mt-4">Acompanhamento de Presenças</h1>

<?php if (count($eventos) > 0): ?>
    <?php foreach ($eventos as $evento):
        $confirmados = json_decode($evento['confirmados'] ?? '[]', true);
        if (!is_array($confirmados)) $confirmados = [];
        $total_confirmados = count($confirmados);

        $confirmados_associados_ids = [];
        $confirmados_usuarios_ids = [];
        foreach ($confirmados as $conf) {
            if ($conf['table'] === 'associados') {
                $confirmados_associados_ids[] = $conf['id'];
            } else {
                $confirmados_usuarios_ids[] = $conf['id'];
            }
        }

        $nomes_confirmados = ['associados' => [], 'usuarios' => []];
        if (count($confirmados_associados_ids) > 0) {
            $placeholders = str_repeat('?,', count($confirmados_associados_ids) - 1) . '?';
            $stmt_nomes = $db->prepare("SELECT id, nome, sobrenome, tipo FROM associados WHERE id IN ($placeholders)");
            $stmt_nomes->execute($confirmados_associados_ids);
            foreach ($stmt_nomes->fetchAll(PDO::FETCH_ASSOC) as $assoc) {
                $nomes_confirmados['associados'][$assoc['id']] = [
                    'nome' => $assoc['nome'] . ' ' . $assoc['sobrenome'],
                    'tipo' => $assoc['tipo']
                ];
            }
        }
        if (count($confirmados_usuarios_ids) > 0) {
            $placeholders = str_repeat('?,', count($confirmados_usuarios_ids) - 1) . '?';
            $stmt_nomes = $db->prepare("SELECT id, nome, tipo, associado_id FROM usuarios WHERE id IN ($placeholders)");
            $stmt_nomes->execute($confirmados_usuarios_ids);
            foreach ($stmt_nomes->fetchAll(PDO::FETCH_ASSOC) as $usr) {
                $nomes_confirmados['usuarios'][$usr['id']] = [
                    'nome' => $usr['nome'],
                    'tipo' => ucfirst($usr['tipo']),
                    'associado_id' => $usr['associado_id']
                ];
            }
        }

        $nao_confirmados_associados = [];
        foreach ($todos_associados as $assoc) {
            if (!in_array($assoc['id'], $confirmados_associados_ids)) {
                $nao_confirmados_associados[] = $assoc;
            }
        }

        $nao_confirmados_usuarios_puros = [];
        $nao_confirmados_gestores = [];
        foreach ($todos_usuarios as $usr) {
            if (in_array($usr['id'], $confirmados_usuarios_ids)) {
                continue;
            }
            if ($usr['tipo'] === 'gestor') {
                $nao_confirmados_gestores[] = $usr;
            } elseif (empty($usr['associado_id'])) {
                $ja_e_associado = false;
                if (!empty($cpfs_cadastrados_como_associado)) {
                    $stmt_check = $db->prepare("SELECT cpf FROM associados WHERE user_id = ?");
                    $stmt_check->execute([$usr['id']]);
                    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    if ($result && in_array($result['cpf'], $cpfs_cadastrados_como_associado)) {
                        $ja_e_associado = true;
                    }
                }
                if (!$ja_e_associado) {
                    $nao_confirmados_usuarios_puros[] = $usr;
                }
            }
        }
    ?>
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($evento['nome']); ?></strong>
                        <span class="badge bg-light text-dark ms-2"><?php echo date('d/m/Y H:i', strtotime($evento['data_evento'])); ?></span>
                        <?php if (!empty($evento['local'])): ?>
                            <span class="badge bg-info ms-1"><?php echo htmlspecialchars($evento['local']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge bg-success fs-6"><?php echo $total_confirmados; ?> Confirmados</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="bi bi-check-circle-fill"></i> Confirmados (<?php echo $total_confirmados; ?>)</h6>
                        <?php if ($total_confirmados > 0): ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($confirmados as $conf):
                                    $nome = '';
                                    $badge = '';
                                    if ($conf['table'] === 'associados' && isset($nomes_confirmados['associados'][$conf['id']])) {
                                        $nome = $nomes_confirmados['associados'][$conf['id']]['nome'];
                                        $tipo = $nomes_confirmados['associados'][$conf['id']]['tipo'];
                                        if ($tipo === 'Admin') $badge = '<span class="badge bg-danger ms-1">Admin</span>';
                                        elseif ($tipo === 'Gestor') $badge = '<span class="badge bg-primary ms-1">Gestor</span>';
                                        else $badge = '<span class="badge bg-secondary ms-1">Associado</span>';
                                    } elseif ($conf['table'] === 'usuarios' && isset($nomes_confirmados['usuarios'][$conf['id']])) {
                                        $nome = $nomes_confirmados['usuarios'][$conf['id']]['nome'];
                                        $tipo = $nomes_confirmados['usuarios'][$conf['id']]['tipo'];
                                        if ($tipo === 'Gestor') $badge = '<span class="badge bg-primary ms-1">Gestor</span>';
                                        else $badge = '<span class="badge bg-info ms-1">Usuário</span>';
                                    } else {
                                        $nome = 'ID: ' . $conf['id'];
                                        $badge = '<span class="badge bg-secondary ms-1">' . ($conf['table'] ?? 'desconhecido') . '</span>';
                                    }
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-person-fill text-success me-2"></i><?php echo htmlspecialchars($nome); ?></span>
                                        <span><?php echo $badge; ?>
                                        <?php if (isset($conf['data'])): ?>
                                            <br><small class="text-muted"><?php echo date('d/m H:i', strtotime($conf['data'])); ?></small>
                                        <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">Nenhum participante confirmado ainda.</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php $total_pendentes = count($nao_confirmados_associados) + count($nao_confirmados_usuarios_puros) + count($nao_confirmados_gestores); ?>
                        <h6 class="text-warning"><i class="bi bi-clock-fill"></i> Pendentes (<?php echo $total_pendentes; ?>)</h6>
                        <?php if ($total_pendentes > 0): ?>
                            <ul class="list-group">
                                <?php if (count($nao_confirmados_gestores) > 0): ?>
                                    <?php foreach ($nao_confirmados_gestores as $gestor): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-shield-fill text-primary me-2"></i><?php echo htmlspecialchars($gestor['nome']); ?></span>
                                            <span class="badge bg-primary">Gestor</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php foreach ($nao_confirmados_associados as $assoc): 
                                    $badge = '';
                                    if ($assoc['tipo'] === 'Admin') $badge = '<span class="badge bg-danger">Admin</span>';
                                    elseif ($assoc['tipo'] === 'Gestor') $badge = '<span class="badge bg-primary">Gestor</span>';
                                    else $badge = '<span class="badge bg-secondary">Associado</span>';
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-person text-secondary me-2"></i><?php echo htmlspecialchars($assoc['nome'] . ' ' . $assoc['sobrenome']); ?></span>
                                        <?php echo $badge; ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (count($nao_confirmados_usuarios_puros) > 0): ?>
                                    <?php foreach ($nao_confirmados_usuarios_puros as $usr): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-person text-info me-2"></i><?php echo htmlspecialchars($usr['nome']); ?></span>
                                            <span class="badge bg-info">Usuário</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-success"><i class="bi bi-check-all"></i> Todos já confirmaram!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-info">Nenhum evento encontrado.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>