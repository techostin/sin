<?php
// Página Pública de Rastreabilidade (Não requer login)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$uuid = $_GET['uuid'] ?? null;
$db = Database::getInstance()->getConnection();

$lote = null;
if ($uuid) {
    // Buscar lote e informações de origem
    $stmt = $db->prepare("SELECT l.*, p.produto as produto_nome, lp.lavoura as lavoura_nome, pr.propriedade as propriedade_nome, a.nome as agricultor_nome
                          FROM lotes_producao l
                          JOIN produtos p ON l.id_produto = p.id
                          JOIN lavouras lp ON l.id_lavoura = lp.id
                          JOIN propriedades pr ON lp.propriedade_id = pr.id
                          JOIN associados a ON pr.associado_id = a.id
                          WHERE l.uuid = ?");
    $stmt->execute([$uuid]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreabilidade Orgânica - SINPORG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/agricultura-familiar.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="text-success fw-bold">SINPORG</h1>
            <p class="text-muted">Rastreabilidade da Agricultura Familiar Orgânica</p>
        </div>

        <?php if ($lote): ?>
            <div class="card shadow border-0 overflow-hidden">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0">Produto Original Garantido</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <i class="bi bi-patch-check-fill text-success h1 d-block mb-3"></i>
                            <span class="badge bg-success">Orgânico</span>
                        </div>
                        <div class="col-md-9">
                            <h2 class="h4"><?php echo $lote['produto_nome']; ?></h2>
                            <p class="text-muted mb-4">Lote: <?php echo $lote['lote']; ?> | Safra: <?php echo $lote['safra']; ?></p>
                            
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Agricultor</label>
                                    <strong><?php echo $lote['agricultor_nome']; ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Origem</label>
                                    <strong><?php echo $lote['propriedade_nome']; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="bg-light p-3 rounded">
                        <h6 class="fw-bold"><i class="bi bi-geo-alt"></i> Local de Produção</h6>
                        <p class="mb-0">Este produto foi cultivado seguindo as normas da Portaria MAPA 52/2021, com manejo orgânico certificado pelo sistema participativo de garantia.</p>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <small class="text-muted">Informações extraídas do SINPORG em <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle h3 d-block"></i>
                Lote não localizado. Por favor, verifique o código QR impresso na etiqueta.
            </div>
        <?php endif; ?>

        <div class="mt-5 text-center">
            <a href="https://www.gov.br/agricultura/pt-br/assuntos/produtos-organicos" class="text-decoration-none text-muted small">Saiba mais sobre a Certificação Orgânica</a>
        </div>
    </div>
</body>
</html>
