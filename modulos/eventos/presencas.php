<?php
$page_title = "Presença em Eventos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Evento.php';

if (!$user->hasPermission(NIVEL_USUARIO)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$evento = new Evento();
$is_usuario_comum = isset($_SESSION['user_tipo']) && strtolower((string) $_SESSION['user_tipo']) === 'usuario';
$presencas = $is_usuario_comum
    ? $evento->getPresencasUsuarioAgendados($_SESSION['user_id'] ?? 0, $_SESSION['user_table'] ?? 'usuarios')
    : $evento->getPresencaEventos();
?>

<h1 class="mt-4">Presença em Eventos</h1>
<p class="text-muted">
    <?php if ($is_usuario_comum): ?>
        Seus registros de presença (confirmação/ausência/justificativa) em eventos agendados.
    <?php else: ?>
        Lista completa de confirmações de presença em eventos.
    <?php endif; ?>
</p>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-check-fill me-1"></i>Presença em Eventos</span>
                <a href="<?php echo SITE_URL; ?>/eventos" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-event me-1"></i>Voltar aos Eventos
                </a>
            </div>
            <div class="card-body">
                <table id="presencaTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <?php if (!$is_usuario_comum): ?>
                                <th>Associado</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <?php if ($is_usuario_comum): ?>
                                <th>Justificativa</th>
                            <?php endif; ?>
                            <th>Data Evento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presencas as $pres): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pres['evento_nome']); ?></td>
                                <?php if (!$is_usuario_comum): ?>
                                    <td><?php echo htmlspecialchars($pres['associado_nome'] . ' ' . $pres['associado_sobrenome']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-<?php
                                    switch ($pres['status_presenca']) {
                                        case 'Confirmado':
                                            echo 'info';
                                            break;
                                        case 'Presente':
                                            echo 'success';
                                            break;
                                        case 'Ausente':
                                            echo 'danger';
                                            break;
                                        case 'Não Confirmado':
                                            echo 'secondary';
                                            break;
                                        default:
                                            echo 'secondary';
                                    }
                                    ?>"><?php echo htmlspecialchars($pres['status_presenca']); ?></span>
                                </td>
                                <?php if ($is_usuario_comum): ?>
                                    <td><?php echo !empty($pres['justificativa']) ? htmlspecialchars($pres['justificativa']) : '<span class="text-muted">Sem justificativa</span>'; ?></td>
                                <?php endif; ?>
                                <td><?php echo date('d/m/Y', strtotime($pres['data_evento'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('#presencaTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
            },
            "order": [[3, "desc"]], // Ordenar por data do evento (mais recente primeiro)
            "pageLength": 25
        });
    });
</script>
