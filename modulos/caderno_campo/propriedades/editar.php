<?php
$page_title = "Editar Propriedade";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';
require_once __DIR__ . '/../../../classes/ImageUploader.php';

$message = '';
$message_type = '';

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $imageUploader = new ImageUploader();
    
    $dados_atualizados = [];
    foreach ($_POST as $key => $value) {
        $dados_atualizados[$key] = htmlspecialchars(strip_tags(trim($value)));
    }
    $dados_atualizados['id'] = $id;
    
    // Processar upload de imagens
    $imagem_principal = $dados_propriedade['imagem_principal']; // Manter imagem atual
    $imagem_secundaria = $dados_propriedade['imagem_secundaria']; // Manter imagem atual
    
    // Upload da nova imagem principal
    if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
        $result = $imageUploader->uploadImage($_FILES['imagem_principal'], 'principal');
        if ($result['success']) {
            // Remover imagem antiga se existir
            if ($imagem_principal && file_exists('uploads/propriedades/' . $imagem_principal)) {
                $imageUploader->removeImage($imagem_principal);
            }
            $imagem_principal = $result['filename'];
        } else {
            $message = "Erro na imagem principal: " . $result['message'];
            $message_type = "danger";
        }
    }
    
    // Upload da nova imagem secundária
    if (isset($_FILES['imagem_secundaria']) && $_FILES['imagem_secundaria']['error'] === UPLOAD_ERR_OK) {
        $result = $imageUploader->uploadImage($_FILES['imagem_secundaria'], 'secundaria');
        if ($result['success']) {
            // Remover imagem antiga se existir
            if ($imagem_secundaria && file_exists('uploads/propriedades/' . $imagem_secundaria)) {
                $imageUploader->removeImage($imagem_secundaria);
            }
            $imagem_secundaria = $result['filename'];
        } else {
            $message = "Erro na imagem secundária: " . $result['message'];
            $message_type = "danger";
        }
    }
    
    // Adicionar nomes das imagens aos dados
    $dados_atualizados['imagem_principal'] = $imagem_principal;
    $dados_atualizados['imagem_secundaria'] = $imagem_secundaria;

    // Só atualizar se não houve erro no upload
    if ($message_type !== 'danger') {
        if ($propriedade->update($dados_atualizados)) {
            $message = "Propriedade atualizada com sucesso!";
            $message_type = "success";
            // Atualizar dados para mostrar na tela
            $dados_propriedade = $propriedade->getById($id);
        } else {
            $message = "Erro ao atualizar propriedade.";
            $message_type = "danger";
        }
    }
}
?>

<h1 class="mt-4">Editar Propriedade</h1>
<p>Atualize as informações da propriedade abaixo.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data" class="row g-3">
    
    <div class="col-md-6">
        <label for="propriedade" class="form-label">Nome da Propriedade</label>
        <input type="text" class="form-control" id="propriedade" name="propriedade" value="<?php echo htmlspecialchars($dados_propriedade['propriedade']); ?>" required>
    </div>
    
    <div class="col-md-6">
        <label for="area_total" class="form-label">Área Total (ha)</label>
        <input type="number" step="0.01" class="form-control" id="area_total" name="area_total" value="<?php echo htmlspecialchars($dados_propriedade['area_total']); ?>">
    </div>

    <div class="col-md-6">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($dados_propriedade['endereco'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="cep" class="form-label">CEP</label>
        <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($dados_propriedade['cep'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="bairro" class="form-label">Bairro</label>
        <input type="text" class="form-control" id="bairro" name="bairro" value="<?php echo htmlspecialchars($dados_propriedade['bairro'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="cidade" class="form-label">Cidade</label>
        <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($dados_propriedade['cidade_nome'] ?? $dados_propriedade['cidade'] ?? ''); ?>">

    <div class="col-md-6">
        <label for="estado" class="form-label">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Selecione um estado</option>
            <option value="AC" <?php echo ($dados_propriedade['estado'] == 'AC') ? 'selected' : ''; ?>>Acre</option>
            <option value="AL" <?php echo ($dados_propriedade['estado'] == 'AL') ? 'selected' : ''; ?>>Alagoas</option>
            <option value="AP" <?php echo ($dados_propriedade['estado'] == 'AP') ? 'selected' : ''; ?>>Amapá</option>
            <option value="AM" <?php echo ($dados_propriedade['estado'] == 'AM') ? 'selected' : ''; ?>>Amazonas</option>
            <option value="BA" <?php echo ($dados_propriedade['estado'] == 'BA') ? 'selected' : ''; ?>>Bahia</option>
            <option value="CE" <?php echo ($dados_propriedade['estado'] == 'CE') ? 'selected' : ''; ?>>Ceará</option>
            <option value="DF" <?php echo ($dados_propriedade['estado'] == 'DF') ? 'selected' : ''; ?>>Distrito Federal</option>
            <option value="ES" <?php echo ($dados_propriedade['estado'] == 'ES') ? 'selected' : ''; ?>>Espírito Santo</option>
            <option value="GO" <?php echo ($dados_propriedade['estado'] == 'GO') ? 'selected' : ''; ?>>Goiás</option>
            <option value="MA" <?php echo ($dados_propriedade['estado'] == 'MA') ? 'selected' : ''; ?>>Maranhão</option>
            <option value="MT" <?php echo ($dados_propriedade['estado'] == 'MT') ? 'selected' : ''; ?>>Mato Grosso</option>
            <option value="MS" <?php echo ($dados_propriedade['estado'] == 'MS') ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
            <option value="MG" <?php echo ($dados_propriedade['estado'] == 'MG') ? 'selected' : ''; ?>>Minas Gerais</option>
            <option value="PA" <?php echo ($dados_propriedade['estado'] == 'PA') ? 'selected' : ''; ?>>Pará</option>
            <option value="PB" <?php echo ($dados_propriedade['estado'] == 'PB') ? 'selected' : ''; ?>>Paraíba</option>
            <option value="PR" <?php echo ($dados_propriedade['estado'] == 'PR') ? 'selected' : ''; ?>>Paraná</option>
            <option value="PE" <?php echo ($dados_propriedade['estado'] == 'PE') ? 'selected' : ''; ?>>Pernambuco</option>
            <option value="PI" <?php echo ($dados_propriedade['estado'] == 'PI') ? 'selected' : ''; ?>>Piauí</option>
            <option value="RJ" <?php echo ($dados_propriedade['estado'] == 'RJ') ? 'selected' : ''; ?>>Rio de Janeiro</option>
            <option value="RN" <?php echo ($dados_propriedade['estado'] == 'RN') ? 'selected' : ''; ?>>Rio Grande do Norte</option>
            <option value="RS" <?php echo ($dados_propriedade['estado'] == 'RS') ? 'selected' : ''; ?>>Rio Grande do Sul</option>
            <option value="RO" <?php echo ($dados_propriedade['estado'] == 'RO') ? 'selected' : ''; ?>>Rondônia</option>
            <option value="RR" <?php echo ($dados_propriedade['estado'] == 'RR') ? 'selected' : ''; ?>>Roraima</option>
            <option value="SC" <?php echo ($dados_propriedade['estado'] == 'SC') ? 'selected' : ''; ?>>Santa Catarina</option>
            <option value="SP" <?php echo ($dados_propriedade['estado'] == 'SP') ? 'selected' : ''; ?>>São Paulo</option>
            <option value="SE" <?php echo ($dados_propriedade['estado'] == 'SE') ? 'selected' : ''; ?>>Sergipe</option>
            <option value="TO" <?php echo ($dados_propriedade['estado'] == 'TO') ? 'selected' : ''; ?>>Tocantins</option>
        </select>
    </div>

    <div class="col-md-6">
        <label for="latitude" class="form-label">Latitude</label>
        <input type="text" class="form-control" id="latitude" name="latitude" value="<?php echo htmlspecialchars($dados_propriedade['latitude'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label for="longitude" class="form-label">Longitude</label>
        <input type="text" class="form-control" id="longitude" name="longitude" value="<?php echo htmlspecialchars($dados_propriedade['longitude'] ?? ''); ?>">
    </div>

    <!-- Seção de Imagens -->
    <div class="col-12">
        <h5 class="mt-4 mb-3">Imagens da Propriedade</h5>
    </div>

    <div class="col-md-6">
        <label for="imagem_principal" class="form-label">Imagem Principal</label>
        
        <!-- Mostrar imagem atual -->
        <?php if (!empty($dados_propriedade['imagem_principal'])): ?>
            <div class="mb-2">
                <img src="<?php echo SITE_URL; ?>/uploads/propriedades/<?php echo htmlspecialchars($dados_propriedade['imagem_principal']); ?>" 
                     class="img-thumbnail" 
                     style="max-width: 200px; max-height: 150px;" 
                     alt="Imagem principal atual">
                <div class="mt-1">
                    <small class="text-muted">Imagem atual</small>
                </div>
            </div>
        <?php endif; ?>
        
        <input type="file" class="form-control" id="imagem_principal" name="imagem_principal" accept="image/*">
        <div class="form-text">
            <small>Deixe em branco para manter a imagem atual. Formatos: JPG, PNG, WEBP. Máximo: 5MB</small>
        </div>
        <div id="preview_principal" class="mt-2"></div>
    </div>

    <div class="col-md-6">
        <label for="imagem_secundaria" class="form-label">Imagem Secundária</label>
        
        <!-- Mostrar imagem atual -->
        <?php if (!empty($dados_propriedade['imagem_secundaria'])): ?>
            <div class="mb-2">
                <img src="<?php echo SITE_URL; ?>/uploads/propriedades/<?php echo htmlspecialchars($dados_propriedade['imagem_secundaria']); ?>" 
                     class="img-thumbnail" 
                     style="max-width: 200px; max-height: 150px;" 
                     alt="Imagem secundária atual">
                <div class="mt-1">
                    <small class="text-muted">Imagem atual</small>
                </div>
            </div>
        <?php endif; ?>
        
        <input type="file" class="form-control" id="imagem_secundaria" name="imagem_secundaria" accept="image/*">
        <div class="form-text">
            <small>Deixe em branco para manter a imagem atual. Formatos: JPG, PNG, WEBP. Máximo: 5MB</small>
        </div>
        <div id="preview_secundaria" class="mt-2"></div>
    </div>

    <div class="col-12">
        <label for="observacoes" class="form-label">Observações</label>
        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($dados_propriedade['observacoes'] ?? ''); ?></textarea>
    </div>

    <div class="col-12 mt-4">
        <button type="submit" class="btn btn-primary">Atualizar Propriedade</button>
        <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedades" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>

<script>
// Preview das imagens
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
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

document.getElementById('imagem_principal').addEventListener('change', function() {
    previewImage(this, 'preview_principal');
});

document.getElementById('imagem_secundaria').addEventListener('change', function() {
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

document.getElementById('imagem_principal').addEventListener('change', function() {
    validateFileSize(this);
});

document.getElementById('imagem_secundaria').addEventListener('change', function() {
    validateFileSize(this);
});
</script> 