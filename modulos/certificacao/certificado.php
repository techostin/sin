<?php
/**
 * Visualização e download do Certificado de Conformidade Orgânica
 *
 * Uso:
 *   GET  /certificacao/{id}/certificado           → PDF
 *   GET  /certificacao/{id}/certificado?format=html → Preview HTML
 *   POST /certificacao/certificado-preview        → Preview a partir do formulário
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/CertificadoOrganico.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo 'Acesso não autorizado.';
    exit;
}

$certificado = new CertificadoOrganico();
$format = $_GET['format'] ?? 'pdf';
$isPreview = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isPreview) {
    if (!$user->hasPermission(NIVEL_GESTOR)) {
        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    $input = $_POST;
    if (empty($input) && !empty(file_get_contents('php://input'))) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $dados = $certificado->buscarPorFormulario($input);
} else {
    $id = $_GET['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        http_response_code(400);
        echo 'ID da certificação é obrigatório.';
        exit;
    }

    $dados = $certificado->buscarPorCertificacaoId((int) $id);

    if (!$dados) {
        http_response_code(404);
        echo 'Certificação não encontrada.';
        exit;
    }

    if ($_SESSION['user_nivel'] == NIVEL_USUARIO) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT associado_id FROM certificacao_participativa WHERE id = ?');
        $stmt->execute([(int) $id]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert || $cert['associado_id'] != $user->getAssociadoId()) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }
}

if (!$dados) {
    http_response_code(400);
    echo 'Dados insuficientes para gerar o certificado. Selecione um associado válido.';
    exit;
}

if ($format === 'html') {
    $page_title = 'Certificado de Conformidade Orgânica';
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/menu.php';

    $html = $certificado->gerarHtml($dados);
    $pdfUrl = $isPreview
        ? '#'
        : SITE_URL . '/certificacao/' . (int) ($_GET['id'] ?? 0) . '/certificado';
    ?>
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h1 class="h4 mb-1">Certificado de Conformidade Orgânica</h1>
            <p class="text-muted mb-0">Pré-visualização — <?php echo htmlspecialchars($dados['produtor']['nome']); ?></p>
        </div>
        <div class="btn-group">
            <?php if (!$isPreview): ?>
            <a href="<?php echo $pdfUrl; ?>" class="btn btn-danger btn-sm" target="_blank">
                <i class="bi bi-file-earmark-pdf"></i> Baixar PDF
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <?php if ($isPreview): ?>
    <div class="alert alert-info py-2">
        <i class="bi bi-info-circle"></i>
        Pré-visualização com dados do formulário. Salve a certificação para gerar o PDF definitivo.
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4 bg-white">
            <?php echo $html; ?>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$result = $certificado->gerarPdf($dados);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
header('Content-Length: ' . strlen($result['content']));
echo $result['content'];
