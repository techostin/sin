<?php
// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Obter o nível do usuário
$userLevel = $_SESSION['user_nivel'] ?? 3; // Padrão para usuário comum
$userId = $_SESSION['user_id'] ?? 0;

// Obter lista de associações para os selects
$assocOptions = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmtAssoc = $db->query("SELECT id, nome FROM associacoes ORDER BY nome ASC");
    $assocOptions = $stmtAssoc->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silencioso se a tabela não existir ou erro
}

// Configurações da página
$pageTitle = 'Relatórios';
$pageDescription = 'Selecione o tipo de relatório desejado';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

?>
<!-- Adicionar o script de relatórios -->
<script src="<?php echo SITE_URL; ?>/assets/js/relatorios.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted mb-0"><?php echo $pageDescription; ?></p>
        </div>
    </div>

    <div class="row">
        <!-- Cartões de Estatísticas Rápidas -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-tachometer-alt me-2"></i> Visão Geral
                    </h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex flex-column h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-uppercase mb-0">Associados</h6>
                                                <h1 class="display-5 mb-0 fw-bold">
                                                    <?php 
                                                        try {
                                                            $db = Database::getInstance()->getConnection();
                                                            $stmt = $db->query("SELECT COUNT(*) as total FROM associados");
                                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo $result['total'];
                                                        } catch (Exception $e) {
                                                            echo '0';
                                                        }
                                                    ?>
                                                </h1>
                                            </div>
                                            <i class="fas fa-users fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <a href="<?php echo SITE_URL; ?>/modulos/associados/listar.php" class="text-white-50 small d-flex align-items-center">
                                        Ver detalhes <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex flex-column h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-uppercase mb-0">Propriedades</h6>
                                                <h1 class="display-5 mb-0 fw-bold">
                                                    <?php 
                                                        try {
                                                            $stmt = $db->query("SELECT COUNT(*) as total FROM propriedades");
                                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo $result['total'];
                                                        } catch (Exception $e) {
                                                            echo '0';
                                                        }
                                                    ?>
                                                </h1>
                                            </div>
                                            <i class="fas fa-tractor fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <a href="<?php echo SITE_URL; ?>/modulos/propriedades/listar.php" class="text-white-50 small d-flex align-items-center">
                                        Ver detalhes <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-warning text-dark h-100">
                                <div class="card-body">
                                    <div class="d-flex flex-column h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-uppercase mb-0">Atividades Hoje</h6>
                                                <h1 class="display-5 mb-0 fw-bold">
                                                    <?php 
                                                        try {
                                                            $stmt = $db->query("SELECT COUNT(*) as total FROM atividades_diarias WHERE DATE(data_atividade) = CURDATE()");
                                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo $result['total'];
                                                        } catch (Exception $e) {
                                                            echo '0';
                                                        }
                                                    ?>
                                                </h1>
                                            </div>
                                            <i class="fas fa-tasks fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <a href="<?php echo SITE_URL; ?>/modulos/caderno_campo/atividade_diaria/listar.php" class="text-dark small d-flex align-items-center">
                                        Ver atividades <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex flex-column h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="text-uppercase mb-0">Insumos Críticos</h6>
                                                <h1 class="display-5 mb-0 fw-bold">
                                                    <?php 
                                                        try {
                                                            $stmt = $db->query("SELECT COUNT(*) as total FROM insumos WHERE quantidade <= estoque_minimo");
                                                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo $result['total'];
                                                        } catch (Exception $e) {
                                                            echo '0';
                                                        }
                                                    ?>
                                                </h1>
                                            </div>
                                            <i class="fas fa-boxes fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <a href="<?php echo SITE_URL; ?>/modulos/caderno_campo/insumos/listar.php" class="text-white-50 small d-flex align-items-center">
                                        Ver itens <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Relatórios -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i> Gerar Relatórios
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Card de Relatório de Associados -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                            <i class="fas fa-users fa-2x text-primary"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Relatório de Associados</h5>
                                    </div>
                                    <p class="card-text text-muted small">Gere relatórios completos com informações dos associados, incluindo dados pessoais, contatos e situação cadastral.</p>
                                    
                                    <!-- Formulário para coletar informações do relatório -->
                                    <form id="formRelatorioAssociados" action="associados.php" method="get" target="_blank">
                                        <div class="mb-3">
                                            <label for="nome_associacao" class="form-label small fw-bold">Nome da Associação / Entidade</label>
                                            <?php if (!empty($assocOptions)): ?>
                                            <select class="form-select form-select-sm" id="nome_associacao" name="nome_entidade">
                                                <option value="">-- Selecione ou deixe em branco --</option>
                                                <?php foreach ($assocOptions as $opt): ?>
                                                    <option value="<?php echo htmlspecialchars($opt['nome']); ?>">
                                                        <?php echo htmlspecialchars($opt['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__outro__">✏️ Digitar outro nome...</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1 d-none" id="nome_associacao_outro"
                                                   placeholder="Digite o nome da entidade">
                                            <?php else: ?>
                                            <input type="text" class="form-control form-control-sm" id="nome_associacao_input" name="nome_entidade"
                                                   placeholder="Ex: Associação dos Produtores Rurais">
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-3">
                                            <label for="nome_responsavel" class="form-label small fw-bold">Responsável / Assinatura</label>
                                            <input type="text" class="form-control form-control-sm" id="nome_responsavel" name="nome_responsavel" 
                                                   placeholder="Opcional - Nome do responsável">
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="tipo" value="pdf" class="btn btn-sm btn-outline-danger flex-grow-1">
                                                <i class="far fa-file-pdf me-1"></i> Gerar PDF
                                            </button>
                                            <button type="submit" name="tipo" value="excel" class="btn btn-sm btn-outline-success flex-grow-1">
                                                <i class="far fa-file-excel me-1"></i> Exportar Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card de Relatório de Propriedades -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                            <i class="fas fa-tractor fa-2x text-success"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Relatório de Propriedades</h5>
                                    </div>
                                    <p class="card-text text-muted small">Relatório detalhado das propriedades rurais, incluindo localização, área total, culturas e situação cadastral.</p>
                                    <div class="d-flex gap-2">
                                        <a href="propriedades.php?tipo=pdf" class="btn btn-sm btn-outline-danger">
                                            <i class="far fa-file-pdf me-1"></i> Gerar PDF
                                        </a>
                                        <a href="propriedades.php?tipo=excel" class="btn btn-sm btn-outline-success">
                                            <i class="far fa-file-excel me-1"></i> Exportar Excel
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalRelatorioPersonalizado" data-tipo="propriedades">
                                            <i class="fas fa-sliders-h me-1"></i> Filtrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Relatório de Atividades -->
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body text-center">
                                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                        <i class="fas fa-tasks fa-2x text-warning"></i>
                                    </div>
                                    <h5 class="card-title">Relatório de Atividades</h5>
                                    <p class="card-text text-muted small">Registro completo das atividades diárias realizadas nas propriedades.</p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>/relatorios/atividades.php?tipo=pdf" class="btn btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-pdf me-2"></i> Gerar PDF
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/relatorios/atividades.php?tipo=excel" class="btn btn-outline-success" target="_blank">
                                            <i class="fas fa-file-excel me-2"></i> Exportar Excel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Relatório de Insumos -->
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body text-center">
                                    <div class="bg-info bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                        <i class="fas fa-boxes fa-2x text-info"></i>
                                    </div>
                                    <h5 class="card-title">Relatório de Insumos</h5>
                                    <p class="card-text text-muted small">Controle de estoque de insumos, incluindo níveis críticos e histórico de movimentação.</p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>/relatorios/insumos.php?tipo=pdf" class="btn btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-pdf me-2"></i> Gerar PDF
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/relatorios/insumos.php?tipo=excel" class="btn btn-outline-success" target="_blank">
                                            <i class="fas fa-file-excel me-2"></i> Exportar Excel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Relatório de Produção -->
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body text-center">
                                    <div class="bg-purple bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                        <i class="fas fa-seedling fa-2x" style="color: #6f42c1;"></i>
                                    </div>
                                    <h5 class="card-title">Relatório de Produção</h5>
                                    <p class="card-text text-muted small">Acompanhamento da produção agrícola por cultura, safra e propriedade.</p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>/relatorios/lavouras.php?tipo=pdf" class="btn btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-pdf me-2"></i> Gerar PDF
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/relatorios/lavouras.php?tipo=excel" class="btn btn-outline-success" target="_blank">
                                            <i class="fas fa-file-excel me-2"></i> Exportar Excel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Relatório Financeiro -->
                        <?php if ($userLevel <= 2): // Apenas para administradores e gestores ?>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                            <i class="fas fa-dollar-sign fa-2x text-danger"></i>
                                        </div>
                                        <h5 class="card-title mb-0">Relatório Financeiro</h5>
                                    </div>
                                    <p class="card-text text-muted small">Análise de receitas, despesas e saldo por período.</p>

                                    <form id="formRelatorioFinanceiro" action="<?php echo SITE_URL; ?>/relatorios/financeiro.php" method="get" target="_blank">
                                        <div class="mb-2">
                                            <label for="fin_nome_entidade" class="form-label small fw-bold">Nome da Associação / Entidade</label>
                                            <?php
                                            // Tentar buscar associações cadastradas
                                            $assocOptions = [];
                                            try {
                                                $dbConn = Database::getInstance()->getConnection();
                                                $stmtAssoc = $dbConn->query("SELECT id, nome FROM associacoes WHERE status = 'ativo' ORDER BY nome ASC");
                                                $assocOptions = $stmtAssoc->fetchAll(PDO::FETCH_ASSOC);
                                            } catch (Exception $e) { $assocOptions = []; }
                                            ?>
                                            <?php if (!empty($assocOptions)): ?>
                                            <select class="form-select form-select-sm" id="fin_nome_entidade" name="nome_entidade">
                                                <option value="">-- Selecione ou deixe em branco --</option>
                                                <?php foreach ($assocOptions as $opt): ?>
                                                    <option value="<?php echo htmlspecialchars($opt['nome']); ?>">
                                                        <?php echo htmlspecialchars($opt['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="__outro__">✏️ Digitar outro nome...</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1 d-none" id="fin_nome_outro"
                                                   placeholder="Digite o nome da entidade">
                                            <?php else: ?>
                                            <input type="text" class="form-control form-control-sm" id="fin_nome_entidade" name="nome_entidade"
                                                   placeholder="Ex: Associação dos Produtores Rurais">
                                            <?php endif; ?>
                                        </div>
                                        <div class="row g-1 mb-2">
                                            <div class="col">
                                                <label class="form-label small fw-bold">Data Início</label>
                                                <input type="date" class="form-control form-control-sm" name="data_inicio">
                                            </div>
                                            <div class="col">
                                                <label class="form-label small fw-bold">Data Fim</label>
                                                <input type="date" class="form-control form-control-sm" name="data_fim">
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="tipo" value="pdf" class="btn btn-sm btn-outline-danger flex-grow-1">
                                                <i class="far fa-file-pdf me-1"></i> Gerar PDF
                                            </button>
                                            <button type="submit" name="tipo" value="excel" class="btn btn-sm btn-outline-success flex-grow-1">
                                                <i class="far fa-file-excel me-1"></i> Excel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Relatório Personalizado -->
                        <div class="col-md-4">
                            <div class="card h-100 border">
                                <div class="card-body text-center">
                                    <div class="bg-secondary bg-opacity-10 p-3 rounded-circle d-inline-flex mb-3">
                                        <i class="fas fa-cogs fa-2x text-secondary"></i>
                                    </div>
                                    <h5 class="card-title">Relatório Personalizado</h5>
                                    <p class="card-text text-muted small">Crie um relatório personalizado com os filtros e colunas desejadas.</p>
                                </div>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <div class="d-grid">
                                        <a href="#" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalRelatorioPersonalizado">
                                            <i class="fas fa-sliders-h me-2"></i> Personalizar Relatório
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Relatório Personalizado -->
<div class="modal fade" id="modalRelatorioPersonalizado" tabindex="-1" aria-labelledby="modalRelatorioPersonalizadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRelatorioPersonalizadoLabel">Criar Relatório Personalizado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formRelatorioPersonalizado">
                    <div class="mb-3">
                        <label for="tipoRelatorio" class="form-label">Tipo de Relatório</label>
                        <select class="form-select" id="tipoRelatorio" required>
                            <option value="">Selecione o tipo de relatório</option>
                            <option value="associados">Associados</option>
                            <option value="propriedades">Propriedades</option>
                            <option value="atividades">Atividades</option>
                            <option value="insumos">Insumos</option>
                            <option value="producao">Produção</option>
                            <?php if ($userLevel <= 2): ?>
                            <option value="financeiro">Financeiro</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="dataInicio" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="dataInicio">
                        </div>
                        <div class="col-md-6">
                            <label for="dataFim" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="dataFim">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Colunas para Incluir</label>
                        <div class="border p-3 rounded" id="colunasRelatorio" style="max-height: 200px; overflow-y: auto;">
                            <!-- Colunas serão carregadas via JavaScript -->
                            <div class="text-muted text-center py-3">Selecione um tipo de relatório para ver as colunas disponíveis</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ordenarPor" class="form-label">Ordenar Por</label>
                        <select class="form-select" id="ordenarPor">
                            <option value="data">Data</option>
                            <option value="nome">Nome</option>
                            <option value="valor">Valor</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGerarRelatorio">
                    <i class="fas fa-file-export me-2"></i> Gerar Relatório
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts necessários -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    .card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .bg-purple {
        background-color: #6f42c1;
    }
    .text-purple {
        color: #6f42c1;
    }
</style>

</script>

<script>
// Lógica de toggle para campo "Outro" (Vanilla JS - Independente de jQuery)
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'nome_associacao') {
        const outro = document.getElementById('nome_associacao_outro');
        if (e.target.value === '__outro__') {
            outro.classList.remove('d-none');
            outro.setAttribute('name', 'nome_entidade');
            outro.focus();
            e.target.removeAttribute('name');
        } else {
            outro.classList.add('d-none');
            outro.removeAttribute('name');
            e.target.setAttribute('name', 'nome_entidade');
        }
    }
    
    if (e.target && e.target.id === 'fin_nome_entidade') {
        const outro = document.getElementById('fin_nome_outro');
        if (e.target.value === '__outro__') {
            outro.classList.remove('d-none');
            outro.setAttribute('name', 'nome_entidade');
            outro.focus();
            e.target.removeAttribute('name');
        } else {
            outro.classList.add('d-none');
            outro.removeAttribute('name');
            e.target.setAttribute('name', 'nome_entidade');
        }
    }
});

$(document).ready(function() {
    // Inicializar tooltips (necessita Bootstrap)
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Não preencher datas por padrão para garantir visibilidade total dos dados (Solicitação do Usuário)
    $('input[type="date"]').val('');

    
    // Carregar colunas dinamicamente ao selecionar o tipo de relatório
    $('#tipoRelatorio').on('change', function() {
        const tipo = $(this).val();
        let colunas = [];
        
        // Definir colunas com base no tipo de relatório
        switch(tipo) {
            case 'associados':
                colunas = [
                    { nome: 'id', label: 'ID', padrao: true },
                    { nome: 'nome', label: 'Nome', padrao: true },
                    { nome: 'cpf', label: 'CPF', padrao: true },
                    { nome: 'email', label: 'E-mail', padrao: true },
                    { nome: 'telefone', label: 'Telefone', padrao: true },
                    { nome: 'endereco', label: 'Endereço', padrao: false },
                    { nome: 'cidade', label: 'Cidade', padrao: false },
                    { nome: 'estado', label: 'Estado', padrao: false },
                    { nome: 'data_cadastro', label: 'Data de Cadastro', padrao: true },
                    { nome: 'status', label: 'Status', padrao: true }
                ];
                break;
            case 'propriedades':
                colunas = [
                    { nome: 'id', label: 'ID', padrao: true },
                    { nome: 'nome', label: 'Nome da Propriedade', padrao: true },
                    { nome: 'proprietario', label: 'Proprietário', padrao: true },
                    { nome: 'area_total', label: 'Área Total (ha)', padrao: true },
                    { nome: 'cidade', label: 'Cidade', padrao: true },
                    { nome: 'estado', label: 'UF', padrao: true },
                    { nome: 'total_talhoes', label: 'Nº de Talhões', padrao: true },
                    { nome: 'culturas', label: 'Culturas', padrao: true },
                    { nome: 'data_cadastro', label: 'Data de Cadastro', padrao: true },
                    { nome: 'status', label: 'Status', padrao: true }
                ];
                break;
            case 'atividades':
                colunas = [
                    { id: 'id', nome: 'ID', padrao: true },
                    { id: 'data', nome: 'Data', padrao: true },
                    { id: 'tipo', nome: 'Tipo de Atividade', padrao: true },
                    { id: 'propriedade', nome: 'Propriedade', padrao: true },
                    { id: 'responsavel', nome: 'Responsável', padrao: true },
                    { id: 'status', nome: 'Status', padrao: true },
                    { id: 'descricao', nome: 'Descrição', padrao: false },
                    { id: 'insumos_utilizados', nome: 'Insumos Utilizados', padrao: false }
                ];
                break;
            case 'insumos':
                colunas = [
                    { id: 'id', nome: 'Código', padrao: true },
                    { id: 'nome', nome: 'Nome do Insumo', padrao: true },
                    { id: 'categoria', nome: 'Categoria', padrao: true },
                    { id: 'quantidade', nome: 'Quantidade', padrao: true },
                    { id: 'unidade', nome: 'Unidade', padrao: true },
                    { id: 'estoque_minimo', nome: 'Estoque Mínimo', padrao: true },
                    { id: 'fornecedor', nome: 'Fornecedor', padrao: false },
                    { id: 'ultima_compra', nome: 'Última Compra', padrao: false }
                ];
                break;
            case 'producao':
                colunas = [
                    { id: 'id', nome: 'ID', padrao: true },
                    { id: 'propriedade', nome: 'Propriedade', padrao: true },
                    { id: 'cultura', nome: 'Cultura', padrao: true },
                    { id: 'area_plantada', nome: 'Área Plantada (ha)', padrao: true },
                    { id: 'data_plantio', nome: 'Data do Plantio', padrao: true },
                    { id: 'previsao_colheita', nome: 'Previsão de Colheita', padrao: true },
                    { id: 'produtividade_estimada', nome: 'Produtividade Estimada', padrao: true },
                    { id: 'status', nome: 'Status', padrao: true }
                ];
                break;
            case 'financeiro':
                colunas = [
                    { id: 'id', nome: 'ID', padrao: true },
                    { id: 'data', nome: 'Data', padrao: true },
                    { id: 'tipo', nome: 'Tipo', padrao: true },
                    { id: 'categoria', nome: 'Categoria', padrao: true },
                    { id: 'descricao', nome: 'Descrição', padrao: true },
                    { id: 'valor', nome: 'Valor', padrao: true },
                    { id: 'forma_pagamento', nome: 'Forma de Pagamento', padrao: false },
                    { id: 'responsavel', nome: 'Responsável', padrao: false }
                ];
                break;
            default:
                colunas = [];
        }
        
        // Renderizar checkboxes de colunas
        let html = '';
        if (colunas.length > 0) {
            colunas.forEach(function(coluna) {
                const checked = coluna.padrao ? 'checked' : '';
                html += `
                <div class="form-check">
                    <input class="form-check-input coluna-checkbox" type="checkbox" 
                           value="${coluna.id}" id="col_${coluna.id}" ${checked}>
                    <label class="form-check-label" for="col_${coluna.id}">
                        ${coluna.nome}
                    </label>
                </div>
                `;
            });
        } else {
            html = '<div class="text-muted">Selecione um tipo de relatório para ver as colunas disponíveis</div>';
        }
        
        $('#colunasRelatorio').html(html);
    });
    
    // Gerar relatório personalizado
    $('#btnGerarRelatorio').on('click', function() {
        const tipo = $('#tipoRelatorio').val();
        const dataInicio = $('#dataInicio').val();
        const dataFim = $('#dataFim').val();
        const ordenarPor = $('#ordenarPor').val();
        
        // Coletar colunas selecionadas
        const colunasSelecionadas = [];
        $('.coluna-checkbox:checked').each(function() {
            colunasSelecionadas.push($(this).val());
        });
        
        if (tipo === '') {
            alert('Selecione um tipo de relatório');
            return;
        }
        
        if (colunasSelecionadas.length === 0) {
            alert('Selecione pelo menos uma coluna para o relatório');
            return;
        }
        
        // Construir URL com parâmetros
        const params = new URLSearchParams();
        params.append('tipo', tipo);
        params.append('data_inicio', dataInicio);
        params.append('data_fim', dataFim);
        params.append('ordenar_por', ordenarPor);
        params.append('colunas', colunasSelecionadas.join(','));
        
        // Redirecionar para a página de visualização do relatório
        window.location.href = `<?php echo SITE_URL; ?>/relatorios/visualizar?${params.toString()}`;
        
        // Fechar o modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalRelatorioPersonalizado'));
        modal.hide();
    });
    
    // Inicializar o seletor de tipo de relatório para carregar as colunas iniciais
    if ($('#tipoRelatorio').val() !== '') {
        $('#tipoRelatorio').trigger('change');
    }
});

// Função para exportar para Excel
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, {sheet: 'Relatório'});
    
    // Gerar nome do arquivo com data atual
    const today = new Date();
    const dateStr = today.toISOString().split('T')[0];
    const finalFilename = `${filename}_${dateStr}.xlsx`;
    
    // Baixar o arquivo
    XLSX.writeFile(wb, finalFilename);
}
</script>

<?php
// Incluir rodapé
require_once __DIR__ . '/../includes/footer.php';
?>
