<?php
$page_title = "Editar Ocorrência";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Ocorrencia.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Insumo.php';
require_once __DIR__ . '/../../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../../classes/ImageUploader.php';

$message = '';
$message_type = '';
$dados_ocorrencia = null;
$propriedades = [];
$lavouras = [];
$insumos = [];
$id = null;

// Verificar se o ID foi passado
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $message = 'ID da ocorrência não informado.';
    $message_type = 'danger';
} else {
    $id = (int)$_GET['id'];
    $ocorrencia_obj = new Ocorrencia();
    $propriedade_obj = new Propriedade();
    $lavoura_obj = new Lavoura();
    $insumo_obj = new Insumo();

    // Buscar dados da ocorrência
    $dados_ocorrencia = $ocorrencia_obj->getById($id);
    if (!$dados_ocorrencia) {
        $message = 'Ocorrência não encontrada.';
        $message_type = 'danger';
    } else {
        // IDOR Check: Se for nível Usuário, deve ser dono da ocorrência
        if (!$user->hasPermission(NIVEL_GESTOR)) {
            $associado_id = $user->getAssociadoId();
            if (!$associado_id || $dados_ocorrencia['associado_id'] != $associado_id) {
                $message = 'Acesso Negado: Esta ocorrência pertence a outro produtor.';
                $message_type = 'danger';
                $dados_ocorrencia = null; // Prevent form rendering
            }
        }
        
        if ($dados_ocorrencia) {
            // Buscar listas (com isolamento)
            $associado_id = $user->getAssociadoId();
            if (!$associado_id) $associado_id = -1;
            
            if ($user->hasPermission(NIVEL_GESTOR)) {
                $propriedades = $propriedade_obj->getAll();
                $insumos = $insumo_obj->getAll();
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id, nome FROM lavouras ORDER BY nome ASC");
                $stmt->execute();
                $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $propriedades = $propriedade_obj->getByAssociadoId($associado_id);
                $insumos = $insumo_obj->getByAssociadoId($associado_id);
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT l.id, l.nome FROM lavouras l INNER JOIN propriedades p ON l.propriedade_id = p.id WHERE p.associado_id = ? ORDER BY l.nome ASC");
                $stmt->execute([$associado_id]);
                $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Processar formulário
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $dados = $_POST;
                $dados['id'] = $id;
                $dados['associado_id'] = $dados_ocorrencia['associado_id'];
                
                // Processar upload da foto (se houver nova)
                $foto_url = $dados_ocorrencia['foto_url'];
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $uploader = new ImageUploader(__DIR__ . '/../../../uploads/ocorrencias/');
                    $uploader->setConfig(['maxWidth' => 1600, 'maxHeight' => 1200]);
                    $uploadResult = $uploader->uploadImage($_FILES['foto'], 'ocorrencia');
                    if ($uploadResult['success']) {
                        $foto_url = 'uploads/ocorrencias/' . $uploadResult['filename'];
                    }
                }
                $dados['foto_url'] = $foto_url;
                
                if ($ocorrencia_obj->update($dados)) {
                    $message = "Ocorrência atualizada com sucesso!";
                    $message_type = "success";
                    // Recarregar dados após atualização
                    $dados_ocorrencia = $ocorrencia_obj->getById($id);
                } else {
                    $message = "Erro ao atualizar a ocorrência.";
                    $message_type = "danger";
                }
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h1>Editar Ocorrência</h1>
    <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencias" class="btn btn-success">
        <i class="bi bi-list"></i> Listar Ocorrências
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif;

if ($dados_ocorrencia): ?>
<form action="" method="POST" enctype="multipart/form-data" class="row g-3 mb-5">
    <?php echo CSRFProtection::getTokenField(); ?>
    <div class="col-md-6">
        <label for="propriedade_id" class="form-label">Propriedade *</label>
        <select class="form-select" id="propriedade_id" name="propriedade_id" required>
            <option value="">Selecione uma Propriedade</option>
            <?php foreach ($propriedades as $prop): ?>
                <option value="<?php echo $prop['id']; ?>" <?php echo ($dados_ocorrencia['propriedade_id'] == $prop['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($prop['propriedade']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-6">
        <label for="lavoura_id" class="form-label">Lavoura</label>
        <select class="form-select" id="lavoura_id" name="lavoura_id">
            <option value="">Selecione uma Lavoura (opcional)</option>
            <?php foreach ($lavouras as $lav): ?>
                <option value="<?php echo $lav['id']; ?>" <?php echo ($dados_ocorrencia['lavoura_id'] == $lav['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lav['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-4">
        <label for="tipo" class="form-label">Tipo *</label>
        <select class="form-select" id="tipo" name="tipo" required>
            <option value="">Selecione o Tipo</option>
            <option value="Praga" <?php echo ($dados_ocorrencia['tipo'] == 'Praga') ? 'selected' : ''; ?>>Praga</option>
            <option value="Doença" <?php echo ($dados_ocorrencia['tipo'] == 'Doença') ? 'selected' : ''; ?>>Doença</option>
            <option value="Fisiológica/Nutricional" <?php echo ($dados_ocorrencia['tipo'] == 'Fisiológica/Nutricional') ? 'selected' : ''; ?>>Fisiológica/Nutricional</option>
            <option value="Climatológica" <?php echo ($dados_ocorrencia['tipo'] == 'Climatológica') ? 'selected' : ''; ?>>Climatológica</option>
            <option value="Outro" <?php echo ($dados_ocorrencia['tipo'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
        </select>
    </div>
    
    <div class="col-md-8">
        <label for="nome_comum" class="form-label">Nome Comum *</label>
        <input type="text" class="form-control" id="nome_comum" name="nome_comum" value="<?php echo htmlspecialchars($dados_ocorrencia['nome_comum'] ?? ''); ?>" required>
    </div>
    
    <div class="col-md-4">
        <label for="data_deteccao" class="form-label">Data de Detecção *</label>
        <input type="date" class="form-control" id="data_deteccao" name="data_deteccao" value="<?php echo htmlspecialchars($dados_ocorrencia['data_deteccao'] ?? ''); ?>" required>
    </div>
    
    <div class="col-md-4">
        <label for="severidade" class="form-label">Severidade *</label>
        <select class="form-select" id="severidade" name="severidade" required>
            <option value="Baixa" <?php echo ($dados_ocorrencia['severidade'] == 'Baixa') ? 'selected' : ''; ?>>Baixa</option>
            <option value="Média" <?php echo ($dados_ocorrencia['severidade'] == 'Média') ? 'selected' : ''; ?>>Média</option>
            <option value="Alta" <?php echo ($dados_ocorrencia['severidade'] == 'Alta') ? 'selected' : ''; ?>>Alta</option>
        </select>
    </div>
    
    <div class="col-md-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($dados_ocorrencia['descricao'] ?? ''); ?></textarea>
    </div>
    
    <div class="col-md-12">
        <label for="acao_manejo" class="form-label">Ação de Manejo Adotada</label>
        <textarea class="form-control" id="acao_manejo" name="acao_manejo" rows="3"><?php echo htmlspecialchars($dados_ocorrencia['acao_manejo'] ?? ''); ?></textarea>
    </div>
    
    <div class="col-md-6">
        <label for="insumo_utilizado_id" class="form-label">Insumo Utilizado</label>
        <select class="form-select" id="insumo_utilizado_id" name="insumo_utilizado_id">
            <option value="">Selecione um Insumo (opcional)</option>
            <?php foreach ($insumos as $ins): ?>
                <option value="<?php echo $ins['id']; ?>" <?php echo ($dados_ocorrencia['insumo_utilizado_id'] == $ins['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ins['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-md-6">
        <label for="foto" class="form-label">Foto de Evidência</label>
        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
        <?php if ($dados_ocorrencia['foto_url']): ?>
            <div class="mt-2">
                <img src="<?php echo SITE_URL; ?>/<?php echo $dados_ocorrencia['foto_url']; ?>" alt="Foto da Ocorrência" style="max-width: 200px; max-height: 200px;">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Atualizar Ocorrência</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/ocorrencias" class="btn btn-success">
            <i class="bi bi-list"></i> Listar Ocorrências
        </a>
    </div>
</form>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
