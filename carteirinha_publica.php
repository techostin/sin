<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Carteirinha.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    http_response_code(404);
    die('Carteirinha não informada.');
}

$carteirinhaService = new Carteirinha();
$validacao = $carteirinhaService->validaToken($token);

if (!$validacao['valida'] || empty($validacao['carteirinha'])) {
    http_response_code(404);
}
$dados = $validacao['carteirinha'] ?? null;
$motivo = $validacao['motivo'] ?? 'Carteirinha não encontrada.';

$fotoUrl = '';
if ($dados && !empty($dados['foto_url']) && file_exists(__DIR__ . '/uploads/associados/' . $dados['foto_url'])) {
    $fotoUrl = SITE_URL . '/uploads/associados/' . htmlspecialchars($dados['foto_url']);
}

$statusCor = $validacao['valida'] ? '#198754' : '#dc3545';
$statusTexto = $validacao['valida'] ? 'CARTEIRINHA VÁLIDA' : 'CARTEIRINHA ' . strtoupper($motivo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carteirinha Digital — <?php echo SITE_TITLE; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background: #eef2f5; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card-sinporg { width: 340px; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.15); }
    .card-top { background:linear-gradient(135deg,#198754,#146c43); color:#fff; padding:14px 16px; }
    .card-body-act { padding:16px; }
</style>
</head>
<body>
<div class="container py-4 text-center">
<?php if (!$dados): ?>
    <div class="alert alert-danger mx-auto" style="max-width:400px;">
        <i class="bi bi-x-octagon-fill me-2"></i><?php echo htmlspecialchars($motivo); ?>
    </div>
<?php else: ?>
<div class="card-sinporg mx-auto">
    <div class="card-top d-flex justify-content-between align-items-center">
        <div style="line-height:1.15;">
            <strong style="font-size:1.05rem;">SINPORG</strong><br>
            <span style="font-size:0.72rem;opacity:.92;">Carteirinha Digital de Associado</span>
        </div>
        <i class="bi bi-qr-code fs-3"></i>
    </div>
    <div class="card-body-act">
        <div class="d-flex gap-3 align-items-center">
            <?php if ($fotoUrl): ?>
                <img src="<?php echo $fotoUrl; ?>" class="rounded" style="width:120px;height:150px;object-fit:cover;border:1px solid #ddd;" alt="Foto">
            <?php else: ?>
                <div class="rounded d-flex align-items-center justify-content-center bg-secondary-subtle" style="width:120px;height:150px;">
                    <i class="bi bi-person-fill fs-1 text-secondary"></i>
                </div>
            <?php endif; ?>
            <div class="text-start small">
                <div class="fw-bold fs-6"><?php echo htmlspecialchars($dados['associado_nome'] ?? ''); ?></div>
                <div class="text-secondary mt-1">Tipo: <strong><?php echo htmlspecialchars(ucfirst($dados['tipo_associado'] ?? 'Efetivo')); ?></strong></div>
                <div class="text-secondary">CNPO: <?php echo htmlspecialchars($dados['registro_cnpo'] ?? '-'); ?></div>
                <div class="text-secondary">Nasc.: <?php echo (!empty($dados['data_nascimento']) && $dados['data_nascimento'] !== '0000-00-00') ? date('d/m/Y', strtotime($dados['data_nascimento'])) : '-'; ?></div>
            </div>
        </div>
        <div class="text-center my-3">
            <div id="qrcode"></div>
        </div>
        <div class="text-center small">
            <span class="badge rounded-pill px-3 py-2" style="background:<?php echo $statusCor; ?>;color:#fff;"><?php echo htmlspecialchars($statusTexto); ?></span>
            <div class="text-secondary mt-2">Validade: <?php echo date('d/m/Y', strtotime($dados['validade'])); ?></div>
            <div class="text-secondary" style="font-size:0.72rem;">Apresente este QR Code no check-in de eventos e atividades da associação.</div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    try {
        var qrUrl = window.location.origin + window.location.pathname;
        new QRCode(document.getElementById("qrcode"), {
            text: qrUrl,
            width: 130,
            height: 130,
            colorDark: "#14532d",
            colorLight: "#ffffff"
        });
    } catch (e) {
        document.getElementById("qrcode").innerHTML = e.message;
    }
</script>
<?php endif; ?>
    <p class="text-muted small mt-3"><?php echo SITE_TITLE; ?> — validação pública de carteirinha.</p>
</div>
</body>
</html>