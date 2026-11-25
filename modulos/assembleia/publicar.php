<?php
/**
 * Publicar Assembleia (ativar votação)
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

if ($assembleia['status'] !== 'rascunho') {
    header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId);
    exit;
}

// Verificar se tem pautas
$stmt = $db->prepare("SELECT COUNT(*) FROM assembleia_pautas WHERE assembleia_id = ?");
$stmt->execute([$assembleiaId]);
$qtdPautas = $stmt->fetchColumn();

if ($qtdPautas === 0) {
    $_SESSION['error_message'] = 'Não é possível publicar assembleia sem pautas.';
    header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId . '/pautas');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $_SESSION['error_message'] = 'Erro de segurança: token inválido.';
    } else {
        $stmt = $db->prepare("UPDATE assembleias SET status = 'ativa', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$assembleiaId]);
        $_SESSION['success_message'] = 'Assembleia publicada! Votação aberta até ' . date('d/m/Y H:i', strtotime($assembleia['data_fim']));
        header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId);
        exit;
    }
}

$page_title = "Publicar Assembleia";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar Publicação</h4>
                </div>
                <div class="card-body">
                    <p>Tem certeza que deseja <strong>publicar</strong> a assembleia abaixo?</p>
                    
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5><?php echo htmlspecialchars($assembleia['titulo']); ?></h5>
                            <p class="text-muted mb-1"><i class="bi bi-calendar me-1"></i> Início: <?php echo date('d/m/Y H:i', strtotime($assembleia['data_inicio'])); ?></p>
                            <p class="text-muted mb-1"><i class="bi bi-calendar-x me-1"></i> Fim: <?php echo date('d/m/Y H:i', strtotime($assembleia['data_fim'])); ?></p>
                            <p class="text-muted mb-0"><i class="bi bi-list-task me-1"></i> <?php echo $qtdPautas; ?> pauta(s) cadastrada(s)</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Ao publicar:</strong>
                        <ul class="mb-0 mt-2">
                            <li>A votação ficará disponível para associados aptos</li>
                            <li>Não será mais possível editar pautas</li>
                            <li>A assembleia só poderá ser encerrada (não cancelada)</li>
                        </ul>
                    </div>
                    
                    <form method="post">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning"><i class="bi bi-send me-1"></i> Sim, Publicar Agora</button>
                            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>