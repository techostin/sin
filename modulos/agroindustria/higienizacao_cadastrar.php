<?php
$page_title = "Novo Registro de Higienização";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ControleHigienizacao.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$higClass = new ControleHigienizacao();
$sanitizantesPermitidos = $higClass->listarSanitizantesPermitidos();
$sanitizantesNomes = array_column($sanitizantesPermitidos, 'nome');
$message = '';
$message_type = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'data_hora_inicio' => $_POST['data_hora_inicio'] ?? '',
        'data_hora_fim' => !empty($_POST['data_hora_fim']) ? $_POST['data_hora_fim'] : null,
        'equipamento_linha' => $_POST['equipamento_linha'] ?? '',
        'sanitizante_utilizado' => $_POST['sanitizante_utilizado'] ?? '',
        'responsavel_nome' => $_POST['responsavel_nome'] ?? '',
        'observacoes' => $_POST['observacoes'] ?? '',
        'associado_id' => !empty($_POST['associado_id']) ? $_POST['associado_id'] : ($user->getAssociadoId() ?? null),
        'criado_por' => (int)$_SESSION['user_id'],
    ];

    if (empty($dados['data_hora_inicio']) || empty($dados['equipamento_linha']) || empty($dados['sanitizante_utilizado']) || empty($dados['responsavel_nome'])) {
        $message = 'Preencha os campos obrigatórios (início, equipamento/linha, sanitizante e responsável).';
        $message_type = 'danger';
    } elseif (!in_array(trim($dados['sanitizante_utilizado']), $sanitizantesNomes, true)) {
        $message = 'BLOQUEADO (conformidade): o sanitizante informado não consta na lista de produtos permitidos para higienização em produção orgânica (Anexo I — Portaria 52/2021, redação da Portaria SDA 811/2023).';
        $message_type = 'danger';
    } else {
        $id = $higClass->create($dados);
        AuditLog::registrar('HIGIENIZACAO_REGISTRADA', 'controle_higienizacao', $id, 'Equipamento/linha: ' . $dados['equipamento_linha']);
        header('Location: ' . SITE_URL . '/agroindustria/higienizacao?registrada=1');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-droplet text-info"></i> Novo Registro de Higienização</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/higienizacao" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Início <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" name="data_hora_inicio" required value="<?php echo htmlspecialchars($_POST['data_hora_inicio'] ?? date('Y-m-d\TH:i')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Término</label>
                    <input type="datetime-local" class="form-control" name="data_hora_fim" value="<?php echo htmlspecialchars($_POST['data_hora_fim'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Equipamento / Linha <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="equipamento_linha" required value="<?php echo htmlspecialchars($_POST['equipamento_linha'] ?? ''); ?>" placeholder="Ex.: Linha de envase / Moinho / Tanque 03">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sanitizante Utilizado <span class="text-danger">*</span></label>
                    <select class="form-select" name="sanitizante_utilizado" required>
                        <option value="">Selecione o sanitizante...</option>
                        <?php foreach ($sanitizantesPermitidos as $s): ?>
                            <option value="<?php echo htmlspecialchars($s['nome']); ?>" <?php echo (($dados['sanitizante_utilizado'] ?? '') === $s['nome']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        <i class="bi bi-shield-check me-1"></i>Apenas produtos permitidos para higienização em sistemas orgânicos
                        (Anexo I — Portaria 52/2021, com redação dada pela Portaria SDA 811/2023).
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Responsável <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="responsavel_nome" required value="<?php echo htmlspecialchars($_POST['responsavel_nome'] ?? ''); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" name="observacoes" rows="2"><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-info text-white"><i class="bi bi-save"></i> Registrar Higienização</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
