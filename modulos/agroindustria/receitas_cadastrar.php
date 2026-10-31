<?php
$page_title = "Nova Receita";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ReceitaAgroindustria.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$receitaClass = new ReceitaAgroindustria();
$associado = new Associado();
$associados = $user->hasPermission(NIVEL_GESTOR) ? $associado->getAll() : ($associado->getById($user->getAssociadoId()) ? [$associado->getById($user->getAssociadoId())] : []);

$message = '';
$message_type = '';
$dados = $_POST;
$ingredientes = [];
$percentual = 0;
$selo = 'SEM_SELO';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ingredientes = [];
    $nomes = $_POST['ing_nome'] ?? [];
    $organicos = $_POST['ing_organico'] ?? [];
    $quantidades = $_POST['ing_quantidade'] ?? [];
    $unidades = $_POST['ing_unidade'] ?? [];
    $obs = $_POST['ing_obs'] ?? [];

    foreach ((array)$nomes as $i => $nome) {
        $nome = trim($nome ?? '');
        if ($nome === '') {
            continue;
        }
        $ingredientes[] = [
            'nome_ingrediente' => $nome,
            'eh_organico' => !empty($organicos[$i]) ? 1 : 0,
            'quantidade' => (float)($quantidades[$i] ?? 0),
            'unidade_medida' => trim($unidades[$i] ?? '') !== '' ? $unidades[$i] : 'kg',
            'observacoes' => trim($obs[$i] ?? ''),
        ];
    }

    if (empty(trim($dados['nome_produto_final'] ?? ''))) {
        $message = 'Informe o nome do produto final.';
        $message_type = 'danger';
    } elseif (empty($ingredientes)) {
        $message = 'Adicione ao menos um ingrediente com quantidade maior que zero.';
        $message_type = 'danger';
    } else {
        try {
            $dados['associado_id'] = !empty($dados['associado_id']) ? $dados['associado_id'] : ($user->getAssociadoId() ?? null);
            $dados['status'] = $dados['status'] ?? 'Ativa';
            $dados['criado_por'] = (int)$_SESSION['user_id'];
            $id = $receitaClass->create($dados, $ingredientes);
            AuditLog::registrar('RECEITA_CADASTRADA', 'receitas_agroindustria', $id, 'Receita: ' . $dados['nome_produto_final']);
            header('Location: ' . SITE_URL . '/agroindustria/receitas?cadastrada=1');
            exit;
        } catch (Exception $e) {
            error_log('Erro ao salvar receita: ' . $e->getMessage());
            $message = 'Erro ao salvar a receita. Tente novamente.';
            $message_type = 'danger';
        }
    }
    $percentual = $receitaClass->calcularPercentualOrganico($ingredientes);
    $selo = $receitaClass->classificarSelo($percentual);
}

$seloTexto = 'Sem selo (<70%)';
$barColor  = 'bg-secondary';
if ($percentual >= 95) {
    $seloTexto = 'Produto Orgânico Brasil (≥95%)';
    $barColor  = 'bg-success';
} elseif ($percentual >= 70) {
    $seloTexto = 'Ingredientes Orgânicos (70–94%)';
    $barColor  = 'bg-warning';
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-journal-richtext text-success"></i> Nova Receita</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/receitas" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert"><?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form method="POST" id="form-receita" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome do Produto Final <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_produto_final" required value="<?php echo htmlspecialchars($dados['nome_produto_final'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Associado</label>
                    <select class="form-select" name="associado_id">
                        <option value="">Selecione...</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo (($dados['associado_id'] ?? '') == $a['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="Ativa" <?php echo (($dados['status'] ?? '') === 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Rascunho" <?php echo (($dados['status'] ?? '') === 'Rascunho') ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="Inativa" <?php echo (($dados['status'] ?? '') === 'Inativa') ? 'selected' : ''; ?>>Inativa</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="descricao" rows="2"><?php echo htmlspecialchars($dados['descricao'] ?? ''); ?></textarea>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Ingredientes</h5>
                <button type="button" class="btn btn-sm btn-outline-success" id="btn-add-ingrediente"><i class="bi bi-plus-circle"></i> Adicionar ingrediente</button>
            </div>

            <div id="ingredientes-container">
                <div class="row g-2 ingrediente-row">
                    <div class="col-md-3">
                        <input type="text" name="ing_nome[]" class="form-control" placeholder="Nome do ingrediente">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="ing_quantidade[]" class="form-control" step="0.001" min="0" placeholder="Quantidade">
                    </div>
                    <div class="col-md-1">
                        <select name="ing_unidade[]" class="form-select">
                            <option value="kg">kg</option>
                            <option value="g">g</option>
                            <option value="mg">mg</option>
                            <option value="l">l</option>
                            <option value="ml">ml</option>
                            <option value="un">un</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="ing_organico[]" class="form-select">
                            <option value="1">Orgânico</option>
                            <option value="0">Convencional</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="ing_obs[]" class="form-control" placeholder="Observações">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ingrediente"><i class="bi bi-x"></i></button>
                    </div>
                </div>
            </div>

            <div class="card mt-3 mb-0">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><i class="bi bi-shield-check text-success me-1"></i><strong>Percentual de ingredientes orgânicos</strong></span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-semibold" id="percentual-label"><?php echo number_format($percentual, 1, ',', '.') . '%'; ?></span>
                            <span class="badge" id="selo-label"><?php echo $seloTexto; ?></span>
                        </div>
                    </div>
                    <div class="progress" style="height:22px;" role="progressbar"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)$percentual; ?>">
                        <div class="progress-bar <?php echo $barColor; ?>" id="percentual-bar" style="width: <?php echo $percentual; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Receita</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('ingredientes-container');

    function fatorConversao(unidade) {
        switch ((unidade || 'kg').toLowerCase()) {
            case 'kg': return 1000;
            case 'mg': return 0.001;
            case 't':  return 1000000;
            case 'l':  return 1000;
            case 'ml': return 1;
            default:   return 1;
        }
    }

    function recalcular() {
        let somaPeso = 0, somaOrganico = 0;
        document.querySelectorAll('.ingrediente-row').forEach(row => {
            const qtd = parseFloat(row.querySelector('[name*="quantidade"]').value) || 0;
            const unidade = row.querySelector('[name*="unidade"]').value;
            const org = row.querySelector('[name*="organico"]').value === '1';
            const peso = qtd * fatorConversao(unidade);
            somaPeso += peso;
            if (org) somaOrganico += peso;
        });
        const pct = somaPeso > 0 ? Math.round((somaOrganico / somaPeso) * 1000) / 10 : 0;
        const bar = document.getElementById('percentual-bar');
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', pct);
        bar.className = 'progress-bar';
        document.getElementById('percentual-label').textContent = pct.toFixed(1).replace('.', ',') + '%';
        const seloLabel = document.getElementById('selo-label');
        if (pct >= 95) { bar.classList.add('bg-success'); seloLabel.textContent = 'Produto Orgânico Brasil (≥95%)'; seloLabel.className = 'badge bg-success'; }
        else if (pct >= 70) { bar.classList.add('bg-warning'); seloLabel.textContent = 'Ingredientes Orgânicos (70–94%)'; seloLabel.className = 'badge bg-warning text-dark'; }
        else { bar.classList.add('bg-secondary'); seloLabel.textContent = 'Sem selo (<70%)'; seloLabel.className = 'badge bg-secondary'; }
    }

    document.getElementById('btn-add-ingrediente').addEventListener('click', function() {
        const first = container.querySelector('.ingrediente-row');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => el.value = '');
        clone.querySelector('[name*="organico"]').value = '1';
        clone.querySelector('[name*="unidade"]').value = 'kg';
        container.appendChild(clone);
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-ingrediente')) {
            const rows = container.querySelectorAll('.ingrediente-row');
            if (rows.length > 1) {
                e.target.closest('.ingrediente-row').remove();
                recalcular();
            }
        }
    });

    container.addEventListener('input', recalcular);
    recalcular();
});
</script>
