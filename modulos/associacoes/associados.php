<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Apenas ADMIN pode vincular associados a associações e definir cargos/funções.
// Gestores e Diretoria não têm esta permissão.
if (!$user->hasPermission(NIVEL_ADMIN)) {
    if ($user->isLoggedIn()) {
        require_once __DIR__ . '/../../includes/perfil.php';
        header('Location: ' . dashboard_destino());
    } else {
        header('Location: ' . SITE_URL . '/login');
    }
    exit;
}

$db = Database::getInstance()->getConnection();
$mensagem = '';
$mensagem_tipo = '';

$associacoes = (new Associacao())->getAll();
$associadoModel = new Associado();

// Cargos/funções da associação (quadro diretivo e comissões)
$cargosVinculo = [
    'membro'             => 'Membro',
    'presidente'         => 'Presidente',
    'vice-presidente'    => 'Vice-Presidente',
    'diretor'            => 'Diretor(a)',
    'secretario'         => 'Secretário(a)',
    'tesoureiro'         => 'Tesoureiro(a)',
    'conselho_fiscal'    => 'Conselho Fiscal',
    'comissao_etica'     => 'Comissão de Ética',
    'comissao_educativa' => 'Comissão Educativa',
];

// --- PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $associacaoId = (int) ($_POST['associacao_id'] ?? 0);
        $selecionados = isset($_POST['associados'])
            ? array_values(array_filter(array_map('intval', (array) $_POST['associados']), fn($v) => $v > 0))
            : [];

        if ($associacaoId <= 0) {
            // Por definição, registro único por associação recebe falso
        }

        if ($associacaoId > 0) {
            try {
                // Remove vínculos atuais dessa associação que não foram mantidos
                $placeholders = '';
                $params = [$associacaoId];
                if (!empty($selecionados)) {
                    $placeholders = implode(',', array_fill(0, count($selecionados), '?'));
                    $params = array_merge($params, $selecionados);
                }
                $sql = "DELETE FROM associado_associacao WHERE associacao_id = ?" . ($placeholders ? " AND associado_id NOT IN ($placeholders)" : "");
                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // Recupera vínculos atuais (após exclusão) para saber o que inserir
                $stmt = $db->prepare("SELECT associado_id FROM associado_associacao WHERE associacao_id = ?");
                $stmt->execute([$associacaoId]);
                $atuais = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'associado_id'));

                $aInserir = array_diff($selecionados, $atuais);
                if (!empty($aInserir)) {
                    $sql = "INSERT INTO associado_associacao (associado_id, associacao_id) VALUES ";
                    $valores = [];
                    $paramsIn = [];
                    foreach ($aInserir as $i => $aid) {
                        $valores[] = "(?, ?)";
                        $paramsIn[] = $aid;
                        $paramsIn[] = $associacaoId;
                    }
                    $stmt = $db->prepare($sql . implode(', ', $valores));
                    $stmt->execute($paramsIn);
                }

                // Atualiza `vinculo` (principal) se o associado não tiver vínculo
                $stmt = $db->prepare(
                    "SELECT associado_id FROM associado_associacao WHERE associacao_id = ?
                     AND associado_id NOT IN (SELECT id FROM associados WHERE vinculo IS NOT NULL)"
                );
                $stmt->execute([$associacaoId]);
                $semPrincipal = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $upVinculo = $db->prepare("UPDATE associados SET vinculo = ? WHERE id = ?");
                foreach ($semPrincipal as $sp) {
                    $upVinculo->execute([$associacaoId, (int) $sp['associado_id']]);
                }

                // Atualiza cargos (tipo_vinculo) informados no formulário
                if (isset($_POST['cargo']) && is_array($_POST['cargo'])) {
                    $upCargo = $db->prepare("UPDATE associado_associacao SET tipo_vinculo = ? WHERE associado_id = ? AND associacao_id = ?");
                    foreach ($_POST['cargo'] as $aid => $cargo) {
                        $aid = (int) $aid;
                        $cargo = in_array($cargo, array_keys($cargosVinculo), true) ? $cargo : 'membro';
                        $upCargo->execute([$cargo, $aid, $associacaoId]);
                    }
                }

                $mensagem = 'Vínculos e cargos atualizados com sucesso (' . count($selecionados) . ' associado(s)).';
                $mensagem_tipo = 'success';
            } catch (PDOException $e) {
                error_log("Erro ao atualizar vínculos: " . $e->getMessage());
                $mensagem = 'Erro ao atualizar vínculos.';
                $mensagem_tipo = 'danger';
            }
        }
    }
}

// --- MONTAGEM DA VIEW ---
$associacaoSelecionada = (int) ($_POST['associacao_id'] ?? $_GET['associacao_id'] ?? 0);
if ($associacaoSelecionada <= 0 && !empty($associacoes)) {
    $associacaoSelecionada = (int) $associacoes[0]['id'];
}

$todosAssociados = $associadoModel->getAll();
$vinculadosIds = [];
$vinculosCargo = []; // associado_id => tipo_vinculo
if ($associacaoSelecionada > 0) {
    $stmt = $db->prepare("SELECT associado_id, tipo_vinculo FROM associado_associacao WHERE associacao_id = ?");
    $stmt->execute([$associacaoSelecionada]);
    while ($v = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vinculadosIds[] = (int) $v['associado_id'];
        $vinculosCargo[(int) $v['associado_id']] = $v['tipo_vinculo'];
    }
}

$page_title = "Vínculo Associação × Associados";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-link-45deg text-primary me-2"></i>Vínculo Associação × Associados</h1>
            <span class="text-muted">Vincule/desvincule associados a uma associação.</span>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show"><?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white"><i class="bi bi-building me-1"></i>Selecione a Associação</div>
        <div class="card-body">
            <form method="get" action="<?php echo SITE_URL; ?>/associacao/associados" class="row g-2">
                <div class="col-md-6">
                    <select name="associacao_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $associacaoSelecionada === (int) $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(!empty($a['sigla']) ? $a['sigla'] : $a['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Carregar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($associacaoSelecionada > 0): ?>
    <form method="post" action="<?php echo SITE_URL; ?>/associacao/associados">
        <?php echo CSRFProtection::getTokenField(); ?>
        <input type="hidden" name="associacao_id" value="<?php echo $associacaoSelecionada; ?>">

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people-fill me-1"></i>Associados Disponíveis</span>
                        <span class="badge bg-light text-dark"><?php echo count($todosAssociados); ?></span>
                    </div>
                    <div class="card-body" style="max-height:520px; overflow-y:auto;">
                        <?php if (empty($todosAssociados)): ?>
                            <span class="text-muted small">Nenhum associado cadastrado.</span>
                        <?php else: ?>
                            <div class="form-check" style="display:none;">
                                <input type="checkbox" id="selecionarTodos">
                            </div>
                            <?php foreach ($todosAssociados as $a): ?>
                                <?php $vinculado = in_array((int) $a['id'], $vinculadosIds, true); ?>
                                <div class="d-flex align-items-center border rounded p-2 mb-2">
                                    <input class="form-check-input me-2" type="checkbox" name="associados[]"
                                           value="<?php echo $a['id']; ?>" id="assoc_<?php echo $a['id']; ?>" <?php echo $vinculado ? 'checked' : ''; ?>>
                                    <label class="form-check-label flex-grow-1" for="assoc_<?php echo $a['id']; ?>">
                                        <?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($a['email'] ?? ''); ?></small>
                                    </label>
                                    <?php if ($vinculado): ?>
                                        <span class="badge bg-success">Vinculado</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people me-1"></i>Vinculados a esta Associação</span>
                        <span class="badge bg-light text-dark"><?php echo count($vinculadosIds); ?></span>
                    </div>
                    <div class="card-body" style="max-height:520px; overflow-y:auto;">
                        <?php
                        $listaVinculados = array_filter($todosAssociados, function ($a) use ($vinculadosIds) {
                            return in_array((int) $a['id'], $vinculadosIds, true);
                        });
                        ?>
                        <?php if (empty($listaVinculados)): ?>
                            <span class="text-muted small">Nenhum associado vinculado ainda. Marque acima e salve.</span>
                        <?php else: ?>
                            <?php foreach ($listaVinculados as $a): ?>
                                <?php $cargoAtual = $vinculosCargo[(int) $a['id']] ?? 'membro'; ?>
                                <div class="border rounded p-2 mb-2 bg-success-subtle">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="bi bi-person-check-fill text-success me-2"></i>
                                        <span class="small fw-semibold"><?php echo htmlspecialchars($a['nome'] . ' ' . ($a['sobrenome'] ?? '')); ?></span>
                                    </div>
                                    <label class="small text-muted d-block mb-1">Função / Cargo na associação</label>
                                    <select class="form-select form-select-sm" name="cargo[<?php echo (int) $a['id']; ?>]">
                                        <?php foreach ($cargosVinculo as $cargoVal => $cargoLabel): ?>
                                            <option value="<?php echo $cargoVal; ?>" <?php echo $cargoAtual === $cargoVal ? 'selected' : ''; ?>><?php echo htmlspecialchars($cargoLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-success" type="submit"><i class="bi bi-check-lg me-1"></i>Salvar Vínculos</button>
            <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>