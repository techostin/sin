<?php
$page_title = "Relatórios de Conformidade Orgânica";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/ReportConformidade.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$catalogo = ReportConformidade::catalogo();

// Filtros atuais (repassados aos botões de exportação)
$filtros = [];
foreach (['data_inicio', 'data_fim', 'status', 'tipo', 'associado_id'] as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[$campo] = $_GET[$campo];
    }
}

// Não-gestores só veem os próprios dados
$isGestor = $user->hasPermission(NIVEL_GESTOR);

// Opções de filtro
$associados = [];
try {
    $db = Database::getInstance()->getConnection();
    if ($isGestor) {
        $stmt = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome, sobrenome");
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // ignora erro de listagem
}

$queryString = http_build_query($filtros);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Relatórios de Conformidade Orgânica</h1>
            <p class="mb-0 text-muted">Emita relatórios de conformidade para auditoria e fiscalização (Fase B)</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Filtros</h5></div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" name="data_inicio" value="<?php echo htmlspecialchars($filtros['data_inicio'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" name="data_fim" value="<?php echo htmlspecialchars($filtros['data_fim'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Todos</option>
                        <option value="APROVADO" <?php echo ($filtros['status'] ?? '') === 'APROVADO' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="ALERTA_FRAUDE" <?php echo ($filtros['status'] ?? '') === 'ALERTA_FRAUDE' ? 'selected' : ''; ?>>Alerta de Fraude</option>
                        <option value="EM_ANALISE" <?php echo ($filtros['status'] ?? '') === 'EM_ANALISE' ? 'selected' : ''; ?>>Em Análise</option>
                        <option value="EM_CONVERSAO" <?php echo ($filtros['status'] ?? '') === 'EM_CONVERSAO' ? 'selected' : ''; ?>>Em Conversão</option>
                        <option value="ORGANICO" <?php echo ($filtros['status'] ?? '') === 'ORGANICO' ? 'selected' : ''; ?>>Orgânico</option>
                        <option value="ATIVO" <?php echo ($filtros['status'] ?? '') === 'ATIVO' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="VENDIDO" <?php echo ($filtros['status'] ?? '') === 'VENDIDO' ? 'selected' : ''; ?>>Vendido</option>
                        <option value="MORTO" <?php echo ($filtros['status'] ?? '') === 'MORTO' ? 'selected' : ''; ?>>Morto</option>
                        <option value="ABATIDO" <?php echo ($filtros['status'] ?? '') === 'ABATIDO' ? 'selected' : ''; ?>>Abatido</option>
                    </select>
                </div>
                <?php if ($isGestor): ?>
                <div class="col-md-3">
                    <label class="form-label">Associado (Produtor)</label>
                    <select class="form-select" name="associado_id">
                        <option value="">Todos</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo (int)$a['id']; ?>" <?php echo ($filtros['associado_id'] ?? '') == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(trim($a['nome'] . ' ' . $a['sobrenome'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill me-1"></i> Aplicar Filtros</button>
                    <a href="<?php echo SITE_URL; ?>/relatorios-conformidade" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards dos relatórios -->
    <div class="row g-4">
        <?php foreach ($catalogo as $slug => $cfg): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="<?php echo $cfg['icone']; ?> fs-3 text-success me-2"></i>
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($cfg['titulo']); ?></h5>
                        </div>
                        <p class="card-text small text-muted"><?php echo htmlspecialchars($cfg['descricao']); ?></p>
                    </div>
                    <div class="card-footer bg-white d-flex gap-2">
                        <a href="<?php echo SITE_URL; ?>/relatorios-conformidade/gerar?relatorio=<?php echo $slug; ?>&formato=pdf&<?php echo $queryString; ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF</a>
                        <a href="<?php echo SITE_URL; ?>/relatorios-conformidade/gerar?relatorio=<?php echo $slug; ?>&formato=excel&<?php echo $queryString; ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel-fill me-1"></i> Excel</a>
                        <a href="<?php echo SITE_URL; ?>/relatorios-conformidade/gerar?relatorio=<?php echo $slug; ?>&formato=html&<?php echo $queryString; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
