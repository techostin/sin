<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Total Associados
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='ativo' THEN 1 ELSE 0 END) as ativos FROM associados");
    $assocStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Lavouras Ativas
    $stmt = $db->query("SELECT COUNT(*) as total FROM lavouras WHERE status='Ativa'");
    $lavourasStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Atividades Pendentes/Em Andamento
    $stmt = $db->query("SELECT COUNT(*) as total FROM atividades_diarias WHERE status IN ('Pendente', 'Em andamento') AND excluido = 0");
    $atividadesStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Lotes em Produção
    $stmt = $db->query("SELECT COUNT(*) as total FROM lotes_producao WHERE status_lote='Em Produção' AND excluido = 0");
    $lotesStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Insumos em Alerta (Estoque Baixo)
    $stmt = $db->query("SELECT COUNT(*) as total FROM insumos WHERE quantidade_estoque < 10 AND excluido = 0");
    $insumosStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Inadimplência
    $stmt = $db->query("SELECT COUNT(DISTINCT associado_id) as total FROM mensalidades WHERE status_mensalidade = 'atrasado' OR (status_mensalidade = 'pendente' AND data_vencimento < CURDATE())");
    $inadimplentesStats = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<div class="row g-4 mb-4">
    <!-- Associados -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-primary text-white h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Associados Ativos</h6>
                        <h2 class="mb-0 fw-bold"><?= $assocStats['ativos'] ?> <span class="fs-6 opacity-75 fw-normal">/ <?= $assocStats['total'] ?></span></h2>
                    </div>
                    <div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lavouras -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-success text-white h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Lavouras Ativas</h6>
                        <h2 class="mb-0 fw-bold"><?= $lavourasStats['total'] ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-tree fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lotes -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-info text-white h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Lotes em Produção</h6>
                        <h2 class="mb-0 fw-bold"><?= $lotesStats['total'] ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-box-seam fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Atividades -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-warning text-dark h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Atividades Pendentes</h6>
                        <h2 class="mb-0 fw-bold"><?= $atividadesStats['total'] ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insumos Alerta -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-danger text-white h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Estoque Crítico</h6>
                        <h2 class="mb-0 fw-bold"><?= $insumosStats['total'] ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inadimplência -->
    <div class="col-md-4 col-lg-3">
        <div class="card bg-secondary text-white h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2 opacity-75">Inadimplentes</h6>
                        <h2 class="mb-0 fw-bold"><?= $inadimplentesStats['total'] ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-wallet2 fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom-0">
        <h5 class="mb-0">Sobre a Visão Geral</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Este painel exibe os principais indicadores operacionais e financeiros da associação. Os dados são em tempo real. Para obter detalhes sobre o estoque ou financeiro, utilize os relatórios específicos listados no menu ao lado.</p>
    </div>
</div>

<!-- Tabela escondida vazia caso o DataTable tente inicializar -->
<table id="reportTable" class="d-none"><thead><tr><th></th></tr></thead><tbody><tr><td></td></tr></tbody></table>
<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
}
?>
