<?php
/**
 * Resultado da Assembleia
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

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
$filtroPauta = isset($_GET['pauta']) && is_numeric($_GET['pauta']) ? (int) $_GET['pauta'] : null;

$stmt = $db->prepare("
    SELECT a.*, ac.nome as associacao_nome, ac.sigla as associacao_sigla
    FROM assembleias a
    LEFT JOIN associacoes ac ON ac.id = a.associacao_id
    WHERE a.id = ?
");
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

// Pautas
$pautasSql = "SELECT * FROM assembleia_pautas WHERE assembleia_id = ?";
$params = [$assembleiaId];
if ($filtroPauta) {
    $pautasSql .= " AND id = ?";
    $params[] = $filtroPauta;
}
$pautasSql .= " ORDER BY ordem";
$stmt = $db->prepare($pautasSql);
$stmt->execute($params);
$pautas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total de aptos
$sqlAptos = "SELECT COUNT(*) FROM associados a WHERE a.status = 'ativo' AND a.status_associado = 'Ativo'";
$paramsAptos = [];
if ($assembleia['associacao_id']) {
    $sqlAptos .= " AND EXISTS (SELECT 1 FROM associado_associacao aa WHERE aa.associado_id = a.id AND aa.associacao_id = ?)";
    $paramsAptos[] = $assembleia['associacao_id'];
}
$stmt = $db->prepare($sqlAptos);
$stmt->execute($paramsAptos);
$totalAptos = $stmt->fetchColumn();

// Votos por pauta
$resultados = [];
foreach ($pautas as $p) {
    $stmt = $db->prepare("
        SELECT voto, COUNT(*) as qtd
        FROM assembleia_votos
        WHERE assembleia_id = ? AND pauta_id = ?
        GROUP BY voto
    ");
    $stmt->execute([$assembleiaId, $p['id']]);
    $votos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalVotosPauta = array_sum(array_column($votos, 'qtd'));
    $detalhes = [];
    
    if ($p['tipo'] === 'eleicao' && $p['opcoes']) {
        $opcoes = json_decode($p['opcoes'], true);
        foreach ($opcoes as $op) {
            $votosOpcao = 0;
            foreach ($votos as $v) {
                $votoData = json_decode($v['voto'], true);
                if (isset($votoData['candidato']) && $votoData['candidato'] === $op['valor']) {
                    $votosOpcao = $v['qtd'];
                    break;
                }
            }
            $detalhes[] = [
                'label' => $op['label'],
                'valor' => $op['valor'],
                'qtd' => $votosOpcao,
                'pct' => $totalVotosPauta > 0 ? round(($votosOpcao / $totalVotosPauta) * 100, 1) : 0
            ];
        }
    } else {
        $mapa = ['sim' => 'SIM', 'nao' => 'NÃO', 'abstencao' => 'ABSTENÇÃO'];
        foreach ($mapa as $key => $label) {
            $qtd = 0;
            foreach ($votos as $v) {
                $votoData = json_decode($v['voto'], true);
                if (isset($votoData['opcao']) && $votoData['opcao'] === $key) {
                    $qtd = $v['qtd'];
                    break;
                }
            }
            $detalhes[] = [
                'label' => $label,
                'valor' => $key,
                'qtd' => $qtd,
                'pct' => $totalVotosPauta > 0 ? round(($qtd / $totalVotosPauta) * 100, 1) : 0
            ];
        }
    }
    
    $resultados[$p['id']] = [
        'pauta' => $p,
        'total_votos' => $totalVotosPauta,
        'detalhes' => $detalhes,
        'quorum_atingido' => $totalAptos > 0 ? (($totalVotosPauta / $totalAptos) * 100) >= $assembleia['quorum_minimo'] : false,
        'quorum_pct' => $totalAptos > 0 ? round(($totalVotosPauta / $totalAptos) * 100, 1) : 0
    ];
}

// Exportar CSV - DEVE VIR ANTES DE QUALQUER OUTPUT HTML
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resultado_assembleia_' . $assembleiaId . '_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Assembleia', $assembleia['titulo']]);
    fputcsv($output, ['Status', ucfirst($assembleia['status'])]);
    fputcsv($output, ['Período', date('d/m/Y H:i', strtotime($assembleia['data_inicio'])) . ' a ' . date('d/m/Y H:i', strtotime($assembleia['data_fim']))]);
    fputcsv($output, ['Quórum Mínimo', $assembleia['quorum_minimo'] . '%']);
    fputcsv($output, []);
    fputcsv($output, ['Pauta', 'Tipo', 'Opção', 'Votos', '%', 'Quórum Atingido']);
    foreach ($resultados as $r) {
        foreach ($r['detalhes'] as $d) {
            fputcsv($output, [
                $r['pauta']['titulo'],
                ucfirst($r['pauta']['tipo']),
                $d['label'],
                $d['qtd'],
                $d['pct'] . '%',
                $r['quorum_atingido'] ? 'SIM' : 'NÃO'
            ]);
        }
    }
    fclose($output);
    exit;
}

$page_title = "Resultado: " . htmlspecialchars($assembleia['titulo']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up text-success me-2"></i>Resultado da Assembleia</h1>
            <span class="text-muted"><?php echo htmlspecialchars($assembleia['titulo']); ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv&id=<?php echo $assembleiaId; ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i> Exportar CSV</a>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 text-primary"><?php echo $totalAptos; ?></div>
                    <div class="small text-muted">Associados Aptos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 text-info">
                        <?php 
                        $stmt = $db->prepare("SELECT COUNT(DISTINCT associado_id) FROM assembleia_votos WHERE assembleia_id = ?");
                        $stmt->execute([$assembleiaId]);
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                    <div class="small text-muted">Votantes Únicos</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 text-success"><?php echo count($pautas); ?></div>
                    <div class="small text-muted">Pautas</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 <?php echo $assembleia['status'] === 'ativa' ? 'text-warning' : 'text-secondary'; ?>">
                        <?php echo ucfirst($assembleia['status']); ?>
                    </div>
                    <div class="small text-muted">Status</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultados por Pauta -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-ballot me-2"></i>Resultados por Pauta</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pautas)): ?>
            <div class="text-center py-4 text-muted">Nenhuma pauta cadastrada.</div>
            <?php else: ?>
            
            <?php foreach ($resultados as $pautaId => $r): 
                $p = $r['pauta'];
                $quorumPct = $r['quorum_pct'];
                $quorumOk = $r['quorum_atingido'];
            ?>
            <div class="card mb-4 border-<?php echo $p['tipo']==='eleicao'?'info':'success'; ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <strong><?php echo $p['ordem']; ?>. <?php echo htmlspecialchars($p['titulo']); ?></strong>
                        <span class="badge bg-<?php echo $p['tipo']==='eleicao'?'info':('success'); ?> ms-2">
                            <?php echo ucfirst($p['tipo']); ?>
                        </span>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Quórum: <?php echo $quorumPct; ?>% (mín: <?php echo $assembleia['quorum_minimo']; ?>%)</div>
                        <span class="badge bg-<?php echo $quorumOk ? 'success' : 'danger'; ?> ms-2">
                            <?php echo $quorumOk ? 'ATINGIDO' : 'NÃO ATINGIDO'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center">
                                    <div class="h3 mb-0 text-primary"><?php echo $r['total_votos']; ?></div>
                                    <div class="small text-muted">Total de Votos</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0">
                                <div class="card-body text-center">
                                    <div class="h3 mb-0 text-info"><?php echo $quorumPct; ?>%</div>
                                    <div class="small text-muted">Participação</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de barras -->
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40%;">Opção</th>
                                    <th class="text-center" style="width:15%;">Votos</th>
                                    <th class="text-center" style="width:15%;">%</th>
                                    <th style="width:30%;">Visualização</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($r['detalhes'] as $d): 
                                    $cor = $d['valor'] === 'sim' ? 'success' : ($d['valor'] === 'nao' ? 'danger' : ($d['valor'] === 'abstencao' ? 'warning' : 'primary'));
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($d['label']); ?></strong></td>
                                    <td class="text-center"><?php echo $d['qtd']; ?></td>
                                    <td class="text-center"><?php echo $d['pct']; ?>%</td>
                                    <td>
                                        <div class="progress" style="height: 24px;">
                                            <div class="progress-bar bg-<?php echo $cor; ?>" role="progressbar" style="width: <?php echo $d['pct']; ?>%;" aria-valuenow="<?php echo $d['pct']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $d['pct']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL</td>
                                    <td class="text-center"><?php echo $r['total_votos']; ?></td>
                                    <td class="text-center">100%</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>