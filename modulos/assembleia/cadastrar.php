<?php
/**
 * Nova Assembleia
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

$mensagem = '';
$mensagem_tipo = '';

$associacoes = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim = $_POST['data_fim'] ?? '';
        $associacao_id = !empty($_POST['associacao_id']) ? (int) $_POST['associacao_id'] : null;
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
                INSERT INTO assembleias (titulo, descricao, data_inicio, data_fim, associacao_id, tipo_votacao, quorum_minimo, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'rascunho')
            ");
            $stmt->execute([$titulo, $descricao, $data_inicio, $data_fim, $associacao_id, $tipo_votacao, $quorum_minimo, $_SESSION['user_id']]);
            $assembleiaId = (int) $db->lastInsertId();
            
            $_SESSION['success_message'] = 'Assembleia criada com sucesso! Agora adicione as pautas.';
            header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId . '/pautas');
            exit;
        }
    }
}

$page_title = "Nova Assembleia";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-plus-circle text-primary me-2"></i>Nova Assembleia</h1>
            <span class="text-muted">Crie uma nova assembleia para votação online</span>
        </div>
        <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo $mensagem; ?></div>
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
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Assembleia Geral Ordinária 2024" required maxlength="255">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Descrição da assembleia, pautas previstas, observações..."></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Data/Hora Início <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_inicio" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Data/Hora Fim <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_fim" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Associação (opcional)</label>
                        <select name="associacao_id" class="form-select">
                            <option value="">Todas as associações</option>
                            <?php foreach ($associacoes as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['nome'].' ('.$a['sigla'].')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Se vazio, todos associados ativos podem votar.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Votação</label>
                        <select name="tipo_votacao" class="form-select">
                            <option value="simples">Simples (1 associado = 1 voto)</option>
                            <option value="ponderada">Ponderada (peso por área/propriedade)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Quórum Mínimo (%)</label>
                        <input type="number" name="quorum_minimo" class="form-control" value="50.00" min="0" max="100" step="0.01">
                        <div class="form-text">Percentual de associados aptos que devem votar para validar.</div>
                    </div>
                </div>
                
                <hr class="my-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Criar Assembleia</button>
                    <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>