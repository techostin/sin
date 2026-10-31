<?php
/**
 * Editar Assembleia
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getInstance()->getConnection();

// Verificar permissão: gestor (user_nivel <= 2) OU criador da assembleia OU diretor/secretário da associação
$isGestor = isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] <= 2;
$isCriador = false;
$isDirSec = false;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/assembleia');
    exit;
}
$assembleiaId = (int) $_GET['id'];

$stmt = $db->prepare("SELECT * FROM assembleias WHERE id = ?");
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

    $podeEditar = $isGestor || $isCriador || $isGestorAssociacao;

if (!$podeEditar) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}

// Não permitir editar se não estiver em rascunho (apenas datas podem ser alteradas se ativa? Por segurança, só rascunho)
$podeAlterarTudo = $assembleia['status'] === 'rascunho';

$mensagem = '';
$mensagem_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim = $_POST['data_fim'] ?? '';
        $tipo_votacao = $_POST['tipo_votacao'] ?? 'simples';
        $quorum_minimo = (float) ($_POST['quorum_minimo'] ?? 50.00);
        
        if (empty($titulo) || empty($data_inicio) || empty($data_fim)) {
            $mensagem = 'Preencha título, data de início e data de fim.';
            $mensagem_tipo = 'danger';
        } elseif (strtotime($data_fim) <= strtotime($data_inicio)) {
            $mensagem = 'Data de fim deve ser posterior à data de início.';
            $mensagem_tipo = 'danger';
        } elseif ($quorum_minimo < 0 || $quorum_minimo > 100) {
            $mensagem = 'Quórum mínimo deve ser entre 0 e 100%.';
            $mensagem_tipo = 'danger';
        } else {
            $stmt = $db->prepare("
                UPDATE assembleias 
                SET titulo = ?, descricao = ?, data_inicio = ?, data_fim = ?, 
                    tipo_votacao = ?, quorum_minimo = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $descricao, $data_inicio, $data_fim, $tipo_votacao, $quorum_minimo, $assembleiaId]);
            
            $_SESSION['success_message'] = 'Assembleia atualizada com sucesso!';
            header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId);
            exit;
        }
    }
}

$associacoes = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Editar Assembleia";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-pencil text-primary me-2"></i>Editar Assembleia</h1>
            <span class="text-muted"><?php echo htmlspecialchars($assembleia['titulo']); ?></span>
        </div>
        <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <?php if (!$podeAlterarTudo): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Atenção:</strong> Esta assembleia não está mais em rascunho (status: <strong><?php echo ucfirst($assembleia['status']); ?></strong>).
        Apenas a <strong>data de início/fim</strong> pode ser alterada. Para alterar outros dados, entre em contato com a administração.
    </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Dados da Assembleia</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <?php echo CSRFProtection::getTokenField(); ?>
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Título <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($assembleia['titulo']); ?>" <?php echo !$podeAlterarTudo ? 'readonly' : 'required'; ?> maxlength="255">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" <?php echo !$podeAlterarTudo ? 'readonly' : ''; ?>><?php echo htmlspecialchars($assembleia['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Data/Hora Início <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_inicio" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($assembleia['data_inicio'])); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Data/Hora Fim <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_fim" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($assembleia['data_fim'])); ?>" required>
                    </div>
                    
                    <?php if ($podeAlterarTudo): ?>
                    <div class="col-md-6">
                        <label class="form-label">Associação</label>
                        <select name="associacao_id" class="form-select" disabled>
                            <option value="">Todas as associações</option>
                            <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $assembleia['associacao_id'] == $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['nome'].' ('.$a['sigla'].')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Não é possível alterar a associação após a criação.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Votação</label>
                        <select name="tipo_votacao" class="form-select" <?php echo !$podeAlterarTudo ? 'disabled' : ''; ?>>
                            <option value="simples" <?php echo $assembleia['tipo_votacao'] === 'simples' ? 'selected' : ''; ?>>Simples (Sim/Não/Abstenção)</option>
                            <option value="ponderada" <?php echo $assembleia['tipo_votacao'] === 'ponderada' ? 'selected' : ''; ?>>Ponderada (peso por área/propriedade)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Quórum Mínimo (%)</label>
                        <input type="number" name="quorum_minimo" class="form-control" value="<?php echo $assembleia['quorum_minimo']; ?>" min="0" max="100" step="0.01" <?php echo !$podeAlterarTudo ? 'readonly' : ''; ?>>
                        <div class="form-text">Percentual de associados aptos que devem votar para validar.</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <hr class="my-4">
                <div class="d-flex gap-2">
                    <?php if ($podeAlterarTudo): ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar Alterações</button>
                    <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar Datas</button>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>