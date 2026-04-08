<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/ImageUploader.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

// Instanciar Model
$associadoModel = new Associado();
$estados = $associadoModel->getEstados();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
echo "<div class='alert alert-danger'>ID não informado.</div>";
require_once __DIR__ . '/../../includes/footer.php';
exit;
}

$id = intval($_GET['id']);
$mensagem = '';

// Buscar associado
$associado = $associadoModel->getById($id);

if (!$associado) {
echo "<div class='alert alert-danger'>Associado não encontrado.</div>";
require_once __DIR__ . '/../../includes/footer.php';
exit;
}

// Para exibir nome da cidade e estado (que o getById simples pode não trazer com JOIN)
// O getById original traz apenas os dados da tabela associados.
// O original fazia um JOIN complexo. Vamos ver se o getById tem isso.
// Verificando o código lido anteriormente: getById faz SELECT * FROM associados. Sem JOIN.
// Se precisarmos exibir "Nome Cidade (UF)", teremos que buscar separado ou aceitar apenas o ID no form.
// O formulário usa IDs para os selects, então tudo bem.
// Mas o original buscava 'nome_cidade', 'nome_estado' etc. Será que isso é usado?
// Olhando o final do arquivo original... não parece ser usado no formulário de edição,
// pois o formulário usa SELECT options baseados nos IDs.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

// Validar CSRF
if (!CSRFProtection::validatePostRequest()) {
die('Erro de segurança: Token CSRF inválido.');
}

$dados = $_POST;

// Apenas administradores podem alterar o status
if (!isset($_SESSION['user_tipo']) || $_SESSION['user_tipo'] !== 'Admin') {
// Remove status do array se não for admin
unset($dados['status']);
}

// Processar upload de foto 3x4
$foto_url = $associado['foto_url'] ?? null;
$imageUploader = new ImageUploader('uploads/associados/');
$imageUploader->setConfig([
'maxWidth' => 300,
'maxHeight' => 400,
'maxFileSize' => 2 * 1024 * 1024
]);

$erro_upload = false;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
$result = $imageUploader->uploadImage($_FILES['foto'], 'foto_' . $id);
if ($result['success']) {
// Remover foto antiga
if ($foto_url && file_exists('uploads/associados/' . $foto_url)) {
$imageUploader->removeImage($foto_url);
}
$foto_url = $result['filename'];
$dados['foto_url'] = $foto_url; // Adiciona ao array para update
} else {
$mensagem = '<div class="alert alert-danger">Erro ao fazer upload da foto: ' . htmlspecialchars($result['message']) . '
</div>';
$erro_upload = true;
}
}

if (!$erro_upload) {
// Realiza o update usando o Model
$resultado = $associadoModel->update($id, $dados);

if ($resultado === true) {
$mensagem = '<div class="alert alert-success">Associado atualizado com sucesso!</div>';
// Atualiza dados na memória para exibir no form
$associado = $associadoModel->getById($id);
} else {
$mensagem = '<div class="alert alert-danger">' . $resultado . '</div>';
}
}
}
?>
<h1 class="mt-4">Editar Associado</h1>
<?php if ($mensagem)
    echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?php echo CSRFProtection::getTokenField(); ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                            value="<?php echo htmlspecialchars($associado['nome']); ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="foto" class="form-label">Foto 3x4</label>
                        <?php if (!empty($associado['foto_url']) && file_exists('uploads/associados/' . $associado['foto_url'])): ?>
                            <div class="mb-2">
                                <img src="<?php echo SITE_URL; ?>/uploads/associados/<?php echo htmlspecialchars($associado['foto_url']); ?>"
                                    class="img-thumbnail" style="max-width: 120px; max-height: 160px; object-fit: cover;"
                                    alt="Foto do associado" id="foto_atual">
                                <div class="mt-1">
                                    <small class="text-muted">Foto atual</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        <div class="form-text">
                            <small>Formatos: JPG, PNG, WEBP. Máximo: 2MB. Dimensões recomendadas: 3x4
                                (300x400px)</small>
                        </div>
                        <div id="preview_foto" class="mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($associado['email']); ?>" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control mask-cpf" id="cpf" name="cpf"
                            value="<?php echo htmlspecialchars($associado['cpf']); ?>" placeholder="000.000.000-00">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control mask-telefone" id="telefone" name="telefone"
                            value="<?php echo htmlspecialchars($associado['telefone']); ?>"
                            placeholder="(00) 00000-0000">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control mask-cep" id="cep" name="cep"
                            value="<?php echo htmlspecialchars($associado['cep']); ?>" placeholder="00000-000">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco"
                            value="<?php echo htmlspecialchars($associado['endereco']); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado" required>
                            <option value="">Selecione um estado</option>
                            <?php foreach ($estados as $estado): ?>
                                <option value="<?php echo $estado['id']; ?>" <?php echo ($associado['estado'] == $estado['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($estado['nome'] . ' (' . $estado['uf'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="cidade" class="form-label">Cidade</label>
                        <select class="form-select" id="cidade" name="cidade" required disabled>
                            <option value="">Selecione um Estado Primeiro</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Campo Status - Apenas para Administradores -->
            <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'Admin'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-muted">(Apenas
                                    Administradores)</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="ativo" <?php echo ($associado['status'] === 'ativo') ? 'selected' : ''; ?>>
                                    Ativo</option>
                                <option value="inativo" <?php echo ($associado['status'] === 'inativo') ? 'selected' : ''; ?>>
                                    Inativo</option>
                                <option value="pendente" <?php echo ($associado['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status Atual</label>
                            <div class="form-control-plaintext">
                                <span class="badge bg-<?php
                                switch ($associado['status']) {
                                    case 'ativo':
                                        echo 'success';
                                        break;
                                    case 'inativo':
                                        echo 'danger';
                                        break;
                                    case 'pendente':
                                        echo 'warning';
                                        break;
                                    default:
                                        echo 'secondary';
                                }
                                ?>"><?php echo ucfirst(htmlspecialchars($associado['status'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Para usuários não-administradores, apenas exibir o status atual -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-control-plaintext">
                                <span class="badge bg-<?php
                                switch ($associado['status']) {
                                    case 'ativo':
                                        echo 'success';
                                        break;
                                    case 'inativo':
                                        echo 'danger';
                                        break;
                                    case 'pendente':
                                        echo 'warning';
                                        break;
                                    default:
                                        echo 'secondary';
                                }
                                ?>"><?php echo ucfirst(htmlspecialchars($associado['status'])); ?></span>
                                <small class="text-muted d-block mt-1">Apenas administradores podem alterar o status</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-success">Salvar Alterações</button>
            <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/novo?associado_id=<?php echo $id; ?>"
                class="btn btn-info" target="_blank">
                <i class="bi bi-house-add"></i> Cadastrar Propriedade para este Associado
            </a>
            <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const estadoSelect = document.getElementById('estado');
        const cidadeSelect = document.getElementById('cidade');

        if (!estadoSelect || !cidadeSelect) {
            return;
        }

        // Habilita o select de cidades se já houver um estado selecionado
        if (estadoSelect.value) {
            cidadeSelect.disabled = false;

            // Se já houver um estado selecionado, carregar as cidades
            setTimeout(() => {
                carregarCidades(estadoSelect.value, '<?php echo $associado["cidade"] ?? ""; ?>');
            }, 100);
        }

        // Função para carregar cidades
        function carregarCidades(estadoId, cidadeSelecionada = '') {
            if (!estadoId) {
                cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
                cidadeSelect.disabled = true;
                return;
            }

            cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
            cidadeSelect.disabled = true;

            fetch(`<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=${estadoId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao carregar cidades');
                    }
                    return response.json();
                })
                .then(data => {
                    cidadeSelect.innerHTML = '<option value="">Selecione uma Cidade</option>';
                    if (Array.isArray(data)) {
                        data.forEach(cidade => {
                            const option = document.createElement('option');
                            option.value = cidade.id;
                            option.textContent = cidade.nome;

                            // Seleciona a cidade se for a mesma que está no banco
                            if (cidadeSelecionada && (cidade.id == cidadeSelecionada || cidade.nome == cidadeSelecionada)) {
                                option.selected = true;
                            }

                            cidadeSelect.appendChild(option);
                        });
                    }
                    cidadeSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Erro ao buscar cidades:', error);
                    cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
                    cidadeSelect.disabled = false;
                });
        }

        // Adiciona o evento de mudança no select de estados
        estadoSelect.addEventListener('change', function () {
            carregarCidades(this.value);
        });

        // Preview da foto
        const fotoInput = document.getElementById('foto');
        const previewFoto = document.getElementById('preview_foto');
        const fotoAtual = document.getElementById('foto_atual');

        if (fotoInput && previewFoto) {
            fotoInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        // Esconder foto atual se existir
                        if (fotoAtual) {
                            fotoAtual.style.display = 'none';
                        }

                        // Mostrar preview da nova foto
                        previewFoto.innerHTML = `
                        <img src="${e.target.result}" 
                             class="img-thumbnail" 
                             style="max-width: 120px; max-height: 160px; object-fit: cover;" 
                             alt="Preview da foto">
                        <div class="mt-1">
                            <small class="text-muted">Nova foto selecionada</small>
                        </div>
                    `;
                    };
                    reader.readAsDataURL(this.files[0]);

                    // Validar tamanho do arquivo
                    const fileSize = this.files[0].size / 1024 / 1024; // em MB
                    if (fileSize > 2) {
                        alert('Arquivo muito grande. Máximo permitido: 2MB');
                        this.value = '';
                        previewFoto.innerHTML = '';
                        if (fotoAtual) {
                            fotoAtual.style.display = 'block';
                        }
                    }
                } else {
                    previewFoto.innerHTML = '';
                    if (fotoAtual) {
                        fotoAtual.style.display = 'block';
                    }
                }
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>