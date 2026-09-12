<?php
// Gera certificado em PDF usando dados da certificação e da certificadora vinculada ao gestor logado
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Certificadora.php';

use Mpdf\Mpdf;
if (session_status() === PHP_SESSION_NONE) session_start();
$user = new User();
if (!$user->isLoggedIn()) { header('HTTP/1.1 401 Unauthorized'); echo 'Acesso negado'; exit; }
$db = Database::getInstance()->getConnection();

// Parâmetros: certificacao_id ou associado_id
$certId = $_GET['certificacao_id'] ?? null;
$associadoId = $_GET['associado_id'] ?? null;

// Buscar dados básicos da certificação (se informado)
$cert = null;
if ($certId && is_numeric($certId)) {
    $stmt = $db->prepare("SELECT * FROM certificacao_participativa WHERE id = ? LIMIT 1");
    $stmt->execute([$certId]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Determinar certificadora vinculada ao gestor atual
$currentUser = $user->getCurrentUser();
$gestorId = $_SESSION['user_id'];
$certModel = new Certificadora();
$certificadora = $certModel->findByGestor($gestorId);

// Se não houver certificadora vinculada ao gestor, tentar buscar por organismo_certificador do certificado
if (!$certificadora && !empty($cert['organismo_certificador'])) {
    $certificadora = $certModel->findByNamePartial($cert['organismo_certificador']);
}

// Caminhos de imagens
$logoPath = null;
if (!empty($certificadora['logo_filename'])) {
    $logoPath = (isset($certificadora['logo_url']) && filter_var($certificadora['logo_url'], FILTER_VALIDATE_URL)) ? $certificadora['logo_url'] : SITE_URL . '/uploads/certificadoras/' . $certificadora['logo_filename'];
} elseif (!empty($certificadora['logo_url'])) {
    $logoPath = $certificadora['logo_url'];
}

// Selo orgânico (esperado em uploads/seals/selo_organico_br.png) - usar URL pública
$seloPath = SITE_URL . '/uploads/seals/selo_organico_br.png';

// Montar HTML do certificado (simples, responsivo ao A4)
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: helvetica, Arial, sans-serif; }
.container { position: relative; width: 100%; height: 100%; }
.header-left { position: absolute; left: 8mm; top: 8mm; }
.header-right { position: absolute; right: 8mm; top: 8mm; }
.logo { max-height: 60px; }
.content { margin-top: 30mm; text-align: center; }
.title { font-size: 22pt; font-weight: bold; margin-bottom: 8pt; }
.subtitle { font-size: 12pt; margin-bottom: 18pt; }
.small { font-size: 10pt; }
</style>
</head>
<body>
<div class="container">
    <?php if ($logoPath): ?>
        <div class="header-left"><img src="<?php echo htmlspecialchars($logoPath); ?>" class="logo"></div>
    <?php endif; ?>
    <?php if (file_exists(__DIR__ . '/../uploads/seals/selo_organico_br.png')): ?>
        <div class="header-right"><img src="<?php echo $seloPath; ?>" class="logo"></div>
    <?php endif; ?>

    <div class="content">
        <div class="title">Certificado de Conformidade - Certificação Participativa</div>
        <div class="subtitle">Emitido conforme Portaria MAPA 52/2021</div>

        <p class="small">Este documento certifica que</p>
        <h2><?php echo htmlspecialchars($cert['nome_grupo'] ?? ($currentUser['nome'] ?? 'NOME')); ?></h2>

        <p class="small">Recebeu a certificação no âmbito do sistema participativo de garantia.</p>

        <p class="small">Número do certificado: <strong><?php echo htmlspecialchars($cert['numero_certificado'] ?? '-'); ?></strong></p>
        <p class="small">Data de emissão: <strong><?php echo !empty($cert['data_emissao']) ? date('d/m/Y', strtotime($cert['data_emissao'])) : '-'; ?></strong></p>

        <div style="margin-top:40mm; text-align:left; padding-left:20mm; padding-right:20mm;">
            <div style="float:left; width:50%; text-align:left;">
                <p class="small">Assinatura da certificadora</p>
            </div>
            <div style="float:right; width:40%; text-align:right;">
                <p class="small">Selo Orgânico - SisOrg</p>
            </div>
            <div style="clear:both"></div>
        </div>
    </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// Gerar PDF com mPDF
try {
    $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4','margin_top'=>15,'margin_bottom'=>15,'margin_left'=>10,'margin_right'=>10]);
    $mpdf->WriteHTML($html);
    $filename = 'certificado_' . ($cert['id'] ?? $associadoId ?? 'doc') . '_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($filename, 'I');
} catch (Exception $e) {
    error_log('Erro ao gerar PDF: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Erro ao gerar PDF.';
}

?>