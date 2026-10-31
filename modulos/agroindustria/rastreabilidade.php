<?php
// Página Pública de Rastreabilidade do Produto Final (RF12) — Não requer login
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

$lote = isset($_GET['lote']) ? trim($_GET['lote']) : '';
$db = Database::getInstance()->getConnection();

$op = null;
$itens = [];
$receita = null;
$ingredientes = [];
if ($lote !== '') {
    $stmt = $db->prepare(
        "SELECT o.*, r.nome_produto_final, r.descricao AS receita_descricao,
                a.nome AS associado_nome, a.sobrenome AS associado_sobrenome, a.registro_cnpo
         FROM ordens_producao o
         JOIN receitas_agroindustria r ON o.receita_id = r.id
         LEFT JOIN associados a ON o.associado_id = a.id
         WHERE o.lote_processado_final = ? AND o.excluido = 0"
    );
    $stmt->execute([$lote]);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($op) {
        $stmt = $db->prepare(
            "SELECT ri.*, lp.nome_lote, lp.uuid AS lote_uuid, lp.data_producao, p.nome AS nome_produto,
                    ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo,
                    f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj
             FROM rastreabilidade_insumos_processo ri
             LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id
             LEFT JOIN produtos p ON ri.produto_id = p.id
             LEFT JOIN insumos ins ON ri.insumo_id = ins.id
             LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id
             WHERE ri.ordem_producao_id = ?"
        );
        $stmt->execute([(int)$op['id']]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM receitas_agroindustria WHERE id = ?");
        $stmt->execute([(int)$op['receita_id']]);
        $receita = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM ingredientes_receita WHERE receita_id = ?");
        $stmt->execute([(int)$op['receita_id']]);
        $ingredientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$seloLabels = [
    'PRODUTO_ORGANICO_BRASIL' => 'Produto Orgânico Brasil (≥95%)',
    'INGREDIENTES_ORGANICOS' => 'Ingredientes Orgânicos (70–94%)',
    'SEM_SELO' => 'Sem selo (<70%)',
];
$statusBadge = [
    'APROVADO' => ['bg-success', 'Aprovado'],
    'ALERTA_FRAUDE' => ['bg-danger', 'Alerta de Fraude'],
    'EM_ANALISE' => ['bg-warning text-dark', 'Em análise'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreabilidade do Produto Final - SINPORG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/agricultura-familiar.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="text-success fw-bold">SINPORG</h1>
            <p class="text-muted">Rastreabilidade do Produto Final Agroindustrial</p>
        </div>

        <?php if ($op): ?>
            <div class="card shadow border-0 overflow-hidden mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-patch-check-fill"></i> Produto Final <?php echo htmlspecialchars($lote); ?></h5>
                </div>
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <i class="bi bi-box-seam-fill text-success h1 d-block mb-3"></i>
                            <span class="badge <?php echo $op['status_auditoria'] === 'APROVADO' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $statusBadge[$op['status_auditoria']][1] ?? $op['status_auditoria']; ?>
                            </span>
                        </div>
                        <div class="col-md-9">
                            <h2 class="h4"><?php echo htmlspecialchars($op['nome_produto_final']); ?></h2>
                            <p class="text-muted mb-3">Lote: <strong><?php echo htmlspecialchars($op['lote_processado_final']); ?></strong> | Processado em: <?php echo date('d/m/Y H:i', strtotime($op['data_processamento'])); ?></p>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Produtor / Agroindústria</label>
                                    <strong><?php echo htmlspecialchars(trim(($op['associado_nome'] ?? '') . ' ' . ($op['associado_sobrenome'] ?? '')) ?: '—'); ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Percentual Orgânico</label>
                                    <strong><?php echo $op['percentual_organico'] !== null ? number_format((float)$op['percentual_organico'], 1, ',', '.') . '%' : '—'; ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Selo Autorizado</label>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($seloLabels[$op['selo_autorizado']] ?? $op['selo_autorizado'] ?? '—'); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Quantidade Final</label>
                                    <strong><?php echo number_format((float)$op['quantidade_final_obtida'], 2, ',', '.') . ' ' . htmlspecialchars($op['unidade_medida']); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($receita && $ingredientes): ?>
                <div class="card shadow border-0 mb-4">
                    <div class="card-header bg-white"><strong><i class="bi bi-journal-text"></i> Receita Utilizada</strong></div>
                    <div class="card-body p-4">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($receita['nome_produto_final']); ?></h6>
                        <?php if (!empty($receita['descricao'])): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($receita['descricao'])); ?></p><?php endif; ?>
                        <ul class="list-group">
                            <?php foreach ($ingredientes as $ing): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($ing['nome_ingrediente']); ?></span>
                                    <span>
                                        <span class="badge <?php echo $ing['eh_organico'] ? 'bg-success' : 'bg-secondary'; ?> me-1"><?php echo $ing['eh_organico'] ? 'Orgânico' : 'Convencional'; ?></span>
                                        <?php if (!empty($ing['quantidade'])): ?>
                                            <?php echo number_format((float)$ing['quantidade'], 3, ',', '.') . ' ' . htmlspecialchars($ing['unidade_medida'] ?? 'kg'); ?>
                                        <?php else: ?>
                                            <?php echo number_format((float)$ing['proporcao_porcentagem'], 1, ',', '.'); ?> g
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow border-0">
                <div class="card-header bg-white"><strong><i class="bi bi-tree-fill"></i> Origem dos Insumos Utilizados (cadeia de rastreabilidade)</strong></div>
                <div class="card-body p-4">
                    <?php if ($itens): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr><th>Origem (Insumo)</th><th>Fornecedor</th><th>NF</th><th>Qtde.</th><th>Un.</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): ?>
                                        <tr>
                                            <td>
                                                <?php if ($item['nome_insumo']): ?>
                                                    <strong><?php echo htmlspecialchars($item['nome_insumo']); ?></strong>
                                                    <?php if ($item['lote_insumo']): ?>
                                                        <span class="text-muted d-block small">Lote do insumo: <?php echo htmlspecialchars($item['lote_insumo']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($item['nome_lote'] ?? '—'); ?></strong>
                                                    <span class="text-muted d-block small"><?php echo htmlspecialchars($item['nome_produto'] ?? '—'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($item['fornecedor']): ?>
                                                    <?php echo htmlspecialchars($item['fornecedor']); ?>
                                                    <?php if ($item['fornecedor_cnpj']): ?>
                                                        <small class="text-muted d-block">CNPJ: <?php echo htmlspecialchars($item['fornecedor_cnpj']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $item['numero_nf'] ? htmlspecialchars($item['numero_nf']) : '—'; ?></td>
                                            <td><?php echo number_format((float)$item['quantidade_utilizada'], 2, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($item['unidade_medida']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Nenhum insumo vinculado a esta ordem.</p>
                    <?php endif; ?>
                    <hr>
                    <p class="small text-muted mb-0">Cadeia de rastreabilidade: origem do insumo (nota fiscal do fornecedor) → insumos utilizados → lote do produto final. Produto processado a partir de matéria-prima de agricultura familiar, seguindo as normas da Portaria MAPA 52/2021, com balanço de massas auditável.</p>
                </div>
            </div>
        <?php elseif ($lote === ''): ?>
            <div class="card shadow border-0 mb-4 mx-auto" style="max-width:600px;">
                <div class="card-header bg-white"><strong><i class="bi bi-qr-code-scan"></i> Consultar Rastreabilidade do Produto Final</strong></div>
                <div class="card-body p-4">
                    <p class="text-muted">Informe o código do lote impresso na etiqueta da embalagem para consultar a rastreabilidade completa (origem da matéria-prima, nota fiscal, fornecedor e cadeia produtiva).</p>
                    <form method="get" action="<?php echo SITE_URL; ?>/agroindustria/rastreabilidade" class="d-flex gap-2">
                        <input type="text" name="lote" class="form-control" placeholder="Ex.: LOTE-AGR-0001" required autofocus>
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
                    <iframe src="<?php echo SITE_URL; ?>/doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" class="w-100" style="height:80vh;border:0;" title="Guia do Produtor Orgânico"></iframe>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-success btn-sm" href="<?php echo SITE_URL; ?>/doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf" target="_blank" rel="noopener"><i class="bi bi-download"></i> Abrir em nova aba</a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
