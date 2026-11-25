<?php
/**
 * Visualizar Assembleia (Gestor) / Votar (Associado)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getInstance()->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/assembleia');
    exit;
}
$assembleiaId = (int) $_GET['id'];

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

// Pautas
$stmt = $db->prepare("SELECT * FROM assembleia_pautas WHERE assembleia_id = ? ORDER BY ordem");
$stmt->execute([$assembleiaId]);
$pautas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se é gestor/admin OU se é o criador da assembleia (via associado vinculado)
$isGestor = (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] <= 2);

// Verificar se é associado logado no portal
$isAssociado = isset($_SESSION['associado_id']);
$associadoId = $isAssociado ? (int) $_SESSION['associado_id'] : 0;
$jaVotou = false;
$votoExistente = [];

// Verificar se é o criador da assembleia (associado logado no portal)
$isCriador = false;
if ($isAssociado && $associadoId > 0) {
    // Verificar se o associado logado tem user_id vinculado ao created_by da assembleia
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $assembleia['created_by']) {
        $isCriador = true;
    }
}

// Verificar se tem cargo de gestão na associação (diretor, secretário, tesoureiro, comissões, conselhos)
    require_once __DIR__ . '/../../classes/User.php';
    $user = new User();
    $isGestorAssociacao = !empty($assembleia['associacao_id'])
        && $user->isMembroDiretoria((int) $assembleia['associacao_id']);

    // Permissão para gerenciar (editar, publicar, encerrar): gestor do sistema OU criador OU cargo de gestão na associação
    $podeGerenciar = $isGestor || $isCriador || $isGestorAssociacao;

if ($isAssociado) {
    // Verificar se associado pode votar nesta assembleia
    $podeVotar = true;
    if ($assembleia['associacao_id']) {
        $stmt = $db->prepare("SELECT 1 FROM associado_associacao WHERE associado_id = ? AND associacao_id = ?");
        $stmt->execute([$associadoId, $assembleia['associacao_id']]);
        $podeVotar = $stmt->fetchColumn() > 0;
    }
    
    if ($podeVotar) {
        // Verificar votos existentes
        foreach ($pautas as $p) {
            $stmt = $db->prepare("SELECT voto FROM assembleia_votos WHERE assembleia_id = ? AND pauta_id = ? AND associado_id = ?");
            $stmt->execute([$assembleiaId, $p['id'], $associadoId]);
            $voto = $stmt->fetchColumn();
            if ($voto) {
                $jaVotou = true;
                $votoExistente[$p['id']] = json_decode($voto, true);
            }
        }
    }
}

// Status temporal
$agora = new DateTime();
$inicio = new DateTime($assembleia['data_inicio']);
$fim = new DateTime($assembleia['data_fim']);
$ativa = $assembleia['status'] === 'ativa' && $agora >= $inicio && $agora <= $fim;
$naoIniciada = $agora < $inicio;
$encerrada = $agora > $fim || $assembleia['status'] === 'encerrada';

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAssociado && $ativa && $podeVotar && !$jaVotou) {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $db->beginTransaction();
        try {
            foreach ($pautas as $p) {
                $voto = [];
                if ($p['tipo'] === 'eleicao') {
                    $opcao = $_POST['voto_' . $p['id']] ?? '';
                    if ($opcao) $voto['candidato'] = $opcao;
                } else {
                    $opcao = $_POST['voto_' . $p['id']] ?? '';
                    if ($opcao) $voto['opcao'] = $opcao; // sim, nao, abstenção
                }
                
                if (!empty($voto)) {
                    $stmt = $db->prepare("
                        INSERT INTO assembleia_votos (assembleia_id, pauta_id, associado_id, voto, ip, user_agent)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $assembleiaId, $p['id'], $associadoId,
                        json_encode($voto, JSON_UNESCAPED_UNICODE),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
            }
            $db->commit();
            $mensagem = 'Seu voto foi registrado com sucesso! Obrigado por participar.';
            $mensagem_tipo = 'success';
            $jaVotou = true;
        } catch (Exception $e) {
            $db->rollBack();
            $mensagem = 'Erro ao registrar voto: ' . $e->getMessage();
            $mensagem_tipo = 'danger';
        }
    }
}

$page_title = "Assembleia: " . htmlspecialchars($assembleia['titulo']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people-fill text-primary me-2"></i><?php echo htmlspecialchars($assembleia['titulo']); ?></h1>
            <span class="text-muted">
                <?php echo $assembleia['associacao_nome'] ? htmlspecialchars($assembleia['associacao_nome'].' ('.$assembleia['associacao_sigla'].')') : 'Todas as associações'; ?>
            </span>
        </div>
        <div class="d-flex gap-2">
            <?php if ($podeGerenciar): ?>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/editar" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Editar</a>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/resultado" class="btn btn-outline-info"><i class="bi bi-graph-up me-1"></i> Resultado</a>
            <?php if ($assembleia['status'] === 'rascunho'): ?>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/pautas" class="btn btn-outline-success"><i class="bi bi-list-task me-1"></i> Gerenciar Pautas</a>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/publicar" class="btn btn-success" onclick="return confirm('Publicar e abrir votação?')"><i class="bi bi-send me-1"></i> Publicar</a>
            <?php elseif ($assembleia['status'] === 'ativa'): ?>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/encerrar" class="btn btn-danger" onclick="return confirm('Encerrar votação definitivamente?')"><i class="bi bi-stop-circle me-1"></i> Encerrar</a>
            <?php elseif ($assembleia['status'] === 'encerrada'): ?>
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/resultado" class="btn btn-outline-info"><i class="bi bi-graph-up me-1"></i> Resultado</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Lista</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 <?php echo $ativa ? 'text-success' : ($naoIniciada ? 'text-warning' : 'text-secondary'); ?>">
                        <i class="bi bi-<?php echo $ativa ? 'play-circle-fill' : ($naoIniciada ? 'clock' : 'stop-circle'); ?>"></i>
                    </div>
                    <div class="small text-muted"><?php echo $ativa ? 'VOTAÇÃO ABERTA' : ($naoIniciada ? 'AGUARDANDO INÍCIO' : 'ENCERRADA'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 text-primary"><?php echo count($pautas); ?></div>
                    <div class="small text-muted">Pautas</div>
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
                    <div class="small text-muted">Votantes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="h4 mb-0 text-secondary">
                        <?php 
                        // Total de aptos
                        $sqlAptos = "SELECT COUNT(*) FROM associados a WHERE a.status = 'ativo' AND a.status_associado = 'Ativo'";
                        $paramsAptos = [];
                        if ($assembleia['associacao_id']) {
                            $sqlAptos .= " AND EXISTS (SELECT 1 FROM associado_associacao aa WHERE aa.associado_id = a.id AND aa.associacao_id = ?)";
                            $paramsAptos[] = $assembleia['associacao_id'];
                        }
                        $stmt = $db->prepare($sqlAptos);
                        $stmt->execute($paramsAptos);
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                    <div class="small text-muted">Aptos a Votar</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Período -->
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <strong><i class="bi bi-calendar-event me-1"></i>Período de Votação:</strong>
            <br><small>Início: <?php echo date('d/m/Y H:i', strtotime($assembleia['data_inicio'])); ?> | Fim: <?php echo date('d/m/Y H:i', strtotime($assembleia['data_fim'])); ?></small>
        </div>
        <div class="text-end">
            <?php if ($naoIniciada): ?>
            <span class="badge bg-warning text-dark fs-6">Inicia em <?php echo $inicio->diff($agora)->format('%d dias %h horas'); ?></span>
            <?php elseif ($ativa): ?>
            <span class="badge bg-success fs-6">Termina em <?php echo $fim->diff($agora)->format('%d dias %h horas'); ?></span>
            <?php else: ?>
            <span class="badge bg-secondary fs-6">Encerrada</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?> alert-dismissible fade show">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($isAssociado && !$podeVotar): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>Você não está vinculado à associação desta assembleia e não pode votar.
    </div>
    <?php endif; ?>

    <!-- Pautas / Votação -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-ballot me-2"></i><?php echo $isAssociado && $ativa && $podeVotar && !$jaVotou ? 'Votação' : 'Pautas'; ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($pautas)): ?>
            <div class="text-center py-4 text-muted">Nenhuma pauta cadastrada.</div>
            <?php else: ?>
            
            <?php if ($isAssociado && $ativa && $podeVotar && !$jaVotou): ?>
            <!-- FORMULÁRIO DE VOTAÇÃO -->
            <form method="post" id="formVotacao">
                <?php echo CSRFProtection::getTokenField(); ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Vote em todas as pautas abaixo.</strong> Seu voto é secreto e não poderá ser alterado após o envio.
                </div>
                
                <?php foreach ($pautas as $p): ?>
                <div class="card mb-3 border-<?php echo $p['tipo']==='eleicao'?'info':'success'; ?>">
                    <div class="card-header fw-bold"><?php echo $p['ordem']; ?>. <?php echo htmlspecialchars($p['titulo']); ?></div>
                    <div class="card-body">
                        <?php if (!empty($p['descricao'])): ?>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($p['descricao']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($p['tipo'] === 'eleicao' && $p['opcoes']): ?>
                        <?php $ops = json_decode($p['opcoes'], true); ?>
                        <div class="row g-2">
                            <?php foreach ($ops as $op): ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="voto_<?php echo $p['id']; ?>" id="voto_<?php echo $p['id']; ?>_<?php echo htmlspecialchars($op['valor']); ?>" value="<?php echo htmlspecialchars($op['valor']); ?>" required>
                                    <label class="form-check-label w-100 p-3 border rounded hover-bg-light" for="voto_<?php echo $p['id']; ?>_<?php echo htmlspecialchars($op['valor']); ?>">
                                        <strong><?php echo htmlspecialchars($op['label']); ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="voto_<?php echo $p['id']; ?>" id="voto_<?php echo $p['id']; ?>_sim" value="sim" required>
                                    <label class="form-check-label w-100 p-3 border rounded text-success hover-bg-light" for="voto_<?php echo $p['id']; ?>_sim">
                                        <i class="bi bi-check-circle me-1"></i> <strong>SIM</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="voto_<?php echo $p['id']; ?>" id="voto_<?php echo $p['id']; ?>_nao" value="nao">
                                    <label class="form-check-label w-100 p-3 border rounded text-danger hover-bg-light" for="voto_<?php echo $p['id']; ?>_nao">
                                        <i class="bi bi-x-circle me-1"></i> <strong>NÃO</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="voto_<?php echo $p['id']; ?>" id="voto_<?php echo $p['id']; ?>_abstencao" value="abstencao">
                                    <label class="form-check-label w-100 p-3 border rounded text-warning hover-bg-light" for="voto_<?php echo $p['id']; ?>_abstencao">
                                        <i class="bi bi-dash-circle me-1"></i> <strong>ABSTENÇÃO</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Confirmar envio do voto? Não será possível alterar depois.');">
                        <i class="bi bi-ballot-check me-2"></i> Confirmar Meu Voto
                    </button>
                </div>
            </form>
            
            <?php elseif ($jaVotou): ?>
            <div class="alert alert-success text-center py-4">
                <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                <h4 class="mt-3">Obrigado por votar!</h4>
                <p class="text-muted">Seu voto foi registrado com sucesso e não pode ser alterado.</p>
            </div>
            
            <?php else: ?>
            <!-- VISUALIZAÇÃO (Gestor ou fora de período) -->
            <?php foreach ($pautas as $p): ?>
            <div class="card mb-3 border-<?php echo $p['tipo']==='eleicao'?'info':'success'; ?>">
                <div class="card-header fw-bold"><?php echo $p['ordem']; ?>. <?php echo htmlspecialchars($p['titulo']); ?></div>
                <div class="card-body">
                    <?php if (!empty($p['descricao'])): ?>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($p['descricao']); ?></p>
                    <?php endif; ?>
                    
                    <span class="badge bg-<?php echo $p['tipo']==='eleicao'?'info':($p['tipo']==='aprovacao'?'success':'secondary'); ?>">
                        <?php echo ucfirst($p['tipo']); ?>
                    </span>
                    
                    <?php if ($isGestor): ?>
                    <div class="mt-2">
                        <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/resultado?pauta=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up me-1"></i> Ver Resultado</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>