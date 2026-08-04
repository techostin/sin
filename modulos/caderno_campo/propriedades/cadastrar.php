<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/User.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/Associado.php';
require_once __DIR__ . '/../../../classes/ImageUploader.php';
require_once __DIR__ . '/../../../classes/Database.php';

$user = new User();
$message = '';
$message_type = '';
$associado_id_via_get = null;
$associado_nome = '';

// Verificação de autenticação
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login.php");
    exit;
}

// Verificar se há um associado_id via GET ou POST (vinculação direta)
$associado_id_para_buscar = null;
if (isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
    $associado_id_via_get = intval($_GET['associado_id']);
    $associado_id_para_buscar = $associado_id_via_get;
} elseif (isset($_POST['associado_id']) && is_numeric($_POST['associado_id'])) {
    $associado_id_via_get = intval($_POST['associado_id']);
    $associado_id_para_buscar = $associado_id_via_get;
}

// Buscar nome do associado para exibir
if ($associado_id_para_buscar) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT nome FROM associados WHERE id = ?");
        $stmt->execute([$associado_id_para_buscar]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($associado) {
            $associado_nome = $associado['nome'];
        }
    } catch (PDOException $e) {
        // Se não encontrar o associado, ignora
    }
}

// Usuário do tipo "usuario" pode cadastrar apenas SUA propriedade.
// Bloqueia somente quando tenta cadastrar para outro associado (associado_id via GET/POST != associado do usuário logado).
$isRestrictedUser = false;
$meu_associado_id = null; // Associado_id do usuário logado

if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario') {
    // Determinar o associado_id do usuário logado
    if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'usuarios') {
        // Usuário logou pela tabela 'usuarios', buscar o associado_id vinculado
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resultado && !empty($resultado['associado_id'])) {
                $meu_associado_id = intval($resultado['associado_id']);
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar associado_id do usuário: " . $e->getMessage());
        }
    } else {
        // Usuário logou pela tabela 'associados', o user_id já é o associado_id
        $meu_associado_id = $_SESSION['user_id'];
    }
    
    // Verificar se está tentando cadastrar para outro associado
    $id_vinculo = null;
    if (isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
        $id_vinculo = (int) $_GET['associado_id'];
    } elseif (isset($_POST['associado_id']) && is_numeric($_POST['associado_id'])) {
        $id_vinculo = (int) $_POST['associado_id'];
    }
    
    if ($id_vinculo !== null && $meu_associado_id !== null && $id_vinculo != $meu_associado_id) {
        $isRestrictedUser = true;
    }
}

// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($isRestrictedUser) {
        header('HTTP/1.1 403 Forbidden');
        die("Acesso negado.");
    }

    $propriedade = new Propriedade();
    $imageUploader = new ImageUploader();

    $dados_propriedade = [];
    foreach ($_POST as $key => $value) {
        $dados_propriedade[$key] = htmlspecialchars(strip_tags(trim($value)));
    }

    // Associado_id: se veio via GET ou POST, usa esse ID; senão, busca o associado_id do usuário logado
    if (isset($_POST['associado_id']) && is_numeric($_POST['associado_id'])) {
        $dados_propriedade['associado_id'] = intval($_POST['associado_id']);
    } elseif (isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
        $dados_propriedade['associado_id'] = intval($_GET['associado_id']);
    } else {
        // Determinar o associado_id correto baseado na tabela de login
        // Reutilizar $meu_associado_id se já foi calculado, senão calcular agora
        $associado_id_final = $meu_associado_id;
        
        if ($associado_id_final === null) {
            if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'usuarios') {
                // Usuário logou pela tabela 'usuarios', buscar o associado_id vinculado
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($resultado && !empty($resultado['associado_id'])) {
                        $associado_id_final = intval($resultado['associado_id']);
                    }
                } catch (PDOException $e) {
                    error_log("Erro ao buscar associado_id do usuário: " . $e->getMessage());
                }
            } else {
                // Usuário logou pela tabela 'associados', o user_id já é o associado_id
                $associado_id_final = $_SESSION['user_id'];
            }
        }
        
        if (!$associado_id_final) {
            $message = "Erro: Não foi possível identificar o associado vinculado à sua conta. Entre em contato com o administrador.";
            $message_type = "danger";
        } else {
            $dados_propriedade['associado_id'] = $associado_id_final;
        }
    }

    // Processar upload de imagens
    $imagem_principal = null;
    $imagem_secundaria = null;

    // Upload da imagem principal
    if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
        $result = $imageUploader->uploadImage($_FILES['imagem_principal'], 'principal');
        if ($result['success']) {
            $imagem_principal = $result['filename'];
        } else {
            $message = "Erro na imagem principal: " . $result['message'];
            $message_type = "danger";
        }
    }

    // Upload da imagem secundária
    if (isset($_FILES['imagem_secundaria']) && $_FILES['imagem_secundaria']['error'] === UPLOAD_ERR_OK) {
        $result = $imageUploader->uploadImage($_FILES['imagem_secundaria'], 'secundaria');
        if ($result['success']) {
            $imagem_secundaria = $result['filename'];
        } else {
            $message = "Erro na imagem secundária: " . $result['message'];
            $message_type = "danger";
        }
    }

    // Adicionar nomes das imagens aos dados
    $dados_propriedade['imagem_principal'] = $imagem_principal;
    $dados_propriedade['imagem_secundaria'] = $imagem_secundaria;

    // Só criar a propriedade se não houve erro no upload
    if ($message_type !== 'danger') {
        if ($propriedade->create($dados_propriedade)) {
            $_SESSION['success_message'] = "Propriedade cadastrada com sucesso!";

            // Redirecionar de volta
            $redirect_url = SITE_URL . '/caderno-campo/propriedades';
            if ($associado_id_via_get) {
                $redirect_url = SITE_URL . '/associado/' . $associado_id_via_get . '/editar';
            }
            header('Location: ' . $redirect_url);
            exit();
        } else {
            $message = "Erro ao cadastrar propriedade.";
            $message_type = "danger";

            // Se falhou, remover as imagens que foram uploadadas
            if ($imagem_principal) {
                $imageUploader->removeImage($imagem_principal);
            }
            if ($imagem_secundaria) {
                $imageUploader->removeImage($imagem_secundaria);
            }
        }
    }
}

// --- INÍCIO DO OUTPUT HTML ---
$page_title = "Cadastrar Propriedade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

// Exibir modal de permissão negada se for usuário restrito
if ($isRestrictedUser) {
    echo '<div class="modal fade" id="permissaoModal" tabindex="-1" aria-labelledby="permissaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title" id="permissaoModalLabel">Permissão Negada</h5></div>
        <div class="modal-body">Você não tem permissão para acessar esta página.</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div>
        </div></div></div>';
    echo '<script>var permissaoModal = new bootstrap.Modal(document.getElementById("permissaoModal")); permissaoModal.show();</script>';
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$associado_helper = new Associado();
$estados = $associado_helper->getEstados();
?>

<h1 class="mt-4">Cadastrar Nova Propriedade</h1>

<?php if (!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i>
        <?php
        echo htmlspecialchars($_SESSION['success_message']);
        unset($_SESSION['success_message']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<?php if ($associado_nome): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Propriedade será vinculada ao associado:</strong> <?php echo htmlspecialchars($associado_nome); ?>
    </div>
<?php else: ?>
    <p>Preencha o formulário abaixo para adicionar uma nova propriedade.</p>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form
    action="<?php echo SITE_URL; ?>/caderno-campo/propriedade/novo<?php echo ($associado_id_via_get) ? '?associado_id=' . $associado_id_via_get : ''; ?>"
    method="POST" enctype="multipart/form-data" class="row g-3">
    <?php if ($associado_id_via_get): ?>
        <input type="hidden" name="associado_id" value="<?php echo $associado_id_via_get; ?>">
    <?php endif; ?>

    <div class="col-md-6">
        <label for="propriedade" class="form-label">Nome da Propriedade</label>
        <input type="text" class="form-control" id="propriedade" name="propriedade" required>
    </div>
    <div class="col-md-6">
        <label for="area_total" class="form-label">Área Total (ha)</label>
        <input type="number" step="0.01" class="form-control" id="area_total" name="area_total" placeholder="0.00">
    </div>

    <div class="col-md-6">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco">
    </div>
    <div class="col-md-6">
        <label for="cep" class="form-label">CEP</label>
        <input type="text" class="form-control" id="cep" name="cep">
    </div>

    <div class="col-md-6">
        <label for="bairro" class="form-label">Bairro</label>
        <input type="text" class="form-control" id="bairro" name="bairro">
    </div>
    <div class="col-md-6">
        <label for="cidade" class="form-label">Cidade</label>
        <select class="form-select" id="cidade" name="cidade" required>
            <option value="">Selecione uma cidade</option>
        </select>
    </div>

    <div class="col-md-6">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado" required>
            <option value="">Selecione um estado</option>
            <option value="AC">Acre</option>
            <option value="AL">Alagoas</option>
            <option value="AP">Amapá</option>
            <option value="AM">Amazonas</option>
            <option value="BA">Bahia</option>
            <option value="CE">Ceará</option>
            <option value="DF">Distrito Federal</option>
            <option value="ES">Espírito Santo</option>
            <option value="GO">Goiás</option>
            <option value="MA">Maranhão</option>
            <option value="MT">Mato Grosso</option>
            <option value="MS">Mato Grosso do Sul</option>
            <option value="MG">Minas Gerais</option>
            <option value="PA">Pará</option>
            <option value="PB">Paraíba</option>
            <option value="PR">Paraná</option>
            <option value="PE">Pernambuco</option>
            <option value="PI">Piauí</option>
            <option value="RJ">Rio de Janeiro</option>
            <option value="RN">Rio Grande do Norte</option>
            <option value="RS">Rio Grande do Sul</option>
            <option value="RO">Rondônia</option>
            <option value="RR">Roraima</option>
            <option value="SC">Santa Catarina</option>
            <option value="SP">São Paulo</option>
            <option value="SE">Sergipe</option>
            <option value="TO">Tocantins</option>
        </select>
    </div>

    <div class="col-md-6">
        <label for="latitude" class="form-label">Latitude</label>
        <input type="text" class="form-control" id="latitude" name="latitude">
    </div>

    <div class="col-md-6">
        <label for="longitude" class="form-label">Longitude</label>
        <input type="text" class="form-control" id="longitude" name="longitude">
    </div>

    <!-- Seção de Upload de Imagens -->
    <div class="col-12">
        <h5 class="mt-4 mb-3">Imagens da Propriedade</h5>
    </div>

    <div class="col-md-6">
        <label for="imagem_principal" class="form-label">Imagem Principal</label>
        <input type="file" class="form-control" id="imagem_principal" name="imagem_principal" accept="image/*">
        <div class="form-text">
            <small>Formatos aceitos: JPG, PNG, WEBP. Máximo: 5MB. Dimensões recomendadas: 1200x800px</small>
        </div>
        <div id="preview_principal" class="mt-2"></div>
    </div>

    <div class="col-md-6">
        <label for="imagem_secundaria" class="form-label">Imagem Secundária</label>
        <input type="file" class="form-control" id="imagem_secundaria" name="imagem_secundaria" accept="image/*">
        <div class="form-text">
            <small>Formatos aceitos: JPG, PNG, WEBP. Máximo: 5MB. Dimensões recomendadas: 1200x800px</small>
        </div>
        <div id="preview_secundaria" class="mt-2"></div>
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Cadastrar Propriedade</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedades" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>

<script>
    // Carregar todas as cidades ao iniciar a página
    document.addEventListener('DOMContentLoaded', function () {
        carregarCidades();
    });

    // Função para carregar todas as cidades
    function carregarCidades() {
        const cidadeSelect = document.getElementById('cidade');
        cidadeSelect.innerHTML = '<option value="">Carregando cidades...</option>';

        // Buscar todas as cidades com seus estados
        fetch(`<?php echo SITE_URL; ?>/api/get_cidades.php?todos=1`)
            .then(response => response.json())
            .then(data => {
                cidadeSelect.innerHTML = '<option value="">Selecione uma cidade</option>';
                data.forEach(cidade => {
                    const option = document.createElement('option');
                    option.value = cidade.id;
                    option.textContent = cidade.nome;
                    option.setAttribute('data-estado-id', cidade.estado_id);
                    option.setAttribute('data-estado-sigla', cidade.estado_sigla);
                    cidadeSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erro ao carregar cidades:', error);
                cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
            });
    }

    // Função para selecionar estado automaticamente baseado na cidade
    function selecionarEstadoAutomatico(cidadeId) {
        if (!cidadeId) {
            document.getElementById('estado').value = '';
            return;
        }

        const cidadeSelect = document.getElementById('cidade');
        const estadoSelect = document.getElementById('estado');
        const cidadeOption = cidadeSelect.querySelector(`option[value="${cidadeId}"]`);

        if (cidadeOption) {
            const estadoSigla = cidadeOption.getAttribute('data-estado-sigla');
            if (estadoSigla) {
                estadoSelect.value = estadoSigla;
            }
        }
    }

    // Event listener para mudança na cidade
    document.getElementById('cidade').addEventListener('change', function () {
        const cidadeId = this.value;
        selecionarEstadoAutomatico(cidadeId);
    });

    // Preview das imagens
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById(previewId);
                preview.innerHTML = `
                <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 150px;" alt="Preview">
                <div class="mt-1">
                    <small class="text-muted">
                        ${input.files[0].name} (${(input.files[0].size / 1024 / 1024).toFixed(2)} MB)
                    </small>
                </div>
            `;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.getElementById('imagem_principal').addEventListener('change', function () {
        previewImage(this, 'preview_principal');
    });

    document.getElementById('imagem_secundaria').addEventListener('change', function () {
        previewImage(this, 'preview_secundaria');
    });

    // Validação de tamanho de arquivo
    function validateFileSize(input, maxSizeMB = 5) {
        if (input.files && input.files[0]) {
            const fileSize = input.files[0].size / 1024 / 1024; // em MB
            if (fileSize > maxSizeMB) {
                alert(`Arquivo muito grande. Máximo permitido: ${maxSizeMB}MB`);
                input.value = '';
                return false;
            }
        }
        return true;
    }

    document.getElementById('imagem_principal').addEventListener('change', function () {
        validateFileSize(this);
    });

    document.getElementById('imagem_secundaria').addEventListener('change', function () {
        validateFileSize(this);
    });
</script>