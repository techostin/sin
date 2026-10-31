<?php
$page_title = "Nova Ocorrência";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/AgroOcorrencia.php';
require_once __DIR__ . '/../../classes/AgroSetor.php';
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

$associadoId    = $user->getAssociadoId();
$isGestor       = $user->hasPermission(NIVEL_GESTOR);

$ocorrenciaClass = new AgroOcorrencia();
$setorClass      = new AgroSetor();

$setores = $setorClass->getAll($isGestor ? null : $associadoId);

$message = '';
$message_type = '';
$dados = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($dados['titulo'] ?? '') === '' || trim($dados['data_ocorrencia'] ?? '') === '') {
        $message = 'Informe o título e a data da ocorrência.';
        $message_type = 'danger';
    } else {
        $dados['associado_id'] = !empty($dados['associado_id']) ? $dados['associado_id'] : $associadoId;
        $dados['tipo'] = $dados['tipo'] ?? 'Outro';
        $dados['severidade'] = $dados['severidade'] ?? 'Baixa';
        $dados['status'] = $dados['status'] ?? 'Aberta';
        if ($ocorrenciaClass->create($dados)) {
            AuditLog::registrar('AGRO_OCORRENCIA_CADASTRADA', 'agro_ocorrencias', null, 'Ocorrência: ' . $dados['titulo']);
            header('Location: ' . SITE_URL . '/agroindustria/ocorrencias?cadastrada=1');
            exit;
        }
        $message = 'Erro ao salvar a ocorrência. Tente novamente.';
        $message_type = 'danger';
    }
}

$tiposOcorrencia = [
    'Contaminação' => 'Contaminação',
    'Falha de Processo' => 'Falha de Processo',
    'Qualidade' => 'Qualidade',
    'Higienização' => 'Higienização',
    'Equipamento' => 'Equipamento',
    'Rastreabilidade' => 'Rastreabilidade',
    'Outro' => 'Outro',
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-exclamation-triangle text-warning"></i> Nova Ocorrência</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/ocorrencias" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Título <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="titulo" required value="<?php echo htmlspecialchars($dados['titulo'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_ocorrencia" required value="<?php echo htmlspecialchars($dados['data_ocorrencia'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <?php foreach ($tiposOcorrencia as $tk => $tv): ?>
                            <option value="<?php echo $tk; ?>" <?php echo (($dados['tipo'] ?? '') === $tk) ? 'selected' : ''; ?>><?php echo $tv; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Setor</label>
                    <select class="form-select" name="setor_id">
                        <option value="">Nenhum</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo (($dados['setor_id'] ?? '') == $s['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Severidade</label>
                    <select class="form-select" name="severidade">
                        <?php foreach (['Baixa', 'Média', 'Alta'] as $sv): ?>
                            <option value="<?php echo $sv; ?>" <?php echo (($dados['severidade'] ?? '') === $sv) ? 'selected' : ''; ?>><?php echo $sv; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['Aberta', 'Em Tratamento', 'Resolvida'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo (($dados['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="3"><?php echo htmlspecialchars($dados['descricao'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Ação Adotada</label>
                    <textarea class="form-control" name="acao_manejo" rows="2"><?php echo htmlspecialchars($dados['acao_manejo'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Ocorrência</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>