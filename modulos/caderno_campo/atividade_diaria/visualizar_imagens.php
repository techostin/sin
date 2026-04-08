<?php

$page_title = "Visualizar Imagens da Atividade Diária";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/AtividadeDiaria.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID da atividade não fornecido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$atividade = new AtividadeDiaria();
$dados_atividade = $atividade->getById($id);

if (!$dados_atividade) {
    echo "<div class='alert alert-danger'>Atividade não encontrada.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Verificação IDOR
if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
    if ($dados_atividade['associado_id'] != $user->getAssociadoId()) {
        echo "<div class='alert alert-danger'>Acesso Negado: Esta atividade pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}

// Buscar dados adicionais
$propriedade_nome = '';
$lavoura_nome = '';
$categoria_nome = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar nome da propriedade
    if (!empty($dados_atividade['propriedade_id'])) {
        $stmt = $db->prepare("SELECT nome FROM propriedades WHERE id = ?");
        $stmt->execute([$dados_atividade['propriedade_id']]);
        $propriedade = $stmt->fetch(PDO::FETCH_ASSOC);
        $propriedade_nome = $propriedade ? $propriedade['nome'] : '';
    }
    
    // Buscar nome da lavoura
    if (!empty($dados_atividade['lavoura_id'])) {
        $stmt = $db->prepare("SELECT nome FROM lavouras WHERE id = ?");
        $stmt->execute([$dados_atividade['lavoura_id']]);
        $lavoura = $stmt->fetch(PDO::FETCH_ASSOC);
        $lavoura_nome = $lavoura ? $lavoura['nome'] : '';
    }
    
    // Buscar nome da categoria
    if (!empty($dados_atividade['categoria_id'])) {
        $stmt = $db->prepare("SELECT nome FROM categorias_atividades WHERE id = ?");
        $stmt->execute([$dados_atividade['categoria_id']]);
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
        $categoria_nome = $categoria ? $categoria['nome'] : '';
    }
} catch (PDOException $e) {
    // Erro ao buscar dados adicionais, mas continua o carregamento
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Imagens da Atividade</h1>
            <p class="text-muted"><?php echo htmlspecialchars($dados_atividade['nome']); ?></p>
        </div>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividades-diarias" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar à Lista
            </a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/atividade-diaria/<?php echo $id; ?>/editar" class="btn btn-primary ms-2">
                <i class="bi bi-pencil"></i> Editar Atividade
            </a>
        </div>
    </div>

    <!-- Informações da Atividade -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da Atividade</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <p><strong>Nome:</strong><br><?php echo htmlspecialchars($dados_atividade['nome']); ?></p>
                            <p><strong>Data:</strong><br><?php echo $dados_atividade['data_atividade'] ? date('d/m/Y', strtotime($dados_atividade['data_atividade'])) : 'Não informado'; ?></p>
                            <p><strong>Hora Início:</strong><br><?php echo !empty($dados_atividade['hora_inicio']) ? date('H:i', strtotime($dados_atividade['hora_inicio'])) : 'Não informado'; ?></p>
                            <p><strong>Status:</strong><br>
                                <?php 
                                $statusClass = '';
                                switch($dados_atividade['status']) {
                                    case 'Pendente': $statusClass = 'badge bg-warning'; break;
                                    case 'Em andamento': $statusClass = 'badge bg-info'; break;
                                    case 'Concluído': $statusClass = 'badge bg-success'; break;
                                    default: $statusClass = 'badge bg-secondary';
                                }
                                ?>
                                <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($dados_atividade['status']); ?></span>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <p><strong>Tipo:</strong><br><?php echo htmlspecialchars($dados_atividade['tipo_atividade']); ?></p>
                            <p><strong>Propriedade:</strong><br><?php echo !empty($propriedade_nome) ? htmlspecialchars($propriedade_nome) : 'Não informada'; ?></p>
                            <p><strong>Lavoura:</strong><br><?php echo !empty($lavoura_nome) ? htmlspecialchars($lavoura_nome) : 'Não informada'; ?></p>
                            <p><strong>Categoria:</strong><br><?php echo !empty($categoria_nome) ? htmlspecialchars($categoria_nome) : 'Não informada'; ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($dados_atividade['descricao'])): ?>
                        <hr>
                        <p><strong>Descrição:</strong><br><?php echo nl2br(htmlspecialchars($dados_atividade['descricao'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($dados_atividade['insumo_utilisado'])): ?>
                        <hr>
                        <p><strong>Insumo Utilizado:</strong><br><?php echo htmlspecialchars($dados_atividade['insumo_utilisado']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($dados_atividade['observações'])): ?>
                        <hr>
                        <p><strong>Observações:</strong><br><?php echo nl2br(htmlspecialchars($dados_atividade['observações'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Imagem da Atividade</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($dados_atividade['imagem_diaria'])): ?>
                        <img src="<?php echo SITE_URL . '/' . htmlspecialchars($dados_atividade['imagem_diaria']); ?>" 
                             class="img-fluid rounded" 
                             style="max-height: 400px; cursor: pointer;" 
                             alt="Imagem da atividade"
                             onclick="abrirModalImagem(this.src, 'Imagem da Atividade')">
                        <div class="mt-2">
                            <small class="text-muted">
                                Clique na imagem para ampliar
                            </small>
                            <div class="mt-2">
                                <a href="<?php echo SITE_URL . '/' . htmlspecialchars($dados_atividade['imagem_diaria']); ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   download>
                                    <i class="bi bi-download"></i> Baixar Imagem
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-5">
                            <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                            <p class="text-muted mt-2">Nenhuma imagem cadastrada para esta atividade</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para visualizar imagem ampliada -->
<div class="modal fade" id="modalImagem" tabindex="-1" aria-labelledby="modalImagemLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImagemLabel">Visualizar Imagem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagemModal" src="" class="img-fluid" alt="Imagem ampliada">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <a id="downloadImagem" href="" download class="btn btn-primary">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>

<script>
function abrirModalImagem(src, titulo) {
    document.getElementById('modalImagemLabel').textContent = titulo;
    document.getElementById('imagemModal').src = src;
    document.getElementById('downloadImagem').href = src;
    
    // Ajustar o tamanho da imagem no modal
    const img = new Image();
    img.onload = function() {
        const modal = new bootstrap.Modal(document.getElementById('modalImagem'));
        modal.show();
    };
    img.src = src;
}
</script>
