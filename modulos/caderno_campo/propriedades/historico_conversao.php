<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/HistoricoConversao.php';
require_once __DIR__ . '/../../../classes/AuditLog.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$propriedadeClass = new Propriedade();
$historicoClass = new HistoricoConversao();
$propriedade = $propriedadeClass->getById($id);

$message = '';
$message_type = '';

// Excluir entrada
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $historicoClass->delete((int)$_GET['excluir']);
    AuditLog::registrar('HISTORICO_CONVERSAO_EXCLUIDO', 'historico_conversao', (int)$_GET['excluir'], 'Entrada excluída');
    header('Location: ' . SITE_URL . '/propriedade/' . $id . '/historico-conversao');
    exit;
}

// Adicionar entrada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'propriedade_id' => $id,
        'associado_id' => $propriedade['associado_id'] ?? null,
        'tipo_uso_solo' => $_POST['tipo_uso_solo'] ?? '',
        'data_inicio' => $_POST['data_inicio'] ?? '',
        'data_fim' => $_POST['data_fim'] ?? '',
        'status' => $_POST['status'] ?? 'EM_CONVERSAO',
        'observacoes' => $_POST['observacoes'] ?? '',
        'criado_por' => (int)$_SESSION['user_id'],
    ];

    if (empty($dados['tipo_uso_solo']) || empty($dados['data_inicio'])) {
        $message = 'Informe o tipo de uso do solo e a data de início.';
        $message_type = 'danger';
    } else {
        $novoId = $historicoClass->create($dados);
        AuditLog::registrar('HISTORICO_CONVERSAO_REGISTRADO', 'historico_conversao', $novoId, 'Uso do solo: ' . $dados['tipo_uso_solo']);
        header('Location: ' . SITE_URL . '/propriedade/' . $id . '/historico-conversao?registrado=1');
        exit;
    }
}

$page_title = "Histórico de Conversão";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$propriedade) {
    echo '<div class="alert alert-danger m-3">Propriedade não encontrada.</div>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Verificação IDOR
if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
    if ($propriedade['associado_id'] != $user->getAssociadoId()) {
        echo '<div class="alert alert-danger m-3">Acesso Negado: Esta propriedade pertence a outro produtor.</div>';
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

$registros = $historicoClass->getByPropriedade($id);
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-clock-history text-warning"></i> Histórico de Conversão (RF03)</h1>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedades" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="alert alert-light border">
        <strong><?php echo htmlspecialchars($propriedade['propriedade']); ?></strong> — Área total:
        <?php echo number_format((float)$propriedade['area_total'], 2, ',', '.'); ?> ha
        <?php if (!empty($propriedade['zona_amortecimento'])): ?>
            <span class="badge bg-success ms-2"><i class="bi bi-shield-check"></i> Zona de amortecimento cadastrada</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-triangle"></i> Sem zona de amortecimento</span>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['registrado'])): ?>
        <div class="alert alert-success">Entrada registrada. O período de conversão restante foi calculado automaticamente.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><i class="bi bi-plus-circle"></i> Registrar Uso do Solo</strong></div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Tipo de Uso do Solo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="tipo_uso_solo" required placeholder="Ex.: Cultura anual — milho / Pomar — goiaba / Pastagem">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Início <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="data_inicio" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fim (opcional)</label>
                            <input type="date" class="form-control" name="data_fim">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="EM_CONVERSAO">Em conversão</option>
                                <option value="ORGANICO">Orgânico</option>
                                <option value="CONVENCIONAL">Convencional</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" name="observacoes" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong><i class="bi bi-list-ol"></i> Cronologia da Área</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Uso do Solo</th>
                                    <th>Período</th>
                                    <th>Conversão</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$registros): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum registro de uso do solo.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($registros as $reg): ?>
                                    <?php $calc = $historicoClass->calcularConversao($reg['data_inicio'], $reg['tipo_uso_solo'], $reg['data_fim']); ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($reg['tipo_uso_solo']); ?></strong></td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($reg['data_inicio'])); ?>
                                            → <?php echo $reg['data_fim'] ? date('d/m/Y', strtotime($reg['data_fim'])) : 'atual'; ?>
                                        </td>
                                        <td>
                                            <?php if ($calc['concluido']): ?>
                                                <span class="badge bg-success">Concluída (<?php echo date('d/m/Y', strtotime($calc['data_conclusao'])); ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Faltam ~<?php echo $calc['restante_meses']; ?> meses</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $reg['status'] === 'ORGANICO' ? 'bg-success' : ($reg['status'] === 'EM_CONVERSAO' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                                <?php echo $reg['status']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo SITE_URL; ?>/propriedade/<?php echo $id; ?>/historico-conversao?excluir=<?php echo $reg['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir esta entrada?');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
