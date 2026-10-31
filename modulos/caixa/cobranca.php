<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/Mailer.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}
$podeGerenciarCobranca = $user->hasPermission(NIVEL_GESTOR);

$f = [
    'associacao_id' => (int) ($_GET['associacao_id'] ?? $_POST['associacao_id'] ?? 0),
    'ano' => (int) ($_GET['ano'] ?? $_POST['ano'] ?? 0),
    'mes' => (int) ($_GET['mes'] ?? $_POST['mes'] ?? 0),
];

$mensalidadeModel = new Mensalidade();
$inadimplentes = $mensalidadeModel->getInadimplentes(
    $f['associacao_id'] ?: null,
    $f['mes'] ?: null,
    $f['ano'] ?: null
);

function telefoneWhatsApp($telefone): string {
    $digitos = preg_replace('/\D+/', '', (string)$telefone);
    if (strlen($digitos) < 10) {
        return '';
    }
    $digitos = ltrim($digitos, '0');
    if (strlen($digitos) > 11) {
        $digitos = substr($digitos, -11);
    }
    return '55' . $digitos;
}

// Ação de envio de lembrete de cobrança (e-mail via Resend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'lembrete_cobranca' && $podeGerenciarCobranca) {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Sessão expirada. Reenvie o formulário.';
        $mensagem_tipo = 'warning';
    } else {
        $associadoId = (int) ($_POST['associado_id'] ?? 0);
        $itens = array_filter($inadimplentes, fn($i) => (int) $i['associado_id'] === $associadoId);
        if (empty($itens)) {
            $mensagem = 'Nenhuma cobrança em aberto encontrada para este associado.';
            $mensagem_tipo = 'warning';
        } else {
            $primeiro = $itens[0];
            $total = 0.0;
            foreach ($itens as $i) {
                $total += (float) $i['valor_cobrado'];
            }
            $nome = $primeiro['associado_nome'];
            $sigla = '';
            if (!empty($f['associacao_id'])) {
                foreach ($associacoes as $a) {
                    if ((int) $a['id'] === $f['associacao_id']) {
                        $sigla = !empty($a['sigla']) ? $a['sigla'] : $a['nome'];
                        break;
                    }
                }
            }

            if (empty($primeiro['email'])) {
                $mensagem = "{$nome} não possui e-mail cadastrado para receber lembrete.";
                $mensagem_tipo = 'warning';
            } else {
                $linhas = '';
                foreach ($itens as $i) {
                    $linhas .= '<tr>
                        <td style="padding:6px 10px;border-bottom:1px solid #e9ecef;">'
                        . str_pad((string)$i['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $i['ano_referencia'] . '</td>
                        <td style="padding:6px 10px;border-bottom:1px solid #e9ecef;text-align:right;">R$ '
                        . number_format((float)$i['valor_cobrado'], 2, ',', '.') . '</td>
                        <td style="padding:6px 10px;border-bottom:1px solid #e9ecef;text-align:center;">'
                        . date('d/m/Y', strtotime($i['data_vencimento'])) . '</td>
                    </tr>';
                }
                $html = '
                <p>Olá, <strong>' . htmlspecialchars($nome) . '</strong>!</p>
                <p>Identificamos mensalidades em aberto junto à ' . htmlspecialchars($sigla ?: 'associação') . '. '
                . 'Pedimos a gentileza de regularizar sua situação até o vencimento para evitar restrições.</p>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr>
                        <th style="padding:6px 10px;background:#f8f9fa;text-align:left;">Referência</th>
                        <th style="padding:6px 10px;background:#f8f9fa;text-align:right;">Valor</th>
                        <th style="padding:6px 10px;background:#f8f9fa;text-align:center;">Vencimento</th>
                    </tr>'
                    . $linhas . '
                </table>
                <p style="margin-top:16px;"><strong>Total em aberto: R$ ' . number_format($total, 2, ',', '.') . '</strong></p>
                <p>Caso o pagamento já tenha sido efetuado, desconsidere esta mensagem.</p>';

                $resultado = Mailer::send(
                    $primeiro['email'],
                    '[' . SITE_TITLE . '] Aviso de mensalidades em aberto',
                    Mailer::layout('Mensalidades em aberto', $html)
                );

                if ($resultado['success']) {
                    $mensagem = "Lembrete enviado com sucesso para {$nome}.";
                    $mensagem_tipo = 'success';
                } else {
                    $mensagem = 'Erro ao enviar o e-mail: ' . ($resultado['error'] ?? 'erro desconhecido');
                    $mensagem_tipo = 'danger';
                }
            }
        }
    }
}

// Agrupa por associado para resumo
$porAssociado = [];
foreach ($inadimplentes as $item) {
    if (!isset($porAssociado[$item['associado_id']])) {
        $porAssociado[$item['associado_id']] = [
            'nome' => $item['associado_nome'],
            'telefone' => $item['telefone'],
            'email' => $item['email'],
            'cidade' => $item['cidade'],
            'total' => 0.0,
            'itens' => [],
        ];
    }
    $porAssociado[$item['associado_id']]['total'] += (float) $item['valor_cobrado'];
    $porAssociado[$item['associado_id']]['itens'][] = $item;
}

$associacoes = (new Associacao())->getAll();

$page_title = "Controle de Cobrança";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Controle de Cobrança</h1>
            <span class="text-muted">Inadimplentes: mensalidades pendentes com vencimento vencido.</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/mensalidades" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show"><?php echo htmlspecialchars($mensagem); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-funnel me-1"></i>Filtros</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/caixa/cobranca" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Associação</label>
                    <select name="associacao_id" class="form-select form-select-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $f['associacao_id'] === (int)$a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Ano</label>
                    <select name="ano" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $f['ano'] === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Mês</label>
                    <select name="mes" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $f['mes'] === $m ? 'selected' : ''; ?>><?php echo str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i>Inadimplentes (<?php echo count($porAssociado); ?>)</span>
            <span class="badge bg-light text-dark">R$ <?php echo number_format(array_sum(array_column($porAssociado, 'total')), 2, ',', '.'); ?></span>
        </div>
        <div class="card-body table-responsive">
            <table id="cobrancaTable" class="table table-striped table-hover small">
                <thead>
                    <tr>
                        <th>Associado</th>
                        <th>Telefone</th>
                        <th>E-mail</th>
                        <th>Cidade</th>
                        <th>Contas em aberto</th>
                        <th>Total em Aberto</th>
                        <th class="text-center">Cobrar</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($porAssociado)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Nenhum inadimplente com o filtro atual.</td></tr>
                <?php else: foreach ($porAssociado as $aid => $a): ?>
                    <tr>
                        <td class="fw-bold"><?php echo htmlspecialchars($a['nome']); ?></td>
                        <td><?php echo $a['telefone'] ? htmlspecialchars($a['telefone']) : '—'; ?></td>
                        <td><?php echo $a['email'] ? htmlspecialchars($a['email']) : '—'; ?></td>
                        <td><?php echo $a['cidade'] ? htmlspecialchars($a['cidade']) : '—'; ?></td>
                        <td>
                            <?php foreach ($a['itens'] as $item): ?>
                                <small class="badge bg-warning text-dark mb-1 me-1"
                                      title="Venceu em <?php echo date('d/m/Y', strtotime($item['data_vencimento'])); ?>">
                                    <?php echo str_pad((string)$item['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $item['ano_referencia']; ?>
                                </small>
                            <?php endforeach; ?>
                        </td>
                        <td class="fw-bold text-danger">R$ <?php echo number_format($a['total'], 2, ',', '.'); ?></td>
                        <td class="text-center text-nowrap">
                            <?php $whats = telefoneWhatsApp($a['telefone']); ?>
                            <?php if (!empty($whats)): ?>
                                <a class="btn btn-success btn-sm"
                                   title="Lembrar via WhatsApp"
                                   target="_blank"
                                   href="https://wa.me/<?php echo $whats; ?>?text=<?php echo urlencode('Olá ' . $a['nome'] . ', tudo bem? Identificamos mensalidades em aberto no valor de R$ ' . number_format($a['total'], 2, ',', '.') . ' na associação. Pedimos gentileza de regularizar sua situação. Obrigado!'); ?>">
                                    <i class="bi bi-whatsapp"></i>
                                </a>
                            <?php else: ?>
                                <a class="btn btn-success btn-sm disabled" title="Associado sem WhatsApp cadastrado"><i class="bi bi-whatsapp"></i></a>
                            <?php endif; ?>
                            <?php if ($podeGerenciarCobranca && !empty($a['email'])): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Enviar lembrete de cobrança por e-mail para <?php echo addslashes($a['nome']); ?>?');">
                                    <?php echo CSRFProtection::getTokenField(); ?>
                                    <input type="hidden" name="acao" value="lembrete_cobranca">
                                    <input type="hidden" name="associado_id" value="<?php echo $aid; ?>">
                                    <input type="hidden" name="associacao_id" value="<?php echo $f['associacao_id']; ?>">
                                    <input type="hidden" name="mes" value="<?php echo $f['mes']; ?>">
                                    <input type="hidden" name="ano" value="<?php echo $f['ano']; ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm" title="Enviar lembrete por e-mail">
                                        <i class="bi bi-envelope"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-outline-primary btn-sm disabled" title="Associado sem e-mail cadastrado"><i class="bi bi-envelope"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#cobrancaTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 25,
        "responsive": true
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>