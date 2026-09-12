<?php
$page_title = "Upload de Croqui da Propriedade";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de plano inválido.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar o plano principal
$stmt = $db->prepare("SELECT p.*, pr.propriedade as propriedade_nome, a.nome as associado_nome, a.sobrenome as associado_sobrenome 
                      FROM plano_manejo p 
                      JOIN propriedades pr ON p.propriedade_id = pr.id 
                      JOIN associados a ON p.associado_id = a.id 
                      WHERE p.id = ?");
$stmt->execute([$id]);
$plano = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plano) {
    echo "<div class='alert alert-danger'>Plano de manejo não encontrado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$message = '';
$message_type = '';

function validarCroqui($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Erro no upload do arquivo.'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Arquivo muito grande. Máximo permitido: 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime === 'application/pdf') {
        $handle = fopen($file['tmp_name'], 'rb');
        $head = $handle ? fread($handle, 5) : '';
        if ($handle) { fclose($handle); }
        if ($head === '%PDF-') {
            return ['ok' => true, 'ext' => 'pdf'];
        }
        return ['ok' => false, 'message' => 'Arquivo não é um PDF válido.'];
    }

    $tipo = @getimagesize($file['tmp_name']);
    if ($tipo === false) {
        return ['ok' => false, 'message' => 'Arquivo não é uma imagem válida. Apenas JPG, PNG, GIF ou PDF.'];
    }
    $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!isset($map[$tipo[2]])) {
        return ['ok' => false, 'message' => 'Formato de imagem não permitido. Apenas JPG, PNG, GIF ou PDF.'];
    }
    return ['ok' => true, 'ext' => $map[$tipo[2]]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['croqui'])) {
    if (!CSRFProtection::validatePostRequest()) {
        $message = "Requisição inválida (token de segurança ausente ou expirado). Por favor, tente novamente.";
        $message_type = 'danger';
    } else {
        $plano_id = $_POST['plano_id'] ?? $id;
        if ((int)$plano_id !== (int)$id) {
            $plano_id = $id;
        }

        // Ownership: não-gestores só podem enviar croqui do próprio plano
        $permitido = $user->hasPermission(NIVEL_GESTOR);
        if (!$permitido) {
            $meuAssociado = $user->getAssociadoId();
            if ($meuAssociado && (int)$plano['associado_id'] === (int)$meuAssociado) {
                $permitido = true;
            }
        }

        if (!$permitido) {
            $message = "Acesso negado: este plano de manejo não pertence ao seu associado.";
            $message_type = 'danger';
        } else {
            $validacao = validarCroqui($_FILES['croqui']);
            if (!$validacao['ok']) {
                $message = $validacao['message'];
                $message_type = 'danger';
            } else {
                // Criar diretório de uploads se não existir
                $uploadDir = __DIR__ . '/../../uploads/planos_manejo/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = 'croqui_' . $plano_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $validacao['ext'];
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['croqui']['tmp_name'], $targetPath)) {
                    // Atualizar no banco
                    $fileUrl = SITE_URL . '/uploads/planos_manejo/' . $fileName;

                    $stmtUpdate = $db->prepare("UPDATE plano_manejo SET croqui_propriedade_url = ? WHERE id = ?");
                    $stmtUpdate->execute([$fileUrl, $plano_id]);

                    // Refresh plano data
                    $stmt->execute([$id]);
                    $plano = $stmt->fetch(PDO::FETCH_ASSOC);

                    $message = "Croqui enviado com sucesso!";
                    $message_type = 'success';
                } else {
                    $message = "Erro ao mover o arquivo.";
                    $message_type = 'danger';
                }
            }
        }
    }
}
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <h1 class="mb-1"><i class="bi bi-map me-2" style="color:var(--gm-primary);"></i>Upload de Croqui da Propriedade</h1>
            <div class="btn-group">
                <a href="<?php echo SITE_URL; ?>/plano-manejo/<?php echo $id; ?>/visualizar" class="btn btn-assoc-ghost">
                    <i class="bi bi-arrow-left"></i> Voltar ao Plano
                </a>
                <a href="<?php echo SITE_URL; ?>/planos-manejo" class="btn btn-assoc-ghost">
                    <i class="bi bi-list-ul"></i> Todos os Planos
                </a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-info-circle me-1" style="color:var(--gm-primary);"></i> Plano de Manejo
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Propriedade</label>
                        <strong><?php echo htmlspecialchars($plano['propriedade_nome']); ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Agricultor</label>
                        <strong><?php echo htmlspecialchars($plano['associado_nome'] . ' ' . $plano['associado_sobrenome']); ?></strong>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Ano de Safra</label>
                        <strong><?php echo htmlspecialchars($plano['ano_safra']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="gm-card mb-4">
                <div class="card-head">
                    <i class="bi bi-upload me-1" style="color:var(--gm-primary);"></i> Upload de Arquivo
                </div>
                <div class="p-4">
                    <?php if ($plano['croqui_propriedade_url']): ?>
                        <div class="mb-3 text-center">
                            <p class="text-muted small mb-2">Arquivo Atual:</p>
                            <div class="bg-light rounded p-3 border border-dashed">
                                <?php if (pathinfo($plano['croqui_propriedade_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                    <i class="bi bi-file-earmark-pdf-fill text-danger display-4"></i>
                                    <p class="text-muted small mt-2">PDF disponível</p>
                                    <a href="<?php echo htmlspecialchars($plano['croqui_propriedade_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> Abrir PDF
                                    </a>
                                <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($plano['croqui_propriedade_url']); ?>" class="img-fluid rounded mb-2" alt="Croqui da Propriedade" style="max-height: 200px;">
                                    <br>
                                    <a href="<?php echo htmlspecialchars($plano['croqui_propriedade_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> Ver em Tamanho Real
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                    <?php endif; ?>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <input type="hidden" name="plano_id" value="<?php echo $id; ?>">
                        
                        <div class="mb-3">
                            <label for="croqui" class="form-label fw-bold">Selecione o Arquivo</label>
                            <p class="text-muted small mb-1">Tipos permitidos: JPG, PNG, GIF ou PDF</p>
                            <input type="file" class="form-control" id="croqui" name="croqui" accept="image/jpeg,image/png,image/gif,application/pdf" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-assoc-success btn-lg">
                                <i class="bi bi-cloud-upload-fill me-2"></i> Enviar Arquivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
