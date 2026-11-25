<?php
// Página Pública de Rastreabilidade (Não requer login) — Produção Vegetal
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$uuid = $_GET['uuid'] ?? null;
$db = Database::getInstance()->getConnection();

$lote = null;
$atividades = [];
$insumos = [];
if ($uuid) {
    // Buscar lote e informações de origem com o schema real
    $stmt = $db->prepare("SELECT l.id, l.uuid, l.nome_lote, l.data_producao, l.quantidade, l.unidade_medida, l.observacoes,
                                 p.nome AS produto_nome, p.tipo AS produto_tipo, p.data_colheita,
                                 lv.nome AS lavoura_nome, lv.tipo_cultura, lv.area AS lavoura_area,
                                 pr.nome AS propriedade_nome, pr.endereco, pr.bairro, pr.cidade, pr.estado, pr.cep,
                                 a.nome AS agricultor_nome, a.sobrenome AS agricultor_sobrenome, a.registro_cnpo
                          FROM lotes_producao l
                          LEFT JOIN produtos p ON l.produto_id = p.id
                          LEFT JOIN lavouras lv ON p.lavoura_id = lv.id
                          LEFT JOIN propriedades pr ON lv.propriedade_id = pr.id
                          LEFT JOIN associados a ON l.associado_id = a.id
                          WHERE l.uuid = ? AND l.excluido = 0");
    $stmt->execute([$uuid]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lote) {
        // Atividades vinculadas à lavoura do produto (histórico de manejo)
        try {
            $stmt = $db->prepare("SELECT ad.nome, ad.data_atividade, ad.tipo_atividade, ad.descricao, ad.hora_inicio, ad.hora_fim, ad.status
                                  FROM lotes_producao lp
                                  JOIN produtos p ON lp.produto_id = p.id
                                  JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id
                                  WHERE lp.uuid = ? AND ad.excluido = 0
                                  ORDER BY ad.data_atividade ASC, ad.hora_inicio ASC");
            $stmt->execute([$uuid]);
            $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* tabela pode não existir */ }

// Insumos utilizados no manejo (origem dos insumos)
        try {
            $stmt = $db->prepare("SELECT DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade
                                  FROM lotes_producao lp
                                  JOIN produtos p ON lp.produto_id = p.id
                                  JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id
                                  WHERE lp.uuid = ? AND ad.excluido = 0 AND ad.insumo_utilisado IS NOT NULL AND ad.insumo_utilisado != ''
                                  ORDER BY ad.data_atividade ASC");
            $stmt->execute([$uuid]);
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* tabela pode não existir */ }
    }
}
$statusBadgeAtividade = function ($s) {
    switch ($s) {
        case 'Pendente': return 'bg-warning text-dark';
        case 'Em andamento': return 'bg-info text-dark';
        case 'Concluído': return 'bg-success';
        default: return 'bg-secondary';
    }
};
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
            <p class="text-muted">Rastreabilidade da Produção Vegetal Orgânica</p>
        </div>

        <?php if ($lote): ?>
            <div class="card shadow border-0 overflow-hidden mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-patch-check-fill"></i> Produto Originado Garantido</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <i class="bi bi-box-seam-fill text-success h1 d-block mb-3"></i>
                            <span class="badge bg-success">Orgânico</span>
                        </div>
                        <div class="col-md-9">
                            <h2 class="h4"><?php echo htmlspecialchars($lote['produto_nome'] ?? '—'); ?></h2>
                            <p class="text-muted mb-3">Lote: <strong><?php echo htmlspecialchars($lote['nome_lote']); ?></strong> | Produção: <?php echo date('d/m/Y', strtotime($lote['data_producao'])); ?> | Quantidade: <?php echo number_format((float)$lote['quantidade'], 2, ',', '.') . ' ' . htmlspecialchars($lote['unidade_medida']); ?></p>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Agricultor / Produtor</label>
                                    <strong><?php echo htmlspecialchars(trim(($lote['agricultor_nome'] ?? '') . ' ' . ($lote['agricultor_sobrenome'] ?? '')) ?: '—'); ?></strong>
                                    <?php if (!empty($lote['registro_cnpo'])): ?>
                                        <small class="text-muted d-block">Registro CNPO: <?php echo htmlspecialchars($lote['registro_cnpo']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Tipo de Produto</label>
                                    <strong><?php echo htmlspecialchars($lote['produto_tipo'] ?? '—'); ?></strong>
                                    <?php if (!empty($lote['data_colheita'])): ?>
                                        <small class="text-muted d-block">Colheita: <?php echo date('d/m/Y', strtotime($lote['data_colheita'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <h6 class="fw-bold"><i class="bi bi-tree-fill"></i> Lavoura de Origem</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($lote['lavoura_nome'] ?? '—'); ?>
                                <?php if ($lote['tipo_cultura']): ?> <span class="text-muted">(<?php echo htmlspecialchars($lote['tipo_cultura']); ?>)</span><?php endif; ?>
                                <?php if ($lote['lavoura_area']): ?> <span class="text-muted">| Área: <?php echo htmlspecialchars($lote['lavoura_area']); ?></span><?php endif; ?>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <h6 class="fw-bold"><i class="bi bi-geo-alt"></i> Propriedade (Local de Produção)</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($lote['propriedade_nome'] ?? '—'); ?>
                                <?php if ($lote['endereco'] || $lote['cidade'] || $lote['estado']): ?>
                                    <span class="text-muted d-block"><?php echo trim(($lote['endereco'] ?? '') . ($lote['bairro'] ? ', ' . $lote['bairro'] : '') . ', ' . ($lote['cidade'] ?? '') . ' - ' . ($lote['estado'] ?? '') . ($lote['cep'] ? ' | CEP: ' . $lote['cep'] : '')); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php if (!empty($lote['observacoes'])): ?>
                        <hr>
                        <div class="bg-light p-3 rounded">
                            <h6 class="fw-bold"><i class="bi bi-journal-text"></i> Observações do Lote</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($lote['observacoes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <div class="bg-light p-3 rounded">
                        <h6 class="fw-bold"><i class="bi bi-shield-check"></i> Garantia de Origem</h6>
                        <p class="mb-0">Este produto foi cultivado seguindo as normas da Portaria MAPA 52/2021, com manejo orgânico certificado pelo sistema participativo de garantia. Toda a cadeia produtiva (insumos, atividades e origem) pode ser verificada abaixo.</p>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <small class="text-muted">Informações extraídas do SINPORG em <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>

            <?php if ($atividades): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-calendar-check"></i> Histórico de Atividades (Manejo)</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Hora</th><th>Atividade</th><th>Tipo</th><th>Status</th><th>Descrição</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($atividades as $at): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($at['data_atividade'])); ?></td>
                                    <td><?php echo htmlspecialchars(($at['hora_inicio'] ?? '') . ' - ' . ($at['hora_fim'] ?: 'Em andamento')); ?></td>
                                    <td><?php echo htmlspecialchars($at['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($at['tipo_atividade'] ?? '—'); ?></td>
                                    <td><span class="badge <?php echo $statusBadgeAtividade($at['status'] ?? ''); ?>"><?php echo htmlspecialchars($at['status'] ?? '—'); ?></span></td>
                                    <td><?php echo htmlspecialchars($at['descricao'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($insumos): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-droplet-half"></i> Insumos Utilizados</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Insumo</th><th>Data de Aplicação</th><th>Atividade</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($insumos as $in): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($in['insumo_utilisado']); ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($in['data_atividade'])); ?></td>
                                    <td><?php echo htmlspecialchars($in['tipo_atividade'] ?? '—'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <p class="small text-muted mb-0">Cadeia de rastreabilidade: origem da matéria-prima (lavoura) → insumos aplicados no manejo → lote do produto. Produto originado de agricultura familiar, seguindo as normas da Portaria MAPA 52/2021, com registro auditável.</p>
                </div>
            </div>
            <?php endif; ?>
        <?php elseif ($uuid === '' || $uuid === null): ?>
            <div class="card shadow border-0 mb-4 mx-auto" style="max-width:600px;">
                <div class="card-header bg-white"><strong><i class="bi bi-qr-code-scan"></i> Consultar Rastreabilidade</strong></div>
                <div class="card-body p-4">
                    <p class="text-muted">Informe o código do lote impresso na etiqueta (ou escaneie o QR Code) para consultar a rastreabilidade completa da produção vegetal orgânica.</p>
                    <form method="get" action="<?php echo SITE_URL; ?>/rastreabilidade" class="d-flex gap-2">
                        <input type="text" name="uuid" class="form-control" placeholder="Ex.: f41eb70e-d499-4967-9bf0-4c448dc1847e" required autofocus>
                        <button type="submit" class="btn btn-success text-nowrap"><i class="bi bi-search"></i> Consultar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle h3 d-block"></i>
                Lote não localizado. Por favor, verifique o código QR impresso na etiqueta.
            </div>
        <?php endif; ?>

        <div class="mt-5 text-center">
            <a href="#" class="text-decoration-none text-muted small" data-bs-toggle="modal" data-bs-target="#modalGuiaProdutor">Saiba mais sobre a Certificação Orgânica</a>
        </div>
    </div>

    <div class="modal fade" id="modalGuiaProdutor" tabindex="-1" aria-labelledby="modalGuiaProdutorLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGuiaProdutorLabel">Guia do Produtor Orgânico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe src="doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" class="w-100" style="height:80vh;border:0;" title="Guia do Produtor Orgânico"></iframe>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-success btn-sm" href="doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" target="_blank" rel="noopener"><i class="bi bi-download"></i> Abrir em nova aba</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>