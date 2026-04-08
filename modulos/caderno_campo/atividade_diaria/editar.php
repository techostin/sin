<?php
$page_title = "Editar Atividade Diária";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Buscar dados para os selects
$propriedades = [];
$lavouras = [];
$associados = [];
$categorias = [];
$insumos = [];
$atividade = null;
try {
    $db = Database::getInstance()->getConnection();
    // Buscar atividade
    $stmt = $db->prepare("SELECT * FROM atividades_diarias WHERE id = ?");
    $stmt->execute([$id]);
    $atividade = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$atividade) {
        echo "<div class='alert alert-danger'>Atividade não encontrada.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
    // Verificação IDOR
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
        if ($atividade['associado_id'] != $user->getAssociadoId()) {
            echo "<div class='alert alert-danger'>Acesso Negado: Esta atividade pertence a outro produtor.</div>";
            require_once __DIR__ . '/../../../includes/footer.php';
            exit;
        }
    }

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
    echo "<div class='alert alert-danger'>Erro ao buscar dados: " . $e->getMessage() . "</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
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
    $remover_imagem = isset($_POST['remover_imagem']) && $_POST['remover_imagem'] == 1;
    
    // Permitir hora_fim vazio se status for 'Em andamento'
    if ($status === 'Em andamento' && empty($hora_fim)) {
        $hora_fim = null;
    }
    
    try {
        // Iniciar transação
        $db->beginTransaction();
        
        // Processar upload de nova imagem, se fornecida
        $imagem_path = $atividade['imagem_diaria'] ?? '';
        
        // Se marcar para remover a imagem atual
        if ($remover_imagem && !empty($imagem_path)) {
            // Remover o arquivo físico se existir
            $caminho_absoluto = __DIR__ . '/../../..' . '/' . $imagem_path;
            if (file_exists($caminho_absoluto)) {
                unlink($caminho_absoluto);
            }
            $imagem_path = '';
        }
        
        // Verificar se foi enviada uma nova imagem
        if (isset($_FILES['imagem_diaria']) && $_FILES['imagem_diaria']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../../../uploads/atividades/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Se já existir uma imagem antiga, removê-la
            if (!empty($imagem_path)) {
                $caminho_antigo = __DIR__ . '/../../..' . '/' . $imagem_path;
                if (file_exists($caminho_antigo)) {
                    unlink($caminho_antigo);
                }
            }
            
            // Processar o upload da nova imagem
            $file_extension = pathinfo($_FILES['imagem_diaria']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('atividade_') . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['imagem_diaria']['tmp_name'], $target_path)) {
                $imagem_path = 'uploads/atividades/' . $file_name;
            } else {
                throw new Exception('Não foi possível fazer o upload da imagem.');
            }
        }
        
        // Atualizar os dados da atividade, incluindo o caminho da imagem
        $sql = "UPDATE atividades_diarias SET 
                   nome=?, descricao=?, data_atividade=?, hora_inicio=?, hora_fim=?, 
                   tipo_atividade=?, status=?, propriedade_id=?, lavoura_id=?, 
                   associado_id=?, categoria_id=?, insumo_utilisado=?, observações=?, 
                   imagem_diaria=?, atualizado_em=NOW() 
                WHERE id=?";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $nome, $descricao, $data_atividade, $hora_inicio, $hora_fim, 
            $tipo_atividade, $status, $propriedade_id, $lavoura_id, 
            $associado_id, $categoria_id, $insumo_utilisado, $observacoes,
            $imagem_path, $id
        ]);
        
        // Confirmar a transação
        $db->commit();
        
        $mensagem = '<div class="alert alert-success">Atividade atualizada com sucesso!</div>';
        
        // Recarregar dados do banco após update
        $stmt = $db->prepare("SELECT * FROM atividades_diarias WHERE id = ?");
        $stmt->execute([$id]);
        $atividade = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Em caso de erro, desfaz as alterações
        $db->rollBack();
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
        
        // Se for erro de upload, adiciona mais detalhes
        if ($e->getCode() == UPLOAD_ERR_INI_SIZE) {
            $mensagem = '<div class="alert alert-danger">O arquivo enviado excede o tamanho máximo permitido.</div>';
        }
    }
}
?>
<h1 class="mt-4">Editar Atividade Diária</h1>
<?php if ($mensagem) echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Atividade</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($atividade['nome']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tipo_atividade" class="form-label">Tipo de Atividade</label>
                        <select class="form-select" id="tipo_atividade" name="tipo_atividade" required>
                            <?php $tipos = ['adubação','capina','irrigação','encanteramento','aplicação de bio insumo','colheita','plantio','poda'];
                            foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo; ?>" <?php if($atividade['tipo_atividade']===$tipo) echo 'selected'; ?>><?php echo ucfirst($tipo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($atividade['descricao']); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="data_atividade" class="form-label">Data da Atividade</label>
                        <input type="date" class="form-control" id="data_atividade" name="data_atividade" value="<?php echo htmlspecialchars($atividade['data_atividade']); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="hora_inicio" class="form-label">Hora de Início</label>
                        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" value="<?php echo htmlspecialchars($atividade['hora_inicio']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="hora_fim" class="form-label">Hora de Fim</label>
                        <input type="time" class="form-control" id="hora_fim" name="hora_fim" value="<?php echo htmlspecialchars($atividade['hora_fim']); ?>">
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
                                <option value="<?php echo $prop['id']; ?>" <?php if($atividade['propriedade_id']==$prop['id']) echo 'selected'; ?>><?php echo htmlspecialchars($prop['propriedade']); ?></option>
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
                                <option value="<?php echo $lav['id']; ?>" <?php if($atividade['lavoura_id']==$lav['id']) echo 'selected'; ?>><?php echo htmlspecialchars($lav['nome']); ?></option>
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
                                <option value="<?php echo $assoc['id']; ?>" <?php if($atividade['associado_id']==$assoc['id']) echo 'selected'; ?>><?php echo htmlspecialchars($assoc['nome']); ?></option>
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
                                <option value="<?php echo $cat['id']; ?>" style="color: <?php echo htmlspecialchars($cat['cor']); ?>;" <?php if($atividade['categoria_id']==$cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['nome']); ?></option>
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
                                <option value="<?php echo htmlspecialchars($insumo['nome']); ?>" <?php if($atividade['insumo_utilisado']==$insumo['nome']) echo 'selected'; ?>><?php echo htmlspecialchars($insumo['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Pendente" <?php if($atividade['status']==='Pendente') echo 'selected'; ?>>Pendente</option>
                            <option value="Em andamento" <?php if($atividade['status']==='Em andamento') echo 'selected'; ?>>Em andamento</option>
                            <option value="Concluído" <?php if($atividade['status']==='Concluído') echo 'selected'; ?>>Concluído</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($atividade['observacoes'] ?? ''); ?></textarea>
            </div>
            <?php if (!empty($atividade['imagem_diaria'])): ?>
                <div class="mb-3">
                    <label class="form-label">Imagem Atual</label>
                    <div class="text-center">
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($atividade['imagem_diaria']); ?>" 
                             class="img-fluid rounded mb-2" 
                             style="max-height: 200px;" 
                             alt="Imagem da atividade">
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-danger btn-sm p-0 px-2" onclick="document.getElementById('remover_imagem').checked = true; this.closest('form').submit();">
                                Remover Imagem
                            </button>
                        </div>
                        <div class="form-check d-none">
                            <input class="form-check-input" type="checkbox" id="remover_imagem" name="remover_imagem" value="1">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="imagem_diaria" class="form-label"><?php echo empty($atividade['imagem_diaria']) ? 'Enviar Imagem' : 'Alterar Imagem'; ?></label>
                <input type="file" class="form-control" id="imagem_diaria" name="imagem_diaria" accept="image/*">
                <div class="form-text">Aceita apenas imagens (JPG, PNG, GIF, etc.)</div>
            </div>
            
            <?php if (!empty($atividade['atualizado_em'])): ?>
                <div class="mb-3">
                    <label class="form-label">Última atualização</label>
                    <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($atividade['atualizado_em'])); ?>" readonly>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>/atividades-diarias" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?> 