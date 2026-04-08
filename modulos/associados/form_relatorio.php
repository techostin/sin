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

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-pdf"></i> Gerar Relatório de Associados</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo SITE_URL; ?>/api/relatorios/relatorio_associados.php" method="post" target="_blank">
                        <div class="mb-3">
                            <label for="nome_associacao" class="form-label">Nome da Associação</label>
                            <input type="text" class="form-control" id="nome_associacao" name="nome_associacao" required 
                                   placeholder="Digite o nome da associação que será exibido no relatório">
                            <div class="form-text">Este nome aparecerá no cabeçalho do relatório.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nome_responsavel" class="form-label">Nome do Responsável</label>
                            <input type="text" class="form-control" id="nome_responsavel" name="nome_responsavel" 
                                   placeholder="Digite o nome do responsável para assinatura">
                            <div class="form-text">Opcional - Nome que aparecerá no campo de assinatura.</div>
                        </div>
                        
                        <div class="d-flex justify-content-start gap-2 mt-4">
                            <button type="submit" name="formato" value="pdf" class="btn btn-outline-danger" style="width: 150px;">Gerar PDF</button>
                            <button type="submit" name="formato" value="excel" class="btn btn-success" style="width: 150px;">Exportar Excel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
