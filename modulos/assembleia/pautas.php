<?php
/**
 * Gerenciar Pautas da Assembleia
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getInstance()->getConnection();

// Verificar permissão: gestor (user_nivel <= 2) OU criador da assembleia
$isGestor = isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] <= 2;
$isCriador = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    // Será verificado após carregar a assembleia
}

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

    $podeGerenciar = $isGestor || $isCriador || $isGestorAssociacao;

    if (!$podeGerenciar) {
        header('Location: ' . SITE_URL . '/login');
        exit;
    }

if ($assembleia['status'] !== 'rascunho') {
    header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId);
    exit;
}

$mensagem = '';
$mensagem_tipo = '';

// Pautas existentes
$stmt = $db->prepare("SELECT * FROM assembleia_pautas WHERE assembleia_id = ? ORDER BY ordem, id");
$stmt->execute([$assembleiaId]);
$pautas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $mensagem = 'Erro de segurança: token inválido.';
        $mensagem_tipo = 'danger';
    } else {
        $acao = $_POST['acao'] ?? '';
        
        if ($acao === 'add_pauta') {
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $tipo = $_POST['tipo'] ?? 'aprovacao';
            $ordem = (int) ($_POST['ordem'] ?? 0);
            $opcoes = null;
            
            if ($tipo === 'eleicao') {
                $opcoesLabels = $_POST['opcao_label'] ?? [];
                $opcoesValores = $_POST['opcao_valor'] ?? [];
                $opcoesArr = [];
                foreach ($opcoesLabels as $i => $label) {
                    if (trim($label) !== '') {
                        $opcoesArr[] = [
                            'label' => trim($label),
                            'valor' => trim($opcoesValores[$i] ?? 'opcao_' . ($i+1))
                        ];
                    }
                }
                if (!empty($opcoesArr)) {
                    $opcoes = json_encode($opcoesArr, JSON_UNESCAPED_UNICODE);
                }
            }
            
            if (empty($titulo)) {
                $mensagem = 'Título da pauta é obrigatório.';
                $mensagem_tipo = 'danger';
            } else {
                $stmt = $db->prepare("INSERT INTO assembleia_pautas (assembleia_id, titulo, descricao, tipo, ordem, opcoes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$assembleiaId, $titulo, $descricao, $tipo, $ordem, $opcoes]);
                $_SESSION['success_message'] = 'Pauta adicionada!';
                header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId . '/pautas');
                exit;
            }
        }
        elseif ($acao === 'delete_pauta') {
            $pautaId = (int) ($_POST['pauta_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM assembleia_pautas WHERE id = ? AND assembleia_id = ?");
            $stmt->execute([$pautaId, $assembleiaId]);
            $_SESSION['success_message'] = 'Pauta removida!';
            header('Location: ' . SITE_URL . '/assembleia/' . $assembleiaId . '/pautas');
            exit;
        }
    }
}

$page_title = "Pautas: " . htmlspecialchars($assembleia['titulo']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-list-task text-primary me-2"></i>Pautas da Assembleia</h1>
            <span class="text-muted"><?php echo htmlspecialchars($assembleia['titulo']); ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $assembleiaId; ?>/publicar" class="btn btn-success" onclick="return confirm('Publicar assembleia e abrir votação?')"><i class="bi bi-send me-1"></i> Publicar e Abrir Votação</a>
            <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagem_tipo; ?>"><?php echo $mensagem; ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Form Nova Pauta -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Adicionar Pauta</h5></div>
        <div class="card-body">
            <form method="post" id="formPauta">
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type="hidden" name="acao" value="add_pauta">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Título da Pauta <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Aprovação das contas do exercício 2023" required maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ordem</label>
                        <input type="number" name="ordem" class="form-control" value="<?php echo count($pautas) + 1; ?>" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="2" placeholder="Detalhes da pauta..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" id="tipoPauta">
                            <option value="aprovacao">Aprovação (Sim/Não/Abstenção)</option>
                            <option value="eleicao">Eleição (múltiplas opções)</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <!-- Opções para Eleição -->
                    <div class="col-12" id="divOpcoesEleicao" style="display:none;">
                        <label class="form-label">Opções de Voto (Eleição)</label>
                        <div id="opcoesContainer">
                            <div class="row g-2 mb-2 opcao-item">
                                <div class="col-md-5"><input type="text" name="opcao_label[]" class="form-control" placeholder="Rótulo (ex: Chapa 1 - João Silva)"></div>
                                <div class="col-md-5"><input type="text" name="opcao_valor[]" class="form-control" placeholder="Valor interno (ex: chapa1)"></div>
                                <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100" onclick="removerOpcao(this)"><i class="bi bi-trash"></i></button></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarOpcao()"><i class="bi bi-plus me-1"></i> Adicionar Opção</button>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Adicionar Pauta</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Pautas -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Pautas Cadastradas (<?php echo count($pautas); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pautas)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">Nenhuma pauta cadastrada. Adicione ao menos uma para publicar a assembleia.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Opções</th>
                            <th style="width:100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pautas as $p): ?>
                        <tr>
                            <td class="text-center fw-bold"><?php echo $p['ordem']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['titulo']); ?></strong>
                                <?php if (!empty($p['descricao'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($p['descricao'], 0, 100)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $p['tipo']==='eleicao'?'info':($p['tipo']==='aprovacao'?'success':'secondary'); ?>">
                                    <?php echo ucfirst($p['tipo']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($p['tipo'] === 'eleicao' && $p['opcoes']): ?>
                                <?php $ops = json_decode($p['opcoes'], true); ?>
                                <span class="badge bg-light text-dark"><?php echo count($ops); ?> opções</span>
                                <div class="mt-1 small text-muted">
                                    <?php foreach ($ops as $o): ?>
                                    <div>• <?php echo htmlspecialchars($o['label']); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">Sim / Não / Abstenção</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <form method="post" class="d-inline" onsubmit="return confirm('Remover esta pauta?');">
                                    <?php echo CSRFProtection::getTokenField(); ?>
                                    <input type="hidden" name="acao" value="delete_pauta">
                                    <input type="hidden" name="pauta_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('tipoPauta').addEventListener('change', function() {
    document.getElementById('divOpcoesEleicao').style.display = this.value === 'eleicao' ? 'block' : 'none';
});

function adicionarOpcao() {
    const container = document.getElementById('opcoesContainer');
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 opcao-item';
    div.innerHTML = `
        <div class="col-md-5"><input type="text" name="opcao_label[]" class="form-control" placeholder="Rótulo (ex: Chapa 1 - João Silva)"></div>
        <div class="col-md-5"><input type="text" name="opcao_valor[]" class="form-control" placeholder="Valor interno (ex: chapa1)"></div>
        <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100" onclick="removerOpcao(this)"><i class="bi bi-trash"></i></button></div>
    `;
    container.appendChild(div);
}

function removerOpcao(btn) {
    const items = document.querySelectorAll('.opcao-item');
    if (items.length > 1) {
        btn.closest('.opcao-item').remove();
    } else {
        alert('Mantenha ao menos uma opção.');
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>