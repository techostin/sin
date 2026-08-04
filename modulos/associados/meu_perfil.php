<?php
/**
 * Módulo: Meu Perfil
 * URL: /meu-perfil
 *
 * Permite que qualquer usuário logado (admin, gestor ou comum) edite
 * seus próprios dados pessoais sem expor o ID na URL.
 *
 * Regras:
 *  - O campo STATUS só é exibido/editável por NIVEL_ADMIN.
 *  - O ID do associado é lido da sessão, não da URL.
 *  - Usado tanto no primeiro acesso quanto para atualização posterior.
 */

$page_title = "Meu Perfil";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/ImageUploader.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

// Garantir que o usuário está logado
if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

// ID do usuário logado — sempre da sessão, nunca da URL
$id    = (int)($_SESSION['user_id'] ?? 0);
$nivel = (int)($_SESSION['user_nivel'] ?? NIVEL_USUARIO);
$isAdmin = ($nivel === NIVEL_ADMIN);

// Detectar primeiro acesso (flag definida no login)
$isPrimeiroAcesso = !empty($_SESSION['primeiro_acesso']);
// Limpar a flag após ler (só mostra uma vez)
unset($_SESSION['primeiro_acesso']);

if (!$id) {
    echo "<div class='alert alert-danger m-4'>Sessão inválida. Faça login novamente.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Instanciar model
$associadoModel = new Associado();
$estados        = $associadoModel->getEstados();

// Buscar dados do próprio usuário
// Usa User::getCurrentUser() que lê da tabela correta (associados ou usuarios)
// baseado em $_SESSION['user_table'], evitando erro para usuários que não são associados.
$associado = $user->getCurrentUser();

// Fallback: se por algum motivo getCurrentUser() falhar, tenta na tabela associados
if (!$associado) {
    $associado = $associadoModel->getById($id);
}

if (!$associado) {
    // Nenhum dado encontrado (nem em associados, nem em usuarios)
    echo "<div class='alert alert-warning m-4'>
        <i class='bi bi-info-circle me-1'></i>
        Seus dados de perfil ainda não foram cadastrados no sistema.
        Entre em contato com o administrador.
    </div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$mensagem = '';

// ── Processar POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF
    if (!CSRFProtection::validatePostRequest()) {
        die('Erro de segurança: token inválido.');
    }

    $dados = $_POST;

    // SEGURANÇA: remover campos que o usuário não pode alterar
    unset($dados['nivel'], $dados['tipo']);

    // STATUS: somente admin pode alterar
    if (!$isAdmin) {
        unset($dados['status']);
    }

    // Upload de foto
    $foto_url     = $associado['foto_url'] ?? null;
    $imageUploader = new ImageUploader('uploads/associados/');
    $imageUploader->setConfig([
        'maxWidth'    => 300,
        'maxHeight'   => 400,
        'maxFileSize' => 2 * 1024 * 1024,
    ]);

    $erro_upload = false;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $result = $imageUploader->uploadImage($_FILES['foto'], 'foto_' . $id);
        if ($result['success']) {
            if ($foto_url && file_exists('uploads/associados/' . $foto_url)) {
                $imageUploader->removeImage($foto_url);
            }
            $foto_url        = $result['filename'];
            $dados['foto_url'] = $foto_url;
        } else {
            $mensagem    = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Erro ao fazer upload da foto: ' . htmlspecialchars($result['message']) . '</div>';
            $erro_upload = true;
        }
    }

    if (!$erro_upload) {
        // Atualiza na tabela correta (associados ou usuarios) via User::updateCurrentUser()
        $resultado = $user->updateCurrentUser($dados);
        if ($resultado === true) {
            // Atualizar nome na sessão se alterado
            if (!empty($dados['nome'])) {
                $_SESSION['user_nome'] = trim($dados['nome']) . ' ' . trim($dados['sobrenome'] ?? '');
            }

            // Sincronizar dados para a outra tabela (exceto para Gestor)
            $user_tipo = $_SESSION['user_tipo'] ?? '';
            if (strtolower($user_tipo) !== 'gestor') {
                $user_table = $_SESSION['user_table'] ?? 'associados';
                try {
                    $db_sync = Database::getInstance()->getConnection();
                    $email = $dados['email'] ?? $associado['email'] ?? '';

                    if ($user_table === 'associados' && !empty($email)) {
                        // Atualizar também na tabela usuarios (buscar pelo email).
                        // A tabela usuarios NÃO possui sobrenome, cpf, endereco, cidade, estado, etc.
                        $campos_usuarios = [];
                        $params_usuarios = [':email' => $email];
                        foreach (['nome', 'telefone', 'orgao', 'setor', 'especialidade', 'nicho'] as $campo) {
                            if (!empty($dados[$campo])) {
                                $campos_usuarios[] = "$campo = :$campo";
                                $params_usuarios[":$campo"] = $dados[$campo];
                            }
                        }
                        if (!empty($campos_usuarios)) {
                            $sql_usuarios = "UPDATE usuarios SET " . implode(', ', $campos_usuarios) . " WHERE email = :email";
                            $stmt_u = $db_sync->prepare($sql_usuarios);
                            $stmt_u->execute($params_usuarios);
                        }
                    } elseif ($user_table === 'usuarios' && !empty($email)) {
                        // Atualizar também na tabela associados (buscar pelo email)
                        $campos_assoc = [];
                        $params_assoc = [':email' => $email];
                        foreach (['nome', 'sobrenome', 'telefone', 'cpf', 'endereco', 'numero', 'bairro', 'complemento', 'cidade', 'estado', 'cep', 'data_nascimento', 'orgao', 'setor', 'especialidade', 'nicho'] as $campo) {
                            if (!empty($dados[$campo])) {
                                $campos_assoc[] = "$campo = :$campo";
                                $params_assoc[":$campo"] = $dados[$campo];
                            }
                        }
                        if (!empty($campos_assoc)) {
                            $sql_assoc = "UPDATE associados SET " . implode(', ', $campos_assoc) . " WHERE email = :email";
                            $stmt_a = $db_sync->prepare($sql_assoc);
                            $stmt_a->execute($params_assoc);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao sincronizar dados entre tabelas: " . $e->getMessage());
                }
            }

            // Se for primeiro acesso, redirecionar para cadastro de propriedade
            if ($isPrimeiroAcesso) {
                $_SESSION['success_message'] = 'Perfil atualizado com sucesso! Agora cadastre sua propriedade.';
                header('Location: ' . SITE_URL . '/caderno-campo/propriedade/novo');
                exit;
            }

            $mensagem  = '<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Perfil atualizado com sucesso!</div>';
            $associado = $user->getCurrentUser(); // recarregar
            if (!$associado) $associado = $associadoModel->getById($id);
        } else {
            $mensagem = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>' . htmlspecialchars($resultado) . '</div>';
        }
    }
}
?>

<div class="container-fluid px-4 py-4">

    <?php if ($isPrimeiroAcesso): ?>
    <!-- Banner de boas-vindas no primeiro acesso -->
    <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-start gap-3" role="alert"
         style="background: linear-gradient(135deg, #d1e7dd 0%, #c3e6cb 100%); border-left: 4px solid #198754 !important;">
        <i class="bi bi-hand-thumbs-up-fill text-success fs-3 mt-1 flex-shrink-0"></i>
        <div>
            <h5 class="fw-bold mb-1 text-success">Bem-vindo(a) ao SINPORG! 🌱</h5>
            <p class="mb-1">Sua conta foi ativada com sucesso. Complete seus dados pessoais para ter acesso completo ao sistema.</p>
            <small class="text-muted">Preencha pelo menos <strong>nome, telefone e CPF</strong> para identificação no sistema.</small>
        </div>
        <button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h2 fw-bold mb-1">
                <i class="bi bi-person-circle text-success me-2"></i>Meu Perfil
            </h1>
            <p class="text-muted mb-0">Atualize seus dados pessoais de acesso ao sistema.</p>
        </div>
        <a href="<?php echo isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO
            ? SITE_URL . '/caderno-campo/dashboard-agricultor'
            : SITE_URL . '/'; ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Voltar ao Início
        </a>
    </div>

    <?php if ($mensagem) echo $mensagem; ?>

    <form method="POST" action="<?php echo SITE_URL; ?>/meu-perfil" enctype="multipart/form-data">
        <?php echo CSRFProtection::getTokenField(); ?>

        <div class="row g-4">

            <!-- ── Dados Pessoais ── -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-person me-1 text-success"></i> Dados Pessoais
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" name="nome"
                                       value="<?php echo htmlspecialchars($associado['nome'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="sobrenome" class="form-label">Sobrenome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sobrenome" name="sobrenome"
                                       value="<?php echo htmlspecialchars($associado['sobrenome'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($associado['email'] ?? ''); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control mask-telefone" id="telefone" name="telefone"
                                       value="<?php echo htmlspecialchars($associado['telefone'] ?? ''); ?>"
                                       placeholder="(00) 00000-0000">
                            </div>

                            <div class="col-md-4">
                                <label for="cpf" class="form-label">CPF</label>
                                <input type="text" class="form-control mask-cpf" id="cpf" name="cpf"
                                       value="<?php echo htmlspecialchars($associado['cpf'] ?? ''); ?>"
                                       placeholder="000.000.000-00">
                            </div>

                            <div class="col-md-4">
                                <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="data_nascimento" name="data_nascimento"
                                       value="<?php echo htmlspecialchars($associado['data_nascimento'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control mask-cep" id="cep" name="cep"
                                       value="<?php echo htmlspecialchars($associado['cep'] ?? ''); ?>"
                                       placeholder="00000-000">
                            </div>

                            <div class="col-md-8">
                                <label for="endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="endereco" name="endereco"
                                       value="<?php echo htmlspecialchars($associado['endereco'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="numero" class="form-label">Número</label>
                                <input type="text" class="form-control" id="numero" name="numero"
                                       value="<?php echo htmlspecialchars($associado['numero'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="bairro" name="bairro"
                                       value="<?php echo htmlspecialchars($associado['bairro'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento"
                                       value="<?php echo htmlspecialchars($associado['complemento'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Selecione um estado</option>
                                    <?php
                                    $estadoValor = $associado['estado'] ?? '';
                                    foreach ($estados as $est):
                                        // Aceita tanto o ID quanto a UF (ex.: usuarios guarda "SP")
                                        $estadoSelected = ($estadoValor == $est['id'] || $estadoValor == $est['uf']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $est['id']; ?>" <?php echo $estadoSelected; ?>>
                                            <?php echo htmlspecialchars($est['nome'] . ' (' . $est['uf'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="cidade" class="form-label">Cidade</label>
                                <select class="form-select" id="cidade" name="cidade" disabled>
                                    <option value="">Selecione um Estado Primeiro</option>
                                </select>
                            </div>

                            <!-- STATUS: somente admin vê e edita -->
                            <?php if ($isAdmin): ?>
                            <div class="col-md-6">
                                <label for="status" class="form-label">
                                    Status
                                    <span class="badge bg-danger ms-1" style="font-size:.65rem;">Admin</span>
                                </label>
                                <select class="form-select" id="status" name="status">
                                    <option value="ativo"    <?php echo ($associado['status'] === 'ativo')    ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="inativo"  <?php echo ($associado['status'] === 'inativo')  ? 'selected' : ''; ?>>Inativo</option>
                                    <option value="pendente" <?php echo ($associado['status'] === 'pendente') ? 'selected' : ''; ?>>Pendente</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label">Status da conta</label>
                                <div class="pt-1">
                                    <?php
                                    $sBadge = ['ativo' => 'success', 'inativo' => 'danger', 'pendente' => 'warning'];
                                    $sColor = $sBadge[$associado['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $sColor; ?> px-3 py-2">
                                        <?php echo ucfirst(htmlspecialchars($associado['status'])); ?>
                                    </span>
                                    <small class="text-muted d-block mt-1">Apenas administradores podem alterar o status.</small>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div><!-- /row -->
                    </div><!-- /card-body -->
                </div><!-- /card -->
            </div><!-- /col-lg-8 -->

            <!-- ── Foto de Perfil ── -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-image me-1 text-success"></i> Foto de Perfil
                    </div>
                    <div class="card-body text-center">

                        <?php if (!empty($associado['foto_url']) && file_exists('uploads/associados/' . $associado['foto_url'])): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/associados/<?php echo htmlspecialchars($associado['foto_url']); ?>"
                                 class="img-fluid rounded-circle border mb-3" id="foto_atual"
                                 style="width:120px;height:120px;object-fit:cover;"
                                 alt="Foto de perfil">
                        <?php else: ?>
                            <div class="rounded-circle bg-success d-flex align-items-center justify-content-center mx-auto mb-3 text-white fw-bold"
                                 style="width:120px;height:120px;font-size:2.5rem;" id="foto_atual">
                                <?php echo mb_strtoupper(mb_substr($associado['nome'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>

                        <div id="preview_foto"></div>

                        <label for="foto" class="form-label">Enviar nova foto</label>
                        <input type="file" class="form-control form-control-sm" id="foto" name="foto"
                               accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted d-block mt-1">JPG / PNG / WEBP · máx. 2MB</small>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Salvar Alterações
                    </button>
                    <a href="<?php echo SITE_URL; ?>/caderno-campo/propriedade/novo"
                       class="btn btn-primary">
                        <i class="bi bi-house-add me-1"></i> Adicionar Nova Propriedade
                    </a>
                    <a href="<?php echo isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO
                        ? SITE_URL . '/caderno-campo/dashboard-agricultor'
                        : SITE_URL . '/'; ?>"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-x me-1"></i> Cancelar
                    </a>
                </div>
            </div><!-- /col-lg-4 -->

        </div><!-- /row -->
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var estadoSelect = document.getElementById('estado');
    var cidadeSelect = document.getElementById('cidade');

    // Carregar cidades ao selecionar estado
    function carregarCidades(estadoId, cidadeSelecionada) {
        if (!estadoId) {
            cidadeSelect.innerHTML = '<option value="">Selecione um Estado Primeiro</option>';
            cidadeSelect.disabled = true;
            return;
        }
        cidadeSelect.innerHTML = '<option value="">Carregando...</option>';
        cidadeSelect.disabled = true;

        fetch('<?php echo SITE_URL; ?>/api/get_cidades.php?estado_id=' + estadoId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                cidadeSelect.innerHTML = '<option value="">Selecione uma cidade</option>';
                (data || []).forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.nome;
                    if (cidadeSelecionada && (c.id == cidadeSelecionada || c.nome == cidadeSelecionada)) {
                        opt.selected = true;
                    }
                    cidadeSelect.appendChild(opt);
                });
                cidadeSelect.disabled = false;
            })
            .catch(function() {
                cidadeSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                cidadeSelect.disabled = false;
            });
    }

    if (estadoSelect) {
        estadoSelect.addEventListener('change', function() { carregarCidades(this.value); });
        if (estadoSelect.value) {
            setTimeout(function() {
                carregarCidades(estadoSelect.value, '<?php echo addslashes($associado['cidade'] ?? ''); ?>');
            }, 100);
        }
    }

    // Preview de foto
    var fotoInput  = document.getElementById('foto');
    var previewEl  = document.getElementById('preview_foto');
    var fotoAtual  = document.getElementById('foto_atual');

    if (fotoInput && previewEl) {
        fotoInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) {
                previewEl.innerHTML = '';
                return;
            }
            if (this.files[0].size > 2 * 1024 * 1024) {
                alert('Arquivo muito grande. Máximo: 2MB.');
                this.value = '';
                previewEl.innerHTML = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                if (fotoAtual) fotoAtual.style.display = 'none';
                previewEl.innerHTML = '<img src="' + e.target.result + '" class="img-fluid rounded-circle border mt-2" style="width:120px;height:120px;object-fit:cover;" alt="Preview"><div class="mt-1"><small class="text-muted">Nova foto</small></div>';
            };
            reader.readAsDataURL(this.files[0]);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
