<?php
$page_title = "Nova Ordem de Produção";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/OrdemProducao.php';
require_once __DIR__ . '/../../classes/Agroindustria.php';
require_once __DIR__ . '/../../classes/ReceitaAgroindustria.php';
require_once __DIR__ . '/../../classes/ControleHigienizacao.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$opClass = new OrdemProducao();
$receitaClass = new ReceitaAgroindustria();
$higClass = new ControleHigienizacao();
$db = Database::getInstance()->getConnection();

$filtroAssociado = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);
$receitas = $receitaClass->getAll($filtroAssociado);

// Higienizações concluídas nas últimas 24h disponíveis
$higienizacoes = [];
if ($filtroAssociado) {
    $stmt = $db->prepare("SELECT * FROM controle_higienizacao WHERE excluido = 0 AND data_hora_fim IS NOT NULL AND data_hora_fim >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND associado_id = ? ORDER BY data_hora_fim DESC");
    $stmt->execute([$filtroAssociado]);
} else {
    $stmt = $db->prepare("SELECT * FROM controle_higienizacao WHERE excluido = 0 AND data_hora_fim IS NOT NULL AND data_hora_fim >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY data_hora_fim DESC");
    $stmt->execute();
}
$higienizacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lotes primários (campo) disponíveis — linha de produção AGRÍCOLA (produto com lavoura).
// Para produtor, apenas os lotes do próprio associado.
$stmt = $db->prepare("SELECT l.id, l.nome_lote, l.quantidade, l.unidade_medida, p.nome AS nome_produto
                      FROM lotes_producao l
                      LEFT JOIN produtos p ON l.produto_id = p.id
                      WHERE l.excluido = 0
                        AND p.lavoura_id IS NOT NULL AND p.lavoura_id != 0" . ($filtroAssociado ? " AND l.associado_id = :assoc" : "") . " ORDER BY l.data_producao DESC");
if ($filtroAssociado) {
    $stmt->execute([':assoc' => $filtroAssociado]);
} else {
    $stmt->execute();
}
$lotesCampo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lotes processados (final) — linha de produção da AGROINDÚSTRIA (produto SEM lavoura).
$stmt = $db->prepare("SELECT l.id, l.nome_lote, l.quantidade, l.unidade_medida, p.nome AS nome_produto
                      FROM lotes_producao l
                      LEFT JOIN produtos p ON l.produto_id = p.id
                      WHERE l.excluido = 0
                        AND (p.lavoura_id IS NULL OR p.lavoura_id = 0)" . ($filtroAssociado ? " AND l.associado_id = :assoc" : "") . " ORDER BY l.data_producao DESC");
if ($filtroAssociado) {
    $stmt->execute([':assoc' => $filtroAssociado]);
} else {
    $stmt->execute();
}
$lotesFinais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Insumos utilizados — todos os insumos cadastrados pelos usuários (membros) desta agroindústria.
// Ex.: usuário X registra um insumo e usuário Y, da mesma agroindústria, registra outro — ambos aparecem.
$insumos = [];
if ($filtroAssociado) {
    $agroClass = new Agroindustria();
    $associadosGrupo = $agroClass->getAssociadosDaMesmaAgroindustria((int)$filtroAssociado);
    if ($associadosGrupo) {
        $in = implode(',', array_map('intval', $associadosGrupo));
        $stmt = $db->prepare("SELECT i.id, i.nome, i.lote, i.tipo_insumo, i.quantidade_estoque,
                                      CONCAT(ass.nome, ' ', COALESCE(ass.sobrenome, '')) AS registrado_por,
                                      f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj
                              FROM insumos i
                              LEFT JOIN associados ass ON i.associado_id = ass.id
                              LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                              WHERE i.excluido = 0 AND i.associado_id IN ($in)
                              ORDER BY i.nome ASC");
        $stmt->execute();
        $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $stmt = $db->prepare("SELECT i.id, i.nome, i.lote, i.tipo_insumo, i.quantidade_estoque,
                                  CONCAT(ass.nome, ' ', COALESCE(ass.sobrenome, '')) AS registrado_por,
                                  f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj
                          FROM insumos i
                          LEFT JOIN associados ass ON i.associado_id = ass.id
                          LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                          WHERE i.excluido = 0
                          ORDER BY i.nome ASC");
    $stmt->execute();
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'receita_id' => $_POST['receita_id'] ?? '',
        'higienizacao_previa_id' => $_POST['higienizacao_previa_id'] ?? '',
        'lote_processado_final' => $_POST['lote_processado_final'] ?? '',
        'data_processamento' => $_POST['data_processamento'] ?? '',
        'quantidade_final_obtida' => $_POST['quantidade_final_obtida'] ?? '',
        'unidade_medida' => $_POST['unidade_medida'] ?? 'KG',
        'associado_id' => !empty($_POST['associado_id']) ? $_POST['associado_id'] : ($user->getAssociadoId() ?? null),
        'observacoes' => $_POST['observacoes'] ?? '',
        'criado_por' => (int)$_SESSION['user_id'],
    ];

    $itens = [];
    $insumosPost = $_POST['item_insumo'] ?? [];
    $qtds = $_POST['item_quantidade'] ?? [];
    $unds = $_POST['item_unidade'] ?? [];
    $nfs = $_POST['item_nf'] ?? [];
    foreach ((array)$insumosPost as $i => $insumoId) {
        if ($insumoId === '' || $insumoId === null) {
            continue;
        }
        $qtd = (float)($qtds[$i] ?? 0);
        if ($qtd <= 0) {
            continue;
        }
        $itens[] = [
            'insumo_id' => (int)$insumoId,
            'lote_primario_id' => null,
            'produto_id' => null,
            'quantidade_utilizada' => $qtd,
            'unidade_medida' => $unds[$i] ?? 'KG',
            'numero_nf' => trim((string)($nfs[$i] ?? '')),
        ];
    }

    // Validações
    if (empty($dados['receita_id']) || empty($dados['lote_processado_final']) || empty($dados['data_processamento']) || empty($dados['quantidade_final_obtida'])) {
        $message = 'Preencha os campos obrigatórios (receita, lote processado, data e quantidade final).';
        $message_type = 'danger';
    } elseif (empty($itens)) {
        $message = 'Informe ao menos um insumo utilizado com quantidade para o balanço de massas.';
        $message_type = 'danger';
    } else {
        // RF08: trava de 24h — a higienização deve ser válida para a data de processamento
        $receita = $receitaClass->getById((int)$dados['receita_id']);
        if (!$receita) {
            $message = 'Receita inválida.';
            $message_type = 'danger';
        } elseif (!$higClass->haHigienizacaoValida($dados['data_processamento'], 'Qualquer linha', (int)($dados['associado_id'] ?? 0), 24)) {
            // Se o usuário escolheu uma higienização específica, ela precisa estar dentro das 24h
            if (empty($dados['higienizacao_previa_id'])) {
                $message = 'BLOQUEADO (RF08): é obrigatória uma higienização concluída do equipamento/linha nas últimas 24h antes do processamento.';
                $message_type = 'danger';
            } else {
                $hig = $higClass->getById((int)$dados['higienizacao_previa_id']);
                if (!$hig || !$hig['data_hora_fim']) {
                    $message = 'BLOQUEADO (RF08): a higienização informada não está concluída.';
                    $message_type = 'danger';
                } else {
                    $fim = strtotime($hig['data_hora_fim']);
                    $proc = strtotime($dados['data_processamento']);
                    if ($proc - $fim > 24 * 3600 || $fim > $proc) {
                        $message = 'BLOQUEADO (RF08): a higienização informada não está dentro da janela de 24h antes do processamento.';
                        $message_type = 'danger';
                    } else {
                        $thisOp = null;
                    }
                }
            }
        }

        // RF11 + RF10: salvar OP com balanço de massas
        if (empty($message)) {
            try {
                $dados['percentual_organico'] = (float)$receita['percentual_organico_calculado'];
                $dados['selo_autorizado'] = $receita['selo_autorizado'];
                $id = $opClass->create($dados, $itens);
                $op = $opClass->getById($id);
                AuditLog::registrar('ORDEM_PRODUCAO_CADASTRADA', 'ordens_producao', $id, 'Lote: ' . $dados['lote_processado_final'] . ' | Status: ' . $op['status_auditoria']);
                header('Location: ' . SITE_URL . '/agroindustria/ordens/' . $id . '?criada=1');
                exit;
            } catch (Exception $e) {
                error_log('Erro ao salvar OP: ' . $e->getMessage());
                $message = 'Erro ao salvar a ordem de produção. Tente novamente.';
                $message_type = 'danger';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-clipboard-check text-success"></i> Nova Ordem de Produção</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/ordens" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Receita (produto final) <span class="text-danger">*</span></label>
                    <select class="form-select" name="receita_id" id="select-receita" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($receitas as $r): ?>
                            <option value="<?php echo $r['id']; ?>" data-percentual="<?php echo $r['percentual_organico_calculado']; ?>" <?php echo (($_POST['receita_id'] ?? '') == $r['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['nome_produto_final']); ?> (<?php echo number_format((float)$r['percentual_organico_calculado'], 1, ',', '.'); ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lote Processado (final) <span class="text-danger">*</span></label>
                    <select class="form-select" name="lote_processado_final" required>
                        <option value="">Selecione o lote...</option>
                        <?php foreach ($lotesFinais as $l): ?>
                            <option value="<?php echo htmlspecialchars($l['nome_lote']); ?>" <?php echo (($_POST['lote_processado_final'] ?? '') == $l['nome_lote']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['nome_lote']) . ' — ' . htmlspecialchars($l['nome_produto'] ?? 'Produto') . ' (disp. ' . number_format((float)$l['quantidade'], 2, ',', '.') . ' ' . htmlspecialchars($l['unidade_medida'] ?? '') . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$lotesFinais): ?>
                        <small class="text-warning"><i class="bi bi-exclamation-circle"></i> Nenhum lote de produto final (sem lavoura) cadastrado. Cadastre-o em "Lotes de Produção" selecionando um produto da agroindústria.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data/Hora do Processamento <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" name="data_processamento" required value="<?php echo htmlspecialchars($_POST['data_processamento'] ?? date('Y-m-d\TH:i')); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Quantidade Final <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" name="quantidade_final_obtida" required value="<?php echo htmlspecialchars($_POST['quantidade_final_obtida'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unidade</label>
                    <select class="form-select" name="unidade_medida">
                        <option value="KG">KG</option>
                        <option value="L">L</option>
                        <option value="UN">UN</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Higienização Prévia (últimas 24h)</label>
                    <select class="form-select" name="higienizacao_previa_id">
                        <option value="">Selecionar registro concluído...</option>
                        <?php foreach ($higienizacoes as $h): ?>
                            <option value="<?php echo $h['id']; ?>" <?php echo (($_POST['higienizacao_previa_id'] ?? '') == $h['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['equipamento_linha'] . ' — ' . date('d/m H:i', strtotime($h['data_hora_fim'])) . ' (' . $h['responsavel_nome'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$higienizacoes): ?>
                        <small class="text-danger"><i class="bi bi-exclamation-circle"></i> Nenhuma higienização concluída nas últimas 24h. A ordem será bloqueada.</small>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Insumos Utilizados</h5>
                <button type="button" class="btn btn-sm btn-outline-success" id="btn-add-item"><i class="bi bi-plus-circle"></i> Adicionar insumo</button>
            </div>
            <p class="text-muted small">Insumos cadastrados pelos usuários da agroindústria (inclui membros). Informe a origem (nota fiscal do fornecedor) para a rastreabilidade.</p>

            <div id="itens-container">
                <div class="row g-2 item-row">
                    <div class="col-md-5">
                        <select name="item_insumo[]" class="form-select" required>
                            <option value="">Selecione o insumo...</option>
                            <?php foreach ($insumos as $in): ?>
                                <option value="<?php echo $in['id']; ?>">
                                    <?php echo htmlspecialchars($in['nome'] . ($in['lote'] ? ' (lote ' . $in['lote'] . ')' : '')); ?>
                                    <?php echo $in['fornecedor'] ? ' — ' . htmlspecialchars($in['fornecedor']) : ''; ?>
                                    <?php echo !empty($in['registrado_por']) ? ' [' . htmlspecialchars($in['registrado_por']) . ']' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="item_quantidade[]" class="form-control" step="0.01" min="0" placeholder="Quantidade">
                    </div>
                    <div class="col-md-1">
                        <input type="text" name="item_unidade[]" class="form-control" value="KG">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="item_nf[]" class="form-control" placeholder="NF fornecedor">
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item"><i class="bi bi-x"></i></button>
                    </div>
                </div>
            </div>
            <?php if (!$insumos): ?>
                <div class="alert alert-warning small mt-2 mb-0">
                    <i class="bi bi-exclamation-circle"></i> Nenhum insumo cadastrado para esta agroindústria.
                    Cadastre insumos em <a href="<?php echo SITE_URL; ?>/insumos" class="alert-link">Insumos</a> ou adicione membros na agroindústria.
                </div>
            <?php endif; ?>

            <div class="col-12 mt-3">
                <label class="form-label">Observações</label>
                <textarea class="form-control" name="observacoes" rows="2"><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
            </div>

            <div class="alert alert-light border mt-3 mb-0 d-flex justify-content-between">
                <span><i class="bi bi-shield-check"></i> <strong>RF11 Balanço de Massas:</strong> entrada <span id="balanco-entrada">0,00</span> kg → saída <span id="balanco-saida">0,00</span> kg</span>
                <span class="badge bg-secondary" id="balanco-status">Aguardando dados</span>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Registrar Ordem de Produção</button>
        </div>
    </form>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('itens-container');
    const saidaInput = document.querySelector('[name="quantidade_final_obtida"]');
    const entradaLabel = document.getElementById('balanco-entrada');
    const saidaLabel = document.getElementById('balanco-saida');
    const statusLabel = document.getElementById('balanco-status');

    document.getElementById('btn-add-item').addEventListener('click', function() {
        const first = container.querySelector('.item-row');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(el => el.value = '');
        clone.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        container.appendChild(clone);
        recalcularBalanco();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-item')) {
            const rows = container.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                recalcularBalanco();
            }
        }
    });

    container.addEventListener('input', recalcularBalanco);
    saidaInput.addEventListener('input', recalcularBalanco);

    function recalcularBalanco() {
        let entrada = 0;
        container.querySelectorAll('.item-row').forEach(row => {
            entrada += parseFloat(row.querySelector('[name*="quantidade"]').value) || 0;
        });
        const saida = parseFloat(saidaInput.value) || 0;
        entradaLabel.textContent = entrada.toFixed(2).replace('.', ',');
        saidaLabel.textContent = saida.toFixed(2).replace('.', ',');

        if (entrada > 0) {
            const div = Math.abs(entrada - saida) / entrada * 100;
            if (div > 10) {
                statusLabel.textContent = 'ALERTA FRAUDE: divergência ' + div.toFixed(1) + '%';
                statusLabel.className = 'badge bg-danger';
            } else {
                statusLabel.textContent = 'Aprovado (divergência ' + div.toFixed(1) + '%)';
                statusLabel.className = 'badge bg-success';
            }
        } else {
            statusLabel.textContent = 'Aguardando dados';
            statusLabel.className = 'badge bg-secondary';
        }
    }
    recalcularBalanco();
});
</script>
