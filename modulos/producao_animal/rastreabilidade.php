<?php
// Página Pública de Rastreabilidade da Produção Animal (Portaria MAPA 52/2021) — Não requer login
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

$lote_id = isset($_GET['lote']) ? (int)$_GET['lote'] : 0;
$uuid = isset($_GET['uuid']) ? trim($_GET['uuid']) : '';
$db = Database::getInstance()->getConnection();

$lote = null;
$animais = [];
$alimentacao = [];
$sanidade = [];
$reproducao = [];

if ($lote_id > 0 || $uuid !== '') {
    if ($uuid !== '') {
        // busca via código de rastreabilidade (formato SINGER-lote-XXXXXX ou uuid)
        $stmt = $db->prepare("SELECT al.id, al.associado_id, al.nome_lote, al.especie, al.categoria, al.quantidade, al.descricao, al.criado_em,
                                     a.nome AS agricultor_nome, a.sobrenome AS agricultor_sobrenome, a.registro_cnpo
                              FROM animal_lotes al
                              LEFT JOIN associados a ON al.associado_id = a.id
                              WHERE CONCAT('AL-' , LPAD(al.id, 6, '0')) = ? AND al.excluido = 0");
        $stmt->execute([$uuid]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$lote && $lote_id > 0) {
        $stmt = $db->prepare("SELECT al.id, al.associado_id, al.nome_lote, al.especie, al.categoria, al.quantidade, al.descricao, al.criado_em,
                                     a.nome AS agricultor_nome, a.sobrenome AS agricultor_sobrenome, a.registro_cnpo
                              FROM animal_lotes al
                              LEFT JOIN associados a ON al.associado_id = a.id
                              WHERE al.id = ? AND al.excluido = 0");
        $stmt->execute([$lote_id]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if ($lote) {
    // Origem dos animais do lote (recepção / notas produtor / fornecedor)
    try {
        $stmt = $db->prepare("SELECT nome_animal, raca, identificacao, sexo, data_nascimento, origem_conversao, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem
                              FROM animais WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0)");
        $stmt->execute([$lote['id']]);
        $animais = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* tabela pode não existir */ }

    // Registros de alimentação do lote (origem/fornecedor da alimentação)
    try {
        $stmt = $db->prepare("SELECT data_alimentacao, tipo_alimento, produto_utilizado, quantidade_kg, origem, observacoes
                              FROM controle_alimentacao_animal
                              WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0)
                              ORDER BY data_alimentacao DESC");
        $stmt->execute([$lote['id']]);
        $alimentacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* tabela pode não existir */ }

    // Registros de sanidade do lote
    try {
        $stmt = $db->prepare("SELECT data_tratamento, tipo_tratamento, produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, observacoes
                              FROM controle_sanidade_animal
                              WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0)
                              ORDER BY data_tratamento DESC");
        $stmt->execute([$lote['id']]);
        $sanidade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* tabela pode não existir */ }

    // Eventos reprodutivos do lote
    try {
        $stmt = $db->prepare("SELECT grupo_reprodutivo, tipo_evento, data_evento, previsao_parto, nascidos_vivos, natimortos, observacoes
                              FROM controle_reproducao_animal
                              WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0)
                              ORDER BY data_evento DESC");
        $stmt->execute([$lote['id']]);
        $reproducao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* tabela pode não existir */ }
}

$tipoEntradaLabels = [
    'NASCIDO_PROPRIEDADE' => 'Nascido na propriedade',
    'COMPRADO' => 'Comprado (terceiro)',
    'TRANSFERIDO' => 'Transferido',
    'RECEBIDO' => 'Recebido de fornecedor',
];
$origemLabels = [
    'ORGANICO' => 'Conversão orgânica certificada',
    'CONVENCIONAL' => 'Convencional em conversão',
    'DESCONHECIDO' => 'Origem não informada',
];
$tipoAlimentoLabels = [
    'FORRAGEM' => 'Forragem',
    'CONCENTRADO' => 'Concentrado',
    'SUPLEMENTO_MINERAL' => 'Suplemento mineral',
    'ADITIVO_TECNOLOGICO' => 'Aditivo tecnológico',
    'LEITE_COLOSTRO' => 'Leite/Colostro',
    'APICULTURA' => 'Apicultura',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreabilidade da Produção Animal - SINPORG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/agricultura-familiar.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="text-success fw-bold">SINPORG</h1>
            <p class="text-muted">Rastreabilidade da Produção Animal Orgânica</p>
        </div>

        <?php if ($lote): ?>
            <div class="card shadow border-0 overflow-hidden mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-egg-fill"></i> Lote de Animais Origem Garantida</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <i class="bi bi-grid-3x3-gap-fill text-success h1 d-block mb-3"></i>
                            <span class="badge bg-success">Orgânico</span>
                        </div>
                        <div class="col-md-9">
                            <h2 class="h4"><?php echo htmlspecialchars($lote['nome_lote']); ?></h2>
                            <p class="text-muted mb-3">Código: <strong>AL-<?php echo str_pad($lote['id'], 6, '0', STR_PAD_LEFT); ?></strong> | Cadastro: <?php echo date('d/m/Y', strtotime($lote['criado_em'])); ?></p>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Produtor</label>
                                    <strong><?php echo htmlspecialchars(trim(($lote['agricultor_nome'] ?? '') . ' ' . ($lote['agricultor_sobrenome'] ?? '')) ?: '—'); ?></strong>
                                    <?php if (!empty($lote['registro_cnpo'])): ?>
                                        <small class="text-muted d-block">Registro CNPO: <?php echo htmlspecialchars($lote['registro_cnpo']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Espécie / Categoria</label>
                                    <strong><?php echo htmlspecialchars($lote['especie'] ?? '—'); ?></strong>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($lote['categoria'] ?? ''); ?></small>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Quantidade de Animais</label>
                                    <strong><?php echo number_format((int)$lote['quantidade'], 0, ',', '.'); ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <label class="text-muted small d-block">Status</label>
                                    <span class="badge bg-success">Em conformidade orgânica</span>
                                </div>
                            </div>
                            <?php if (!empty($lote['descricao'])): ?>
                                <hr>
                                <div class="bg-light p-3 rounded">
                                    <h6 class="fw-bold"><i class="bi bi-journal-text"></i> Descrição do Lote</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($lote['descricao'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <hr>
                            <div class="bg-light p-3 rounded">
                                <h6 class="fw-bold"><i class="bi bi-shield-check"></i> Garantia de Origem</h6>
                                <p class="mb-0">Animais criados seguindo a Portaria MAPA 52/2021, com origem, alimentação e sanidade rastreadas pelo sistema participativo de garantia.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-3">
                    <small class="text-muted">Informações extraídas do SINPORG em <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>

            <?php if ($animais): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-egg-fill"></i> Origem dos Animais (Recepção)</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Animal</th><th>Raça</th><th>Identif.</th><th>Entrada</th><th>Fornecedor/Origem</th><th>Data Entrada</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($animais as $an): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($an['nome_animal']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($an['raca'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($an['identificacao'] ?? '—'); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($tipoEntradaLabels[$an['tipo_entrada']] ?? $an['tipo_entrada'] ?? '—'); ?></span></td>
                                    <td><?php echo htmlspecialchars($an['fornecedor_origem'] ?? '—'); ?></td>
                                    <td><?php echo $an['data_entrada'] ? date('d/m/Y', strtotime($an['data_entrada'])) : '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <p class="small text-muted mb-0">Cadeia de rastreabilidade: recepção do animal (fornecedor / nota de compra) → lote de criação → produto final animal, registrado conforme a Portaria MAPA 52/2021.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($alimentacao): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-basket"></i> Alimentação (Registros e Origem)</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Tipo</th><th>Produto</th><th>Qtde (kg)</th><th>Origem/Fornecedor</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alimentacao as $al): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($al['data_alimentacao'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tipoAlimentoLabels[$al['tipo_alimento']] ?? $al['tipo_alimento']); ?></span></td>
                                    <td><?php echo htmlspecialchars($al['produto_utilizado'] ?? '—'); ?></td>
                                    <td><?php echo $al['quantidade_kg'] !== null ? number_format((float)$al['quantidade_kg'], 2, ',', '.') : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($al['origem'] ?? '—'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($sanidade): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-heart-pulse"></i> Sanidade</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Tratamento</th><th>Produto</th><th>Motivo</th><th>Responsável</th><th>Lib. Orgânica</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sanidade as $san): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($san['data_tratamento'])); ?></td>
                                    <td><?php echo htmlspecialchars($san['tipo_tratamento'] ?? '—'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($san['produto_utilizado'] ?? '—'); ?>
                                        <?php if (!empty($san['periodo_carencia_horas'])): ?>
                                            <small class="text-muted d-block">Carência: <?php echo htmlspecialchars($san['periodo_carencia_horas']); ?>h</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($san['motivo'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($san['responsavel'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($san['data_liberacao_organica'])): ?>
                                            <span class="badge bg-success"><?php echo date('d/m/Y', strtotime($san['data_liberacao_organica'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($reproducao): ?>
            <div class="card shadow border-0 mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-stars"></i> Reprodução</strong></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Data</th><th>Evento</th><th>Grupo</th><th>Prev. Parto</th><th>Nascidos Vivos</th><th>Natimortos</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reproducao as $rep): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($rep['data_evento'])); ?></td>
                                    <td><?php echo htmlspecialchars($rep['tipo_evento'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($rep['grupo_reprodutivo'] ?? '—'); ?></td>
                                    <td><?php echo $rep['previsao_parto'] ? date('d/m/Y', strtotime($rep['previsao_parto'])) : '—'; ?></td>
                                    <td><?php echo $rep['nascidos_vivos'] !== null ? (int)$rep['nascidos_vivos'] : '—'; ?></td>
                                    <td><?php echo $rep['natimortos'] !== null ? (int)$rep['natimortos'] : '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php elseif ($lote_id <= 0 && $uuid === ''): ?>
            <div class="card shadow border-0 mb-4 mx-auto" style="max-width:600px;">
                <div class="card-header bg-white"><strong><i class="bi bi-qr-code-scan"></i> Consultar Rastreabilidade da Produção Animal</strong></div>
                <div class="card-body p-4">
                    <p class="text-muted">Informe o código do lote de animais impresso na etiqueta (ex.: AL-000001), ou escaneie o QR Code, para consultar a rastreabilidade completa do lote.</p>
                    <form method="get" action="<?php echo SITE_URL; ?>/producao-animal/rastreabilidade" class="d-flex gap-2">
                        <input type="text" name="uuid" class="form-control" placeholder="Ex.: AL-000001" required autofocus>
                        <button type="submit" class="btn btn-success text-nowrap"><i class="bi bi-search"></i> Consultar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle h3 d-block"></i>
                Lote de animais não localizado. Por favor, verifique o código QR impresso na etiqueta.
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