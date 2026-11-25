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

// Determinar se é modo visualização (gestor ou view=1)
$isViewMode = ($_SESSION['user_nivel'] != NIVEL_ADMIN) || isset($_GET['view']);

// Autorização: Admin/Gestor (equipe interna) podem ver o cadastro de qualquer
// associado; usuário comum (nível 3) só o próprio — antes qualquer usuário
// logado conseguia ler nome/e-mail/CPF/telefone/endereço de qualquer associado.
if (!$user->isGestorOuAdmin()) {
    $meuAssociadoId = (int) $user->getAssociadoIdResolvido();
    if ($meuAssociadoId <= 0 || $meuAssociadoId !== $id) {
        echo "<div class='alert alert-danger m-4'>Acesso negado: você só pode visualizar o seu próprio cadastro.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
    $isViewMode = true;
}

// Buscar associado
$associado = $associadoModel->getById($id);

if (!$associado) {
echo "<div class='alert alert-danger'>Associado não encontrado.</div>";
require_once __DIR__ . '/../../includes/footer.php';
exit;
}

// Cargos/funções disponíveis na associação
$cargosVinculo = [
    'membro'             => 'Membro',
    'presidente'         => 'Presidente',
    'vice-presidente'    => 'Vice-Presidente',
    'diretor'            => 'Diretor(a)',
    'secretario'         => 'Secretário(a)',
    'tesoureiro'         => 'Tesoureiro(a)',
    'conselho_fiscal'    => 'Conselho Fiscal',
    'comissao_etica'     => 'Comissão de Ética',
    'comissao_educativa' => 'Comissão Educativa',
];

// Associações ativas + vínculos e cargos atuais do associado
$associacoesAtivas = $associadoModel->getAssociacoesAtivas();
$associacoesVinculadas = array_map('intval', array_column($associadoModel->getAssociacoesById($id), 'id'));
$cargosAtuais = $associadoModel->getTiposVinculo($id);

// Para exibir nome da cidade e estado (que o getById simples pode não trazer com JOIN)
// O getById original traz apenas os dados da tabela associados.
// O original fazia um JOIN complexo. Vamos ver se o getById tem isso.
// Verificando o código lido anteriormente: getById faz SELECT * FROM associados. Sem JOIN.
// Se precisarmos exibir "Nome Cidade (UF)", teremos que buscar separado ou aceitar apenas o ID no form.
// O formulário usa IDs para os selects, então tudo bem.
// Mas o original buscava 'nome_cidade', 'nome_estado' etc. Será que isso é usado?
// Olhando o final do arquivo original... não parece ser usado no formulário de edição,
// pois o formulário usa SELECT options baseados nos IDs.

if (!$isViewMode && $_SERVER['REQUEST_METHOD'] === 'POST') {

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
// Recarrega vínculos e cargos para refletir imediatamente o cargo atribuído
$associacoesVinculadas = array_map('intval', array_column($associadoModel->getAssociacoesById($id), 'id'));
$cargosAtuais = $associadoModel->getTiposVinculo($id);
} else {
$mensagem = '<div class="alert alert-danger">' . $resultado . '</div>';
}
}
}
?>
<?php if ($isViewMode): ?>
    <h1 class="mt-4">Visualizar Associado</h1>
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-1"></i> Modo de visualização. Apenas administradores podem editar os dados dos associados.
    </div>
<?php else: ?>
    <h1 class="mt-4">Editar Associado</h1>
<?php endif; ?>
<?php if ($mensagem)
    echo $mensagem; ?>
<div class="card">
    <div class="card-body">
        <form method="post" id="formEditarAssociado" action="<?php echo SITE_URL; ?>/associado/<?php echo $id; ?>/editar" enctype="multipart/form-data">
            <?php if (!$isViewMode): ?>
                <?php echo CSRFProtection::getTokenField(); ?>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="nome" class="form-label">Nome</label>
                                    <input type="text" class="form-control" id="nome" name="nome"
                                        value="<?php echo htmlspecialchars($associado['nome']); ?>" required
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?php echo htmlspecialchars($associado['email']); ?>" required
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-6">
                                    <label for="cpf" class="form-label">CPF</label>
                                    <input type="text" class="form-control mask-cpf" id="cpf" name="cpf"
                                        value="<?php echo htmlspecialchars($associado['cpf']); ?>" placeholder="000.000.000-00"
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-4">
                                    <label for="telefone" class="form-label">Telefone</label>
                                    <input type="text" class="form-control mask-telefone" id="telefone" name="telefone"
                                        value="<?php echo htmlspecialchars($associado['telefone']); ?>" placeholder="(00) 00000-0000"
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-4">
                                    <label for="cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control mask-cep" id="cep" name="cep"
                                        value="<?php echo htmlspecialchars($associado['cep']); ?>" placeholder="00000-000"
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-4">
                                    <label for="endereco" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="endereco" name="endereco"
                                        value="<?php echo htmlspecialchars($associado['endereco']); ?>"
                                        <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                </div>

                                <div class="col-md-6">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select class="form-select" id="estado" name="estado" required
                                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                                        <option value="">Selecione um estado</option>
                                        <?php foreach ($estados as $estado): ?>
                                            <option value="<?php echo $estado['id']; ?>" <?php echo ($associado['estado'] == $estado['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($estado['nome'] . ' (' . $estado['uf'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="cidade" class="form-label">Cidade</label>
                                    <select class="form-select" id="cidade" name="cidade" required disabled>
                                        <option value="">Selecione um Estado Primeiro</option>
                                    </select>
                                </div>

                                <?php if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'Admin' && !$isViewMode): ?>
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">Status <span class="text-muted">(Apenas Administradores)</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="ativo" <?php echo ($associado['status'] === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                            <option value="inativo" <?php echo ($associado['status'] === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                                            <option value="pendente" <?php echo ($associado['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="w-100">
                                            <label class="form-label d-block">Status Atual</label>
                                            <div class="form-control-plaintext p-0">
                                                <span class="badge bg-<?php
                                                    switch ($associado['status']) {
                                                        case 'ativo': echo 'success'; break;
                                                        case 'inativo': echo 'danger'; break;
                                                        case 'pendente': echo 'warning'; break;
                                                        default: echo 'secondary'; break;
                                                    }
                                                ?>"><?php echo ucfirst(htmlspecialchars($associado['status'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="w-100">
                                            <label class="form-label d-block">Status</label>
                                            <div class="form-control-plaintext p-0">
                                                <span class="badge bg-<?php
                                                    switch ($associado['status']) {
                                                        case 'ativo': echo 'success'; break;
                                                        case 'inativo': echo 'danger'; break;
                                                        case 'pendente': echo 'warning'; break;
                                                        default: echo 'secondary'; break;
                                                    }
                                                ?>"><?php echo ucfirst(htmlspecialchars($associado['status'])); ?></span>
                                            </div>
                                            <?php if ($isViewMode): ?>
                                                <small class="text-muted d-block mt-1">Apenas administradores podem alterar o status</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-semibold mb-0"><i class="bi bi-building me-1 text-success"></i> Associações e Cargos</h6>
                                <?php if (!$isViewMode): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalAtribuirCargo">
                                    <i class="bi bi-person-gear me-1"></i> Atribuir Cargo
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php if (empty($associacoesVinculadas)): ?>
                                <span class="text-muted small">Sem vínculos de associação.</span>
                            <?php else: ?>
                                <?php foreach ($associacoesAtivas as $assoc): ?>
                                    <?php if (in_array((int)$assoc['id'], $associacoesVinculadas, true)): ?>
                                        <?php $cargoAtual = $cargosAtuais[(int)$assoc['id']] ?? 'membro'; ?>
                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle me-1 mb-1">
                                            <?php echo htmlspecialchars(!empty($assoc['sigla']) ? $assoc['sigla'] : $assoc['nome']); ?>
                                            &mdash; <?php echo htmlspecialchars($cargosVinculo[$cargoAtual] ?? $cargoAtual); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="foto" class="form-label">Foto 3x4</label>
                                <?php if (!empty($associado['foto_url']) && file_exists('uploads/associados/' . $associado['foto_url'])): ?>
                                    <div class="text-center mb-3">
                                        <img src="<?php echo SITE_URL; ?>/uploads/associados/<?php echo htmlspecialchars($associado['foto_url']); ?>"
                                            class="img-fluid rounded border" style="max-height: 260px; object-fit: cover;"
                                            alt="Foto do associado" id="foto_atual">
                                        <div class="mt-2">
                                            <small class="text-muted">Foto atual</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="foto" name="foto" accept="image/*"
                                    <?php echo $isViewMode ? 'disabled' : ''; ?> />
                                <div class="form-text mt-2">
                                    <small>Formatos: JPG, PNG, WEBP. Máximo: 2MB. Dimensões recomendadas: 3x4 (300x400px)</small>
                                </div>
                                <div id="preview_foto" class="mt-3"></div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <?php if (!$isViewMode): ?>
                                    <button type="submit" class="btn btn-success">Salvar Alterações</button>
                                    <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/novo?associado_id=<?php echo $id; ?>"
                                        class="btn btn-info" target="_blank">
                                        <i class="bi bi-house-add"></i> Cadastrar Propriedade
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-secondary">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Atribuir Cargo / Função na Associação -->
<div class="modal fade" id="modalAtribuirCargo" tabindex="-1" aria-labelledby="modalAtribuirCargoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAtribuirCargoLabel"><i class="bi bi-person-gear me-2"></i>Atribuir Cargo / Função na Associação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Vincule o associado a uma ou mais associações e defina o cargo/função em cada uma.</p>
                <?php if (empty($associacoesAtivas)): ?>
                    <div class="alert alert-warning mb-0">Nenhuma associação ativa cadastrada.</div>
                <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($associacoesAtivas as $assoc): ?>
                        <?php
                        $vinc = in_array((int)$assoc['id'], $associacoesVinculadas, true);
                        $cargoAtual = $cargosAtuais[(int)$assoc['id']] ?? 'membro';
                        ?>
                        <div class="col-md-6">
                            <div class="border rounded p-2">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="associacoes[]" form="formEditarAssociado"
                                           value="<?php echo $assoc['id']; ?>" id="assoc_<?php echo $assoc['id']; ?>"
                                           <?php echo $vinc ? 'checked' : ''; ?> <?php echo $isViewMode ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="assoc_<?php echo $assoc['id']; ?>">
                                        <?php echo htmlspecialchars(!empty($assoc['sigla']) ? $assoc['sigla'] : $assoc['nome']); ?>
                                    </label>
                                </div>
                                <label class="small text-muted d-block mb-1">Cargo / Função</label>
                                <select class="form-select form-select-sm" name="cargo[<?php echo $assoc['id']; ?>]" form="formEditarAssociado" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                                    <?php foreach ($cargosVinculo as $cargoVal => $cargoLabel): ?>
                                        <option value="<?php echo $cargoVal; ?>" <?php echo $cargoAtual === $cargoVal ? 'selected' : ''; ?>><?php echo htmlspecialchars($cargoLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="associacoes_enviado" value="1" form="formEditarAssociado">
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('formEditarAssociado').submit()">
                    <i class="bi bi-check-lg me-1"></i> Salvar Cargo
                </button>
            </div>
        </div>
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
