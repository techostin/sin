<?php
/**
 * Relatório: Associados Ativos por Município
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor do sistema OU membro da Diretoria (Presidente, Vice-Presidente,
// Diretor, Secretário, Tesoureiro e comissões/conselhos) da associação.
if (!$user->hasPermission(NIVEL_GESTOR) && !$user->isMembroDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

$db = Database::getInstance()->getConnection();

// Filtros
$filtro_estado = $_GET['estado'] ?? '23'; // RS por padrão
$filtro_mesorregiao = $_GET['mesorregiao'] ?? '';
$filtro_microrregiao = $_GET['microrregiao'] ?? '';

// Buscar mesorregiões
$mesorregioes = $db->query("SELECT id, nome FROM mesorregioes_rs ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Buscar microrregiões se mesorregiao selecionada
$microrregioes = [];
if ($filtro_mesorregiao) {
    $stmt = $db->prepare("SELECT id, nome FROM microrregioes_rs WHERE mesorregiao_id = ? ORDER BY nome");
    $stmt->execute([$filtro_mesorregiao]);
    $microrregioes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Query principal
$sql = "
    SELECT 
        c.nome as cidade,
        c.ibge,
        c.microrregiao_id,
        mi.nome as microrregiao,
        mr.nome as mesorregiao,
        COUNT(a.id) as total_associados,
        COUNT(CASE WHEN a.status = 'ativo' AND a.status_associado = 'Ativo' THEN 1 END) as ativos,
        COUNT(CASE WHEN a.status = 'ativo' AND a.status_associado = 'Inadimplente' THEN 1 END) as inadimplentes,
        COUNT(CASE WHEN a.status = 'ativo' AND a.status_associado = 'Afastado' THEN 1 END) as afastados
    FROM associados a
    JOIN cidades c ON c.id = a.cidade
    LEFT JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
    LEFT JOIN mesorregioes_rs mr ON mi.mesorregiao_id = mr.id
    WHERE a.estado = ?
      AND a.status = 'ativo'
      AND a.status_associado NOT IN ('Desligado', 'Falecido')
";
$params = [$filtro_estado];

if ($filtro_mesorregiao) {
    $sql .= " AND mr.id = ?";
    $params[] = $filtro_mesorregiao;
}
if ($filtro_microrregiao) {
    $sql .= " AND mi.id = ?";
    $params[] = $filtro_microrregiao;
}

$sql .= " GROUP BY c.id, c.nome, c.ibge, c.microrregiao_id, mi.nome, mr.nome ORDER BY mr.nome, mi.nome, c.nome";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totais
$total_geral = array_sum(array_column($dados, 'total_associados'));
$total_ativos = array_sum(array_column($dados, 'ativos'));
$total_inadimplentes = array_sum(array_column($dados, 'inadimplentes'));
$total_afastados = array_sum(array_column($dados, 'afastados'));

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="associados_ativos_municipio_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mesorregião', 'Microrregião', 'Município', 'IBGE', 'Total Associados', 'Ativos', 'Inadimplentes', 'Afastados']);
    foreach ($dados as $row) {
        fputcsv($output, [$row['mesorregiao'], $row['microrregiao'], $row['cidade'], $row['ibge'], $row['total_associados'], $row['ativos'], $row['inadimplentes'], $row['afastados']]);
    }
    fclose($output);
    exit;
}

$page_title = "Associados Ativos por Município";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-geo-alt text-primary me-2"></i>Associados Ativos por Município</h1>
            <span class="text-muted">Filtro por mesorregião e microrregião (RS)</span>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=csv<?php echo $filtro_mesorregiao ? '&mesorregiao=' . $filtro_mesorregiao : ''; ?><?php echo $filtro_microrregiao ? '&microrregiao=' . $filtro_microrregiao : ''; ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Exportar CSV</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Mesorregião</label>
                    <select name="mesorregiao" class="form-select" id="filtroMesorregiao">
                        <option value="">Todas</option>
                        <?php foreach ($mesorregioes as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $filtro_mesorregiao == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Microrregião</label>
                    <select name="microrregiao" class="form-select" id="filtroMicrorregiao" <?php echo !$filtro_mesorregiao ? 'disabled' : ''; ?>>
                        <option value="">Todas</option>
                        <?php foreach ($microrregioes as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $filtro_microrregiao == $m['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?php echo SITE_URL; ?>/relatorios_diretoria/ativos_municipio" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Associados</h6>
                    <h3 class="mb-0"><?php echo number_format($total_geral); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Ativos</h6>
                    <h3 class="mb-0 text-success"><?php echo number_format($total_ativos); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Inadimplentes</h6>
                    <h3 class="mb-0 text-danger"><?php echo number_format($total_inadimplentes); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Afastados</h6>
                    <h3 class="mb-0 text-warning"><?php echo number_format($total_afastados); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaMunicipios">
                    <thead class="table-light">
                        <tr>
                            <th>Mesorregião</th>
                            <th>Microrregião</th>
                            <th>Município</th>
                            <th>IBGE</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Ativos</th>
                            <th class="text-center">Inadimplentes</th>
                            <th class="text-center">Afastados</th>
                            <th class="text-center">% Inadimplência</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mesoAtual = '';
                        foreach ($dados as $row):
                            if ($row['mesorregiao'] !== $mesoAtual) {
                                $mesoAtual = $row['mesorregiao'];
                                echo '<tr class="table-secondary"><td colspan="9"><strong>' . htmlspecialchars($mesoAtual) . '</strong></td></tr>';
                            }
                            $pctInad = $row['ativos'] > 0 ? round(($row['inadimplentes'] / $row['ativos']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($row['microrregiao']); ?></td>
                            <td><?php echo htmlspecialchars($row['cidade']); ?></td>
                            <td><?php echo $row['ibge']; ?></td>
                            <td class="text-center"><?php echo $row['total_associados']; ?></td>
                            <td class="text-center"><?php echo $row['ativos']; ?></td>
                            <td class="text-center text-danger"><?php echo $row['inadimplentes']; ?></td>
                            <td class="text-center text-warning"><?php echo $row['afastados']; ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $pctInad > 20 ? 'bg-danger' : ($pctInad > 10 ? 'bg-warning' : 'bg-success'); ?>">
                                    <?php echo $pctInad; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="4">TOTAL GERAL</td>
                            <td class="text-center"><?php echo $total_geral; ?></td>
                            <td class="text-center"><?php echo $total_ativos; ?></td>
                            <td class="text-center text-danger"><?php echo $total_inadimplentes; ?></td>
                            <td class="text-center text-warning"><?php echo $total_afastados; ?></td>
                            <td class="text-center">
                                <?php echo $total_ativos > 0 ? round(($total_inadimplentes / $total_ativos) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelaMunicipios').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "order": [[0, 'asc'], [1, 'asc']]
    });

    // Atualizar microrregiões ao mudar mesorregião
    $('#filtroMesorregiao').change(function() {
        var mesoId = $(this).val();
        var $micro = $('#filtroMicrorregiao');
        $micro.prop('disabled', !mesoId);
        if (!mesoId) {
            $micro.html('<option value="">Todas</option>');
            return;
        }
        $micro.html('<option value="">Carregando...</option>');
        $.get('<?php echo SITE_URL; ?>/api/regionalizacao/microrregioes.php', { mesorregiao_id: mesoId }, function(data) {
            var html = '<option value="">Todas</option>';
            if (data.sucesso && data.dados) {
                data.dados.forEach(function(m) {
                    html += '<option value="' + m.id + '">' + m.nome + '</option>';
                });
            }
            $micro.html(html);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>