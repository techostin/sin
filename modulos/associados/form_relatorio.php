<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_nivel'])) {
    // Se não estiver logado, redireciona para o login
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Verifica se o usuário tem permissão
if (!in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)) {
    die('Você não tem permissão para acessar esta página.');
}

// Define o título da página
$page_title = "Gerar Relatório de Associados";

// Inclui os arquivos necessários
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="assoc-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/associados-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<div class="assoc-hero mb-3">
    <h1 class="mb-1"><i class="bi bi-file-earmark-pdf me-2" style="color:var(--am-coral);"></i>Gerar Relatório de Associados</h1>
    <p class="mb-0">Configure os dados que aparecerão no cabeçalho e na assinatura do relatório.</p>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="assoc-card">
        <div class="card-head"><i class="bi bi-sliders2 me-2" style="color:var(--am-primary);"></i>Opções do Relatório</div>
        <div class="p-4">
            <form action="<?php echo SITE_URL; ?>/api/relatorios/relatorio_associados.php" method="post" target="_blank">
                <div class="mb-3">
                    <label for="nome_associacao" class="form-label">Nome da Associação</label>
                    <input type="text" class="form-control" id="nome_associacao" name="nome_associacao" required 
                           placeholder="Digite o nome da associação que será exibido no relatório">
                    <div class="form-text mt-1">Este nome aparecerá no cabeçalho do relatório.</div>
                </div>
                
                <div class="mb-3">
                    <label for="nome_responsavel" class="form-label">Nome do Responsável</label>
                    <input type="text" class="form-control" id="nome_responsavel" name="nome_responsavel" 
                           placeholder="Digite o nome do responsável para assinatura">
                    <div class="form-text mt-1">Opcional - Nome que aparecerá no campo de assinatura.</div>
                </div>
                
                <div class="d-flex justify-content-start gap-2 mt-4 flex-wrap">
                    <button type="submit" name="formato" value="pdf" class="btn btn-danger" style="width: 150px;"><i class="bi bi-file-earmark-pdf me-1"></i> Gerar PDF</button>
                    <button type="submit" name="formato" value="excel" class="btn btn-success" style="width: 150px;"><i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel</button>
                </div>
            </form>
        </div>
        </div>
    </div>
</div>

</div> <!-- /page-bg -->
</div> <!-- /assoc-modern -->

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
