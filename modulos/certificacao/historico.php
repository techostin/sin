<?php
$page_title = "Histórico de Certificações";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$associado_id = $_GET['associado_id'] ?? null;
if (!$associado_id || !is_numeric($associado_id)) {
    echo "<div class='alert alert-danger'>Associado não especificado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar dados do associado
$stmtAssoc = $db->prepare("SELECT nome, sobrenome FROM associados WHERE id = ?");
$stmtAssoc->execute([$associado_id]);
$associado = $stmtAssoc->fetch(PDO::FETCH_ASSOC);

// Buscar todas as certificações deste associado
$stmt = $db->prepare("SELECT * FROM certificacao_participativa 
                      WHERE associado_id = ? 
                      ORDER BY data_validade DESC, created_at DESC");
$stmt->execute([$associado_id]);
$historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mt-4">
    <h1>Histórico de Certificações</h1>
    <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar à Lista
    </a>
</div>
<p class="lead"><?php echo $associado['nome'] . ' ' . $associado['sobrenome']; ?></p>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <?php if (empty($historico)): ?>
            <div class="alert alert-info">Nenhum registro de certificação encontrado para este associado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nº Certificado</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Emissão</th>
                            <th>Validade</th>
                            <th>Organismo</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $cert): 
                            $statusClass = 'secondary';
                            if ($cert['status'] === 'Ativa') $statusClass = 'success';
                            if ($cert['status'] === 'Vencida') $statusClass = 'danger';
                            if ($cert['status'] === 'Em Conversão') $statusClass = 'warning';
                        ?>
                            <tr>
                                <td><strong><?php echo $cert['numero_certificado'] ?: '-'; ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $cert['tipo_certificacao']; ?></span></td>
                                <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $cert['status']; ?></span></td>
                                <td><?php echo $cert['data_emissao'] ? date('d/m/Y', strtotime($cert['data_emissao'])) : '-'; ?></td>
                                <td><?php echo $cert['data_validade'] ? date('d/m/Y', strtotime($cert['data_validade'])) : '-'; ?></td>
                                <td><?php echo $cert['organismo_certificador'] ?: '-'; ?></td>
                                <td class="small"><?php echo $cert['observacoes'] ? mb_strimwidth($cert['observacoes'], 0, 50, '...') : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <h3>Linha do Tempo de Conformidade</h3>
    <div class="timeline-v2 mt-3">
        <?php foreach ($historico as $cert): ?>
            <div class="border-start border-3 border-success ps-4 pb-4 position-relative">
                <div class="position-absolute start-0 translate-middle-x bg-success rounded-circle" style="width: 12px; height: 12px; margin-left: -1.5px; top: 0;"></div>
                <div class="fw-bold"><?php echo $cert['data_emissao'] ? date('M/Y', strtotime($cert['data_emissao'])) : 'Sem data'; ?></div>
                <div class="text-muted small"><?php echo $cert['tipo_certificacao']; ?> - <?php echo $cert['status']; ?></div>
                <div>Certificado: <?php echo $cert['numero_certificado'] ?: 'N/A'; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.timeline-v2 {
    position: relative;
    padding-left: 20px;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
