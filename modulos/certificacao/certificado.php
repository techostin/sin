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
    // Gerar apenas o HTML do certificado, sem header/menu/footer
    $html = $certificado->gerarHtml($dados);
    
    // Output apenas o HTML do certificado para impressão/PDF
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Conformidade Orgânica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            @page { margin: 1cm; }
            body { margin: 0; padding: 0; }
        }
        .certificado-container {
            max-width: 800px;
            margin: 2cm auto;
            padding: 2cm;
            border: 2px solid #198754;
            background: #fff;
            font-family: 'Times New Roman', serif;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print text-center mb-3">
        <a href="#" onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="bi bi-printer"></i> Imprimir / Salvar como PDF
        </a>
        <a href="<?php echo SITE_URL; ?>/certificacoes" class="btn btn-secondary btn-sm ms-2">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
    <?php echo $html; ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
<?php
    exit;
}

$result = $certificado->gerarPdf($dados);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
header('Content-Length: ' . strlen($result['content']));
echo $result['content'];
