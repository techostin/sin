<?php
/**
 * Encerrar Assembleia
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getInstance()->getConnection();

// Verificar permissão: gestor (user_nivel <= 2) OU criador da assembleia
$isGestor = isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] <= 2;
$isCriador = false;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/assembleia');
    exit;
}
$assembleiaId = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM assembleias WHERE id = ?");
$stmt->execute([$assembleiaId]);
$assembleia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assembleia) {
    header('Location: ' . SITE_URL . '/assembleia');
    exit;
}

// Verificar se tem cargo de gestão na associação
    require_once __DIR__ . '/../../classes/User.php';
    $user = new User();
    $isGestorAssociacao = !empty($assembleia['associacao_id'])
        && $user->isMembroDiretoria((int) $assembleia['associacao_id']);

    $podeGerenciar = $isGestor || $isCriador || $isGestorAssociacao;

    if (!$podeGerenciar) {
        header('Location: ' . SITE_URL . '/login');
        exit;
    }

if ($assembleia['status'] !== 'ativa') {
    header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $_SESSION['error_message'] = 'Erro de segurança: token inválido.';
    } else {
        $stmt = $db->prepare("UPDATE assembleias SET status = 'encerrada', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$assembleiaId]);
        $_SESSION['success_message'] = 'Assembleia encerrada com sucesso!';
        header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId . '/resultado');
        exit;
    }
}

$page_title = "Encerrar Assembleia";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="bi bi-stop-circle me-2"></i>Confirmar Encerramento</h4>
                </div>
                <div class="card-body">
                    <p>Tem certeza que deseja <strong>encerrar definitivamente</strong> a assembleia abaixo?</p>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5><?php echo htmlspecialchars($assembleia['titulo']); ?></h5>
                            <p class="text-muted mb-0">Votação aberta desde <?php echo date('d/m/Y H:i', strtotime($assembleia['data_inicio'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Ação irreversível:</strong>
                        <ul class="mb-0 mt-2">
                            <li>A votação será finalizada imediatamente</li>
                            <li>Não será mais possível votar</li>
                            <li>Resultados ficarão disponíveis para consulta</li>
                        </ul>
                    </div>
                    
                    <form method="post">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger"><i class="bi bi-stop-circle me-1"></i> Sim, Encerrar Agora</button>
                            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>