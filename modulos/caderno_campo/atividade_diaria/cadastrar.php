<?php
$page_title = "Cadastrar Atividade Diária";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/AtividadeDiaria.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/CategoriaAtividade.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar dados para os selects
$propriedades = [];
$lavouras = [];
$associados = [];
$categorias = [];
$insumos = [];
try {
    $db = Database::getInstance()->getConnection();
    
    if ($user->hasPermission(NIVEL_GESTOR)) {
        // Buscar propriedades
        $stmt = $db->prepare("SELECT id, propriedade FROM propriedades ORDER BY propriedade ASC");
        $stmt->execute();
        $propriedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar lavouras
        $stmt = $db->prepare("SELECT id, nome FROM lavouras ORDER BY nome ASC");
        $stmt->execute();
        $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar associados
        $stmt = $db->prepare("SELECT id, nome FROM associados ORDER BY nome ASC");
        $stmt->execute();
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $associado_id = $user->getAssociadoId();
        if (!$associado_id) $associado_id = -1;

        // Buscar propriedades restritas
        $stmt = $db->prepare("SELECT id, propriedade FROM propriedades WHERE associado_id = ? ORDER BY propriedade ASC");
        $stmt->execute([$associado_id]);
        $propriedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar lavouras restritas
        $stmt = $db->prepare("SELECT l.id, l.nome FROM lavouras l INNER JOIN propriedades p ON l.propriedade_id = p.id WHERE p.associado_id = ? ORDER BY l.nome ASC");
        $stmt->execute([$associado_id]);
        $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar apenas o próprio associado
        $stmt = $db->prepare("SELECT id, nome FROM associados WHERE id = ?");
        $stmt->execute([$associado_id]);
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Buscar categorias
    $stmt = $db->prepare("SELECT id, nome, cor FROM categorias_atividades ORDER BY nome ASC");
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar insumos
    $stmt = $db->prepare("SELECT id, nome FROM insumos ORDER BY nome ASC");
    $stmt->execute();
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Tratar erro se necessário
}

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $data_atividade = $_POST['data_atividade'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    $tipo_atividade = $_POST['tipo_atividade'] ?? 'adubação';
    $status = $_POST['status'] ?? 'Pendente';
    $propriedade_id = $_POST['propriedade_id'] ?? null;
    $lavoura_id = $_POST['lavoura_id'] ?? null;
    $associado_id = $_POST['associado_id'] ?? null;
    $categoria_id = $_POST['categoria_id'] ?? null;
    $insumo_utilisado = $_POST['insumo_utilisado'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    $imagem_path = '';
    
    // Processar upload da imagem
    if (isset($_FILES['imagem_diaria']) && $_FILES['imagem_diaria']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../../../uploads/atividades/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['imagem_diaria']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('atividade_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['imagem_diaria']['tmp_name'], $target_path)) {
            $imagem_path = 'uploads/atividades/' . $file_name;
        } else {
            $mensagem = '<div class="alert alert-warning">Aviso: Não foi possível fazer o upload da imagem.</div>';
        }
    }
    
    // Permitir hora_fim vazio se status for 'Em andamento'
    if ($status === 'Em andamento' && empty($hora_fim)) {
        $hora_fim = null;
    }

    try {
        $stmt = $db->prepare("INSERT INTO atividades_diarias (nome, descricao, data_atividade, hora_inicio, hora_fim, tipo_atividade, status, propriedade_id, lavoura_id, associado_id, categoria_id, insumo_utilisado, observações, imagem_diaria) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $descricao, $data_atividade, $hora_inicio, $hora_fim, $tipo_atividade, $status, $propriedade_id, $lavoura_id, $associado_id, $categoria_id, $insumo_utilisado, $observacoes, $imagem_path]);
        $mensagem = '<div class="alert alert-success">Atividade cadastrada com sucesso!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao cadastrar: ' . $e->getMessage() . '</div>';
    }
}
?>

<h1 class="mt-4">Cadastrar Atividade Diária</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Atividade</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tipo_atividade" class="form-label">Tipo de Atividade</label>
                        <select class="form-select" id="tipo_atividade" name="tipo_atividade" required>
                            <option value="adubação">Adubação</option>
                            <option value="capina">Capina</option>
                            <option value="irrigação">Irrigação</option>
                            <option value="encanteramento">Encanteramento</option>
                            <option value="aplicação de bio insumo">Aplicação de Bio Insumo</option>
                            <option value="colheita">Colheita</option>
                            <option value="plantio">Plantio</option>
                            <option value="poda">Poda</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="data_atividade" class="form-label">Data da Atividade</label>
                        <input type="date" class="form-control" id="data_atividade" name="data_atividade" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="hora_inicio" class="form-label">Hora de Início</label>
                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="hora_fim" class="form-label">Hora de Fim</label>
                        <input type="time" class="form-control" id="hora_fim" name="hora_fim">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="propriedade_id" class="form-label">Propriedade</label>
                        <select class="form-select" id="propriedade_id" name="propriedade_id">
                            <option value="">Selecione</option>
                            <?php foreach ($propriedades as $prop): ?>
                                <option value="<?php echo $prop['id']; ?>"><?php echo htmlspecialchars($prop['propriedade']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="lavoura_id" class="form-label">Lavoura</label>
                        <select class="form-select" id="lavoura_id" name="lavoura_id">
                            <option value="">Selecione</option>
                            <?php foreach ($lavouras as $lav): ?>
                                <option value="<?php echo $lav['id']; ?>"><?php echo htmlspecialchars($lav['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="associado_id" class="form-label">Associado</label>
                        <select class="form-select" id="associado_id" name="associado_id">
                            <option value="">Selecione</option>
                            <?php foreach ($associados as $assoc): ?>
                                <option value="<?php echo $assoc['id']; ?>"><?php echo htmlspecialchars($assoc['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id">
                            <option value="">Selecione</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" style="color: <?php echo htmlspecialchars($cat['cor']); ?>;">
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="insumo_utilisado" class="form-label">Insumo Utilizado</label>
                        <select class="form-select" id="insumo_utilisado" name="insumo_utilisado">
                            <option value="">Selecione</option>
                            <?php foreach ($insumos as $insumo): ?>
                                <option value="<?php echo htmlspecialchars($insumo['nome']); ?>"><?php echo htmlspecialchars($insumo['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Pendente">Pendente</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Concluído">Concluído</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
            </div>
            
            <div class="mb-3">
                <label for="imagem_diaria" class="form-label">Imagem da Atividade</label>
                <input type="file" class="form-control" id="imagem_diaria" name="imagem_diaria" accept="image/*">
                <div class="form-text">Aceita apenas imagens (JPG, PNG, GIF, etc.)</div>
            </div>
            
            <button type="submit" class="btn btn-success">Salvar</button>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividades-diarias" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
