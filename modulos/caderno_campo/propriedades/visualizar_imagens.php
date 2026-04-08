<?php
$page_title = "Visualizar Imagens da Propriedade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID da propriedade não fornecido.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int)$_GET['id'];
$propriedade = new Propriedade();
$dados_propriedade = $propriedade->getById($id);

if (!$dados_propriedade) {
    echo "<div class='alert alert-danger'>Propriedade não encontrada.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Verificação IDOR
if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
    if ($dados_propriedade['associado_id'] != $user->getAssociadoId()) {
        echo "<div class='alert alert-danger'>Acesso Negado: Esta propriedade pertence a outro produtor.</div>";
        require_once __DIR__ . '/../../../includes/footer.php';
        exit;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Imagens da Propriedade</h1>
            <p class="text-muted"><?php echo htmlspecialchars($dados_propriedade['propriedade']); ?></p>
        </div>
        <div>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedades" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar à Lista
            </a>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/<?php echo $id; ?>/editar" class="btn btn-primary ms-2">
                <i class="bi bi-pencil"></i> Editar Propriedade
            </a>
        </div>
    </div>

    <!-- Informações da Propriedade -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informações da Propriedade</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <p><strong>Nome:</strong><br><?php echo htmlspecialchars($dados_propriedade['propriedade']); ?></p>
                            <p><strong>Área Total:</strong><br><?php echo !empty($dados_propriedade['area_total']) ? number_format($dados_propriedade['area_total'], 2) . ' ha' : 'Não informado'; ?></p>
                            <p><strong>Endereço:</strong><br><?php echo htmlspecialchars($dados_propriedade['endereco'] ?? 'Não informado'); ?></p>
                        </div>
                        <div class="col-sm-6">
                            <p><strong>Cidade:</strong><br><?php echo htmlspecialchars($dados_propriedade['cidade'] ?? 'Não informado'); ?></p>
                            <p><strong>Estado:</strong><br><?php echo htmlspecialchars($dados_propriedade['estado'] ?? 'Não informado'); ?></p>
                            <p><strong>CEP:</strong><br><?php echo htmlspecialchars($dados_propriedade['cep'] ?? 'Não informado'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Localização</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dados_propriedade['latitude']) && !empty($dados_propriedade['longitude'])): ?>
                        <div id="map" style="height: 200px; width: 100%; border-radius: 0.375rem;"></div>
                        <small class="text-muted mt-2 d-block">
                            Coordenadas: <?php echo $dados_propriedade['latitude']; ?>, <?php echo $dados_propriedade['longitude']; ?>
                        </small>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-geo-alt text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">Localização não informada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Galeria de Imagens -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Galeria de Imagens</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Imagem Principal -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Imagem Principal</h6>
                                </div>
                                <div class="card-body text-center">
                                    <?php if (!empty($dados_propriedade['imagem_principal'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/propriedades/<?php echo htmlspecialchars($dados_propriedade['imagem_principal']); ?>" 
                                             class="img-fluid rounded" 
                                             style="max-height: 400px; cursor: pointer;" 
                                             alt="Imagem principal da propriedade"
                                             onclick="abrirModalImagem(this.src, 'Imagem Principal')">
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Clique na imagem para ampliar
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="py-5">
                                            <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                                            <p class="text-muted mt-2">Nenhuma imagem principal cadastrada</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Imagem Secundária -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Imagem Secundária</h6>
                                </div>
                                <div class="card-body text-center">
                                    <?php if (!empty($dados_propriedade['imagem_secundaria'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/propriedades/<?php echo htmlspecialchars($dados_propriedade['imagem_secundaria']); ?>" 
                                             class="img-fluid rounded" 
                                             style="max-height: 400px; cursor: pointer;" 
                                             alt="Imagem secundária da propriedade"
                                             onclick="abrirModalImagem(this.src, 'Imagem Secundária')">
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Clique na imagem para ampliar
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="py-5">
                                            <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                                            <p class="text-muted mt-2">Nenhuma imagem secundária cadastrada</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Observações -->
                    <?php if (!empty($dados_propriedade['observacoes'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">Observações</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($dados_propriedade['observacoes'])); ?></p>
                                    </div>
                                </div>
                            </div>
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
    
    const modal = new bootstrap.Modal(document.getElementById('modalImagem'));
    modal.show();
}

// Inicializar mapa se houver coordenadas
<?php if (!empty($dados_propriedade['latitude']) && !empty($dados_propriedade['longitude'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Aqui você pode adicionar código para inicializar um mapa
    // Por exemplo, usando Google Maps, Leaflet, etc.
    console.log('Coordenadas da propriedade:', <?php echo $dados_propriedade['latitude']; ?>, <?php echo $dados_propriedade['longitude']; ?>);
});
<?php endif; ?>
</script> 