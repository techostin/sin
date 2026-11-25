<?php
/**
 * Relatório: Inadimplentes por Microrregião
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor do sistema OU membro da Diretoria (Presidente, Vice-Presidente,
// Diretor, Secretário, Tesoureiro e comissões/conselhos) da associação.
if (!$user->hasPermission(NIVEL_GESTOR) && !$user->isMembroDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();
$mensalidadeModel = new Mensalidade();

// Filtros
$filtro_mesorregiao = $_GET['mesorregiao'] ?? '';
$filtro_mes = $_GET['mes'] ?? (int) date('m');
$filtro_ano = $_GET['ano'] ?? (int) date('Y');

// Buscar mesorregiões
$mesorregioes = $db->query("SELECT id, nome FROM mesorregioes_rs ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Buscar inadimplentes usando o método da classe
$inadimplentes = $mensalidadeModel->getInadimplentes(null, $filtro_mes, $filtro_ano);

// Filtrar por mesorregião se selecionado (via join com cidades/microrregioes)
if ($filtro_mesorregiao) {
    $inadimplentes = array_filter($inadimplentes, function($item) use ($filtro_mesorregiao, $db) {
        $stmt = $db->prepare("
            SELECT mr.id FROM cidades c
            JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
            JOIN mesorregioes_rs mr ON mi.mesorregiao_id = mr.id
            WHERE c.id = ?
        ");
        $stmt->execute([$item['cidade']]);
        $mesoId = $stmt->fetchColumn();
        return $mesoId == $filtro_mesorregiao;
    });
    $inadimplentes = array_values($inadimplentes);
}

// Agrupar por microrregião
$agrupado = [];
foreach ($inadimplentes as $item) {
    $stmt = $db->prepare("
        SELECT mi.nome as microrregiao, mr.nome as mesorregiao
        FROM cidades c
        JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
        JOIN mesorregioes_rs mr ON mi.mesorregiao_id = mr.id
        WHERE c.id = ?
    ");
    $stmt->execute([$item['cidade']]);
    $local = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $chave = $local['mesorregiao'] . '|' . $local['microrregiao'];
    if (!isset($agrupado[$chave])) {
        $agrupado[$chave] = [
            'mesorregiao' => $local['mesorregiao'],
            'microrregiao' => $local['microrregiao'],
            'qtd' => 0,
            'valor' => 0,
            'associados' => []
        ];
    }
    $agrupado[$chave]['qtd']++;
    $agrupado[$chave]['valor'] += (float) $item['valor_cobrado'];
    $agrupado[$chave]['associados'][] = $item;
}

// Ordenar por mesorregião/microrregião
uksort($agrupado, function($a, $b) {
    return strcmp($a, $b);
});

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inadimplentes_microrregiao_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mesorregião', 'Microrregião', 'Qtd Inadimplentes', 'Valor Total', 'Associado', 'Cidade', 'Referência', 'Vencimento', 'Valor']);
    foreach ($agrupado as $grupo) {
        foreach ($grupo['associados'] as $assoc) {
            fputcsv($output, [
                $grupo['mesorregiao'],
                $grupo['microrregiao'],
                $grupo['qtd'],
                $grupo['valor'],
                $assoc['associado_nome'],
                $assoc['cidade'],
                str_pad((string)$assoc['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $assoc['ano_referencia'],
                date('d/m/Y', strtotime($assoc['data_vencimento'])),
                number_format((float)$assoc['valor_cobrado'], 2, ',', '.')
            ]);
        }
    }
    fclose($output);
    exit;
}

$page_title = "Inadimplentes por Microrregião";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Inadimplentes por Microrregião</h1>
            <span class="text-muted">Referência: <?php echo str_pad((string)$filtro_mes, 2, '0', STR_PAD_LEFT) . '/' . $filtro_ano; ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv<?php echo $filtro_mesorregiao ? '&mesorregiao=' . $filtro_mesorregiao : ''; ?>&mes=<?php echo $filtro_mes; ?>&ano=<?php echo $filtro_ano; ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Exportar CSV</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Mesorregião</label>
                    <select name="mesorregiao" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($mesorregioes as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $filtro_mesorregiao == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mês</label>
                    <select name="mes" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $filtro_mes == $m ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ano</label>
                    <select name="ano" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filtro_ano == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/inadimplentes_micro" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards Resumo -->
    <?php
    $total_qtd = array_sum(array_column($agrupado, 'qtd'));
    $total_valor = array_sum(array_column($agrupado, 'valor'));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Microrregiões com Inadimplência</h6>
                    <h3 class="mb-0"><?php echo count($agrupado); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Inadimplentes</h6>
                    <h3 class="mb-0 text-danger"><?php echo number_format($total_qtd); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Valor Total em Aberto</h6>
                    <h3 class="mb-0 text-danger">R$ <?php echo number_format($total_valor, 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela por Microrregião -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Resumo por Microrregião</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaMicro">
                    <thead class="table-light">
                        <tr>
                            <th>Mesorregião</th>
                            <th>Microrregião</th>
                            <th class="text-center">Qtd Inadimplentes</th>
                            <th class="text-center">Valor Total (R$)</th>
                            <th class="text-center">Média por Associado (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mesoAtual = '';
                        foreach ($agrupado as $chave => $grupo):
                            if ($grupo['mesorregiao'] !== $mesoAtual) {
                                $mesoAtual = $grupo['mesorregiao'];
                                echo '<tr class="table-secondary"><td colspan="5"><strong>' . htmlspecialchars($mesoAtual) . '</strong></td></tr>';
                            }
                        ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($grupo['microrregiao']); ?></td>
                            <td class="text-center"><?php echo $grupo['qtd']; ?></td>
                            <td class="text-center text-danger">R$ <?php echo number_format($grupo['valor'], 2, ',', '.'); ?></td>
                            <td class="text-center">R$ <?php echo number_format($grupo['qtd'] > 0 ? $grupo['valor'] / $grupo['qtd'] : 0, 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2">TOTAL GERAL</td>
                            <td class="text-center"><?php echo $total_qtd; ?></td>
                            <td class="text-center text-danger">R$ <?php echo number_format($total_valor, 2, ',', '.'); ?></td>
                            <td class="text-center">R$ <?php echo number_format($total_qtd > 0 ? $total_valor / $total_qtd : 0, 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Detalhamento por Associado (accordion) -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Detalhamento por Associado</h5>
        </div>
        <div class="card-body">
            <div class="accordion" id="accordionInadimplentes">
                <?php $idx = 0; foreach ($agrupado as $chave => $grupo): $idx++; ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $idx; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $idx; ?>" aria-expanded="false" aria-controls="collapse<?php echo $idx; ?>">
                            <div class="w-100 d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($grupo['mesorregiao']); ?></strong> / <?php echo htmlspecialchars($grupo['microrregiao']); ?>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?php echo $grupo['qtd']; ?> inadimplentes</span>
                                    <span class="badge bg-warning text-dark ms-1">R$ <?php echo number_format($grupo['valor'], 2, ',', '.'); ?></span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $idx; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $idx; ?>" data-bs-parent="#accordionInadimplentes">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Associado</th>
                                            <th>Cidade</th>
                                            <th>Telefone</th>
                                            <th>E-mail</th>
                                            <th class="text-center">Referência</th>
                                            <th class="text-center">Vencimento</th>
                                            <th class="text-center">Valor (R$)</th>
                                            <th class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grupo['associados'] as $assoc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assoc['associado_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($assoc['cidade']); ?></td>
                                            <td><?php echo htmlspecialchars($assoc['telefone'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($assoc['email'] ?? '—'); ?></td>
                                            <td class="text-center"><?php echo str_pad((string)$assoc['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $assoc['ano_referencia']; ?></td>
                                            <td class="text-center"><?php echo date('d/m/Y', strtotime($assoc['data_vencimento'])); ?></td>
                                            <td class="text-center text-danger fw-bold">R$ <?php echo number_format((float)$assoc['valor_cobrado'], 2, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <a href="<?php echo SITE_URL; ?>/mensalidade/<?php echo $assoc['mensalidade_id']; ?>/baixa" class="btn btn-sm btn-outline-primary" title="Dar Baixa"><i class="bi bi-check-circle"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($agrupado)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle-fill text-success fs-1"></i>
                    <p class="mt-2">Nenhum inadimplente encontrado para os filtros selecionados.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaMicro').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "order": [[0, 'asc'], [1, 'asc']]
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>