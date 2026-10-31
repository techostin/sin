<?php
$page_title = "Editar Atividade Diária";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroAtividadeDiaria.php';
require_once __DIR__ . '/../../classes/AgroSetor.php';
require_once __DIR__ . '/../../classes/AgroCategoriaAtividade.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

if (!$user->temAgroindustria()) {
    echo "<div class='alert alert-danger m-4'>Acesso restrito a produtores de agroindústria.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . SITE_URL . '/agroindustria/atividades');
    exit;
}

$associadoId    = $user->getAssociadoId();
$isGestor       = $user->hasPermission(NIVEL_GESTOR);

$atividadeClass = new AgroAtividadeDiaria();
$atividade = $atividadeClass->getById($id);
if (!$atividade) {
    header('Location: ' . SITE_URL . '/agroindustria/atividades');
    exit;
}

if (!$isGestor && (int)$atividade['associado_id'] !== (int)$associadoId) {
    echo "<div class='alert alert-danger m-4'>Você não tem permissão para editar esta atividade.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$setorClass     = new AgroSetor();
$categoriaClass = new AgroCategoriaAtividade();

$setores     = $setorClass->getAll($isGestor ? null : $associadoId);
$categorias  = $categoriaClass->getAll();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($_POST['nome'] ?? '') === '' || trim($_POST['data_atividade'] ?? '') === '') {
        $message = 'Informe o nome e a data da atividade.';
        $message_type = 'danger';
    } elseif ($atividadeClass->update($id, $_POST)) {
        AuditLog::registrar('AGRO_ATIVIDADE_ATUALIZADA', 'agro_atividades_diarias', $id, 'Atividade: ' . $_POST['nome']);
        header('Location: ' . SITE_URL . '/agroindustria/atividades?atualizada=1');
        exit;
    } else {
        $message = 'Erro ao atualizar a atividade. Tente novamente.';
        $message_type = 'danger';
    }
    $atividade = array_merge($atividade, $_POST);
}

$tiposAtividades = [
    'Recebimento' => 'Recebimento de Matéria-Prima',
    'Processamento' => 'Processamento',
    'Embalagem' => 'Embalagem',
    'Qualidade' => 'Controle de Qualidade',
    'Higienização' => 'Higienização',
    'Estocagem' => 'Estocagem',
    'Expedição' => 'Expedição',
    'Outro' => 'Outro',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-calendar-check text-primary"></i> Editar Atividade Diária</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/atividades" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome da Atividade <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($atividade['nome']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_atividade" required value="<?php echo htmlspecialchars($atividade['data_atividade']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['Concluída', 'Em Andamento', 'Planejada', 'Cancelada'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($atividade['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Setor</label>
                    <select class="form-select" name="setor_id">
                        <option value="">Nenhum</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($atividade['setor_id'] == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" name="categoria_id">
                        <option value="">Nenhuma</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($atividade['categoria_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo de Atividade</label>
                    <select class="form-select" name="tipo_atividade">
                        <option value="">Nenhum</option>
                        <?php foreach ($tiposAtividades as $tk => $tv): ?>
                            <option value="<?php echo $tk; ?>" <?php echo ($atividade['tipo_atividade'] === $tk) ? 'selected' : ''; ?>><?php echo $tv; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Hora Início</label>
                    <input type="time" class="form-control" name="hora_inicio" value="<?php echo htmlspecialchars($atividade['hora_inicio'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hora Fim</label>
                    <input type="time" class="form-control" name="hora_fim" value="<?php echo htmlspecialchars($atividade['hora_fim'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Insumo Utilizado</label>
                    <input type="text" class="form-control" name="insumo_utilizado" value="<?php echo htmlspecialchars($atividade['insumo_utilizado'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($atividade['descricao'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"><?php echo htmlspecialchars($atividade['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Alterações</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>