<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ApiAuth.php';
require_once __DIR__ . '/../../classes/Carteirinha.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Mpdf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
// PDF com dado pessoal do associado (foto/CNPO): exige login e devolve
// 403 em vez de conteúdo para quem não é Admin/Gestor nem o próprio dono.
ApiAuth::exigirLogin(false);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    ApiAuth::negar(400, 'Associado não informado.', false);
}
$id = (int) $_GET['id'];

// Anti-IDOR: o id aqui é o próprio associado_id da carteirinha;
// Admin/Gestor (nível <= 2) acessam qualquer um, nível 3 só o seu.
ApiAuth::exigirProprioAssociado($id, false);

$carteirinhaService = new Carteirinha();
$carteirinha = $carteirinhaService->getByAssociado($id);
if (!$carteirinha) {
    die('Carteirinha não encontrada. Gere a carteirinha pela ficha do associado.');
}

$validade = $carteirinhaService->validaToken($carteirinha['token']);
$dados = $validade['carteirinha'];

$urlCarteirinha = SITE_URL . '/carteirinha/' . $carteirinha['token'];
$qrBase64 = '';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=0&data=' . urlencode($urlCarteirinha);
$ctx = @file_get_contents($qrUrl);
if ($ctx !== false && substr($ctx, 0, 4) === "\x89PNG") {
    $qrBase64 = base64_encode($ctx);
}

$qrHtml = '';
if ($qrBase64 !== '') {
    $qrHtml = '<img src="data:image/png;base64,' . $qrBase64 . '" style="width:120px;height:120px;">';
} else {
    $qrHtml = '<div style="font-size:9px;color:#555;max-width:130px;">Sem rede para gerar QR. Token: ' . htmlspecialchars($carteirinha['token']) . '</div>';
}

$situacaoValida = $validade['valida'] ? 'Ativo' : 'Inválida/Vencida';
$situacaoCor = $validade['valida'] ? '#5F8F5F' : '#C26E2E';

$fotoHtml = '';
$fotoBase64 = '';
$fotoMime = 'image/jpeg';
if (!empty($dados['foto_url']) && file_exists(__DIR__ . '/../../uploads/associados/' . $dados['foto_url'])) {
    $fotoBase64 = base64_encode(file_get_contents(__DIR__ . '/../../uploads/associados/' . $dados['foto_url']));
    $fotoInfo = @getimagesize(__DIR__ . '/../../uploads/associados/' . $dados['foto_url']);
    if (is_array($fotoInfo) && !empty($fotoInfo['mime'])) {
        $fotoMime = $fotoInfo['mime'];
    }
}
if ($fotoBase64 !== '') {
    $fotoHtml = '<img src="data:' . $fotoMime . ';base64,' . $fotoBase64 . '" style="height:150px;width:120px;object-fit:cover;border-radius:8px;border:2px solid #C8CDD3;">';
} else {
    $fotoHtml = '<div style="height:150px;width:120px;background:#E4E6E9;border:1px dashed #B9BDC2;border-radius:8px;display:inline-block;line-height:150px;color:#7A828A;font-size:11px;font-weight:600;">SEM FOTO</div>';
}

$html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
</head>
<body style="font-family:Helvetica,Arial,sans-serif;">
<div style="border:2px solid #B9BDC2;border-radius:14px;padding:0;width:340px;margin:0 auto;overflow:hidden;background:linear-gradient(160deg,#F6F7F8 0%,#E4E6E9 55%,#D2D5D9 100%);">
    <table cellpadding="0" cellspacing="0" style="width:100%;">
        <tr>
            <td style="height:4px;background:#B8D4B8;border-right:1px solid #FFF;"></td>
            <td style="height:4px;background:#EBD79A;border-right:1px solid #FFF;"></td>
            <td style="height:4px;background:#EDB87D;"></td>
        </tr>
    </table>
    <div style="background:linear-gradient(135deg,#F5D08C,#EDB46B);color:#2F353B;padding:12px 16px;text-align:center;border-bottom:2px solid #E0C27B;">
        <strong style="font-size:16px;">SINPORG</strong><br>
        <span style="font-size:11px;">Carteirinha Digital de Associado</span>
    </div>
    <div style="padding:16px;">
        <table cellpadding="4" cellspacing="0" style="font-size:12px;width:100%;">
            <tr>
                <td style="vertical-align:middle;text-align:center;width:130px;">' . $fotoHtml . '</td>
                <td style="vertical-align:middle;">
                    <div style="font-size:13px;"><strong>' . htmlspecialchars($dados['associado_nome'] ?? '') . '</strong></div>
                    <div style="color:#7A828A;margin-top:2px;">Tipo: ' . htmlspecialchars(ucfirst($dados['tipo_associado'] ?? 'Efetivo')) . '</div>
                    <div style="color:#7A828A;">CNPO: ' . htmlspecialchars($dados['registro_cnpo'] ?? '-') . '</div>
                    <div style="color:#7A828A;">Nascimento: ' . (!empty($dados['data_nascimento']) && $dados['data_nascimento'] !== '0000-00-00' ? date('d/m/Y', strtotime($dados['data_nascimento'])) : '-') . '</div>
                </td>
            </tr>
        </table>
        <div style="margin-top:14px;text-align:center;">' . $qrHtml . '</div>
        <div style="margin-top:10px;font-size:11px;text-align:center;">
            <span style="color:' . $situacaoCor . ';"><strong>' . $situacaoValida . '</strong></span><br>
            <span style="color:#7A828A;">Validade: ' . date('d/m/Y', strtotime($carteirinha['validade'])) . '</span><br>
            <span style="color:#9AA1A8;font-size:9px;">Verifique a autenticidade pelo QR Code.</span>
        </div>
    </div>
</div>
</body>
</html>';

$mpdf = new Mpdf([
    'format' => [100, 160],
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_top' => 0,
    'margin_bottom' => 0,
]);
$mpdf->SetTitle('Carteirinha - ' . ($dados['associado_nome'] ?? ''));
$mpdf->WriteHTML($html);
$mpdf->Output('carteirinha_' . $id . '.pdf', 'I');