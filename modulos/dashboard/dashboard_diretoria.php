<?php
$page_title = "Dashboard da Diretoria";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Caixa.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';

$user = new User();
if (!$user->podeAcessarDiretoria()) {
    echo "<div class='alert alert-danger m-4'>Acesso restrito: esta área é exclusiva para o Administrador e membros do quadro diretivo da Associação (Presidente, Vice-Presidente, Diretor, Secretário, Tesoureiro, Conselho Fiscal e comissões).</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$KPI = [
    'associados_ativos' => 0, 'associados_total' => 0, 'inadimplentes' => 0,
    'inadimplencia_valor' => 0, 'aniversariantes' => [], 'vencimentos' => [],
    'caixa' => ['entradas' => 0, 'saidas' => 0, 'saldo' => 0],
];

$mesAtual = (int) date('m');
$anoAtual = (int) date('Y');
$hoje = date('Y-m-d');
$fimMes = date('Y-m-t');

try {
    $q = function (string $sql, array $p = []) use ($db) {
        $s = $db->prepare($sql);
        $s->execute($p);
        return (int) $s->fetchColumn();
    };

    $KPI['associados_total'] = $q("SELECT COUNT(*) FROM associados");
    $KPI['associados_ativos'] = $q("SELECT COUNT(*) FROM associados WHERE status = 'ativo' AND (status_associado IS NULL OR status_associado NOT IN ('Desligado','Falecido'))");

    // Inadimplentes do mês (pendente/atrasado com vencimento vencido)
    $plML = new Mensalidade();
    $inadimplentes = $plML->getInadimplentes(null, $mesAtual, $anoAtual);
    $KPI['inadimplentes'] = count($inadimplentes);
    $KPI['inadimplencia_valor'] = array_sum(array_map(fn($i) => (float) $i['valor_cobrado'], $inadimplentes));

    // Aniversariantes do mês
    $stmt = $db->prepare(
        "SELECT CONCAT(nome, ' ', sobrenome) AS nome, data_nascimento, telefone
         FROM associados
         WHERE status = 'ativo'
           AND data_nascimento IS NOT NULL
           AND data_nascimento <> '0000-00-00'
           AND MONTH(data_nascimento) = :mes
         ORDER BY DAY(data_nascimento) ASC"
    );
    $stmt->execute([':mes' => $mesAtual]);
    $KPI['aniversariantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Próximos vencimentos (mensalidades pendentes/atrasadas até fim do mês)
    $stmt = $db->prepare(
        "SELECT m.id as mensalidade_id, CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome,
                m.data_vencimento, m.valor_cobrado, m.status_mensalidade,
                ac.sigla AS associacao_sigla
         FROM mensalidades m
         INNER JOIN associados a ON a.id = m.associado_id
         LEFT JOIN associacoes ac ON ac.id = m.associacao_id
         WHERE m.status_mensalidade IN ('pendente','atrasado')
           AND m.data_vencimento BETWEEN :hoje AND :fim
         ORDER BY m.data_vencimento ASC
         LIMIT 10"
    );
    $stmt->execute([':hoje' => $hoje, ':fim' => $fimMes]);
    $KPI['vencimentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Saldo de caixa (geral)
    $KPI['caixa'] = (new Caixa())->getSaldo();
} catch (PDOException $e) {
    error_log("Erro no dashboard da diretoria: " . $e->getMessage());
}
?>
<div class="container-fluid px-4 py-3">
    <h1 class="h3 mb-1"><i class="bi bi-people-fill text-success me-2"></i>Dashboard da Diretoria</h1>
    <p class="text-muted mb-4">Visão gerencial da associação: associados, inadimplência, caixa e agenda.</p>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Associados Ativos', $KPI['associados_ativos'], 'bi-people-fill', 'bg-success', '/associados'],
            ['Total Cadastrados', $KPI['associados_total'], 'bi-person-lines-fill', 'bg-primary', '/associados'],
            ['Inadimplentes no Mês', $KPI['inadimplentes'], 'bi-exclamation-triangle', 'bg-danger', '/caixa/cobranca'],
            ['Valor em Atraso', 'R$ ' . number_format($KPI['inadimplencia_valor'], 2, ',', '.'), 'bi-cash-coin', 'bg-warning text-dark', '/caixa/cobranca'],
            ['Saldo em Caixa', 'R$ ' . number_format($KPI['caixa']['saldo'], 2, ',', '.'), 'bi-wallet2', ($KPI['caixa']['saldo'] >= 0 ? 'bg-info' : 'bg-danger'), '/caixa'],
            ['Aniversariantes', count($KPI['aniversariantes']), 'bi-gift', 'bg-secondary', '#aniversariantes'],
        ];
        foreach ($cards as $c):
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?php echo $c[4] === '#aniversariantes' ? '#' : SITE_URL . $c[4]; ?>" class="text-decoration-none <?php echo $c[4] === '#aniversariantes' ? 'anchor-scroll' : ''; ?>">
                <div class="card <?php echo $c[3]; ?> text-white h-100 shadow-sm">
                    <div class="card-body text-center py-3">
                        <i class="bi <?php echo $c[2]; ?> display-6"></i>
                        <div class="fs-5 fw-bold"><?php echo $c[1]; ?></div>
                        <div class="small"><?php echo $c[0]; ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white"><i class="bi bi-calendar-event me-1"></i>Próximos Vencimentos</div>
                <div class="card-body table-responsive">
                    <?php if (empty($KPI['vencimentos'])): ?>
                        <span class="text-muted small">Nenhum vencimento em aberto até o fim do mês.</span>
                    <?php else: ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Associado</th><th>Vencimento</th><th>Valor</th><th>Situação</th></tr></thead>
                            <tbody>
                            <?php foreach ($KPI['vencimentos'] as $v): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($v['associado_nome']); ?> <?php echo $v['associacao_sigla'] ? '<small class="text-muted">(' . htmlspecialchars($v['associacao_sigla']) . ')</small>' : ''; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($v['data_vencimento'])); ?></td>
                                    <td>R$ <?php echo number_format((float) $v['valor_cobrado'], 2, ',', '.'); ?></td>
                                    <td><span class="badge bg-<?php echo $v['status_mensalidade'] === 'pendente' ? 'warning text-dark' : 'danger'; ?>"><?php echo ucfirst(htmlspecialchars($v['status_mensalidade'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6" id="aniversariantes">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark"><i class="bi bi-gift me-1"></i>Aniversariantes do Mês</div>
                <div class="card-body table-responsive">
                    <?php if (empty($KPI['aniversariantes'])): ?>
                        <span class="text-muted small">Nenhum aniversariante este mês.</span>
                    <?php else: ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Nome</th><th>Dia</th><th>Telefone</th></tr></thead>
                            <tbody>
                            <?php foreach ($KPI['aniversariantes'] as $an): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($an['nome']); ?></td>
                                    <td><?php echo date('d/m', strtotime($an['data_nascimento'])); ?></td>
                                    <td><?php echo htmlspecialchars($an['telefone'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>