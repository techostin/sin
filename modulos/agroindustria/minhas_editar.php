<?php
$page_title = "Editar Agroindústria";
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Agroindustria.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/AuditLog.php';

$user = new User();

if (!$user->isLoggedIn()) {
    header("Location: " . SITE_URL . "/login");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isGestor = $user->hasPermission(NIVEL_GESTOR);
$meuAssociado = $user->getAssociadoId();

$agroClass = new Agroindustria();
$agro = $agroClass->getById($id);

if (!$agro) {
    header("Location: " . SITE_URL . "/agroindustria/minhas");
    exit;
}
if (!$isGestor && (int)$agro['associado_id'] !== (int)$meuAssociado) {
    header("Location: " . SITE_URL . "/agroindustria/minhas");
    exit;
}

$message = '';
$message_type = '';

// Ação de membros (adicionar/remover) via POST dedicado
$membros = $agroClass->getMembros($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $message = 'Erro de segurança: token inválido. Atualize a página e tente novamente.';
        $message_type = 'danger';
    } else {
        // Gerenciamento de membros
        if (($agroClass->getById($id) && (isset($_POST['acao_membro']))) && ($_POST['acao_membro'] === 'adicionar' || $_POST['acao_membro'] === 'remover')) {
            $associadoId = (int)($_POST['associado_membro_id'] ?? 0);
            if ($associadoId <= 0) {
                $message = 'Selecione um associado válido.';
                $message_type = 'danger';
            } elseif ($_POST['acao_membro'] === 'adicionar') {
                if ($agroClass->adicionarMembro($id, $associadoId, (int)$_SESSION['user_id'])) {
                    if ($isGestor || (int)$agro['associado_id'] === (int)$meuAssociado) {
                        AuditLog::registrar('AGROINDUSTRIA_MEMBRO_ADICIONADO', 'agroindustria_membros', $id, 'Membro ' . $associadoId . ' adicionado');
                    }
                    $message = 'Membro adicionado com sucesso.';
                    $message_type = 'success';
                    $membros = $agroClass->getMembros($id);
                } else {
                    $message = 'Erro ao adicionar membro.';
                    $message_type = 'danger';
                }
            } else {
                if ($agroClass->removerMembro($id, $associadoId)) {
                    $message = 'Membro removido.';
                    $message_type = 'success';
                    $membros = $agroClass->getMembros($id);
                } else {
                    $message = 'Não é possível remover o proprietário ou houve erro.';
                    $message_type = 'danger';
                }
            }
        } else {
            $dados = $_POST;
            $dados['associado_id'] = $agro['associado_id'];

            $resultado = $agroClass->update($id, $dados);

            if ($resultado === true) {
                AuditLog::registrar('AGROINDUSTRIA_EDITADA', 'agroindustrias', $id, 'Agroindústria atualizada');
                header('Location: ' . SITE_URL . '/agroindustria/minhas?editada=1');
                exit;
            }
            $message = $resultado;
            $message_type = 'danger';
        }
    }
}

// Lista de associados disponíveis para incluir como membro (exclui já membros)
$associados = [];
$meuAssociadoId = $isGestor ? null : $meuAssociado;
$db = Database::getInstance()->getConnection();
$membrosIds = array_column($membros, 'id');
$stmt = $db->query("SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    if (!in_array((int)$a['id'], array_map('intval', $membrosIds), true)) {
        $associados[] = $a;
    }
}

$old = function ($key) use ($agro) {
    return htmlspecialchars((string)($_POST[$key] ?? $agro[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-buildings text-success"></i> Editar Agroindústria</h1>
        <a href="<?php echo SITE_URL; ?>/agroindustria/minhas" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" class="card shadow-sm">
        <div class="card-body">
            <?php echo CSRFProtection::getTokenField(); ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome Fantasia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_fantasia" required value="<?php echo $old('nome_fantasia'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Razão Social</label>
                    <input type="text" class="form-control" name="razao_social" value="<?php echo $old('razao_social'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">CNPJ</label>
                    <input type="text" class="form-control" name="cnpj" id="cnpj" value="<?php echo $old('cnpj'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Inscrição Estadual</label>
                    <input type="text" class="form-control" name="inscricao_estadual" value="<?php echo $old('inscricao_estadual'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="Ativa" <?php echo (($_POST['status'] ?? $agro['status']) === 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Inativa" <?php echo (($_POST['status'] ?? $agro['status']) === 'Inativa') ? 'selected' : ''; ?>>Inativa</option>
                    </select>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-4">
                    <label class="form-label">Endereço</label>
                    <input type="text" class="form-control" name="endereco" value="<?php echo $old('endereco'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Número</label>
                    <input type="text" class="form-control" name="numero" value="<?php echo $old('numero'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" class="form-control" name="bairro" value="<?php echo $old('bairro'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cidade</label>
                    <input type="text" class="form-control" name="cidade" value="<?php echo $old('cidade'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Selecione...</option>
                        <?php
                        $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                        $selUf = $_POST['estado'] ?? $agro['estado'] ?? '';
                        foreach ($ufs as $uf): ?>
                            <option value="<?php echo $uf; ?>" <?php echo $selUf === $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" class="form-control" name="cep" value="<?php echo $old('cep'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-control" name="telefone" value="<?php echo $old('telefone'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" name="email" value="<?php echo $old('email'); ?>">
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-6">
                    <label class="form-label">Responsável Técnico (RT)</label>
                    <input type="text" class="form-control" name="responsavel_tecnico" value="<?php echo $old('responsavel_tecnico'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observações</label>
                    <input type="text" class="form-control" name="observacoes" value="<?php echo $old('observacoes'); ?>">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Alterações</button>
        </div>
    </form>

    <!-- Membros da agroindústria (formulários próprios, fora do form principal) -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="bi bi-people text-primary"></i> Membros (usuários da agroindústria)</h5>
            <p class="text-muted small">Associados que compartilham os insumos e registros desta agroindústria (ex.: usuário X e usuário Y). O proprietário é sempre membro.</p>

            <?php if ($membros): ?>
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr><th>Associado</th><th>Tipo</th><th class="text-end">Ação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membros as $m): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(trim(($m['nome'] ?? '') . ' ' . ($m['sobrenome'] ?? ''))); ?></td>
                                <td>
                                    <?php if ((int)$m['id'] === (int)$agro['associado_id']): ?>
                                        <span class="badge bg-success">Proprietário</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Membro</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((int)$m['id'] !== (int)$agro['associado_id']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remover este membro?');">
                                            <?php echo CSRFProtection::getTokenField(); ?>
                                            <input type="hidden" name="acao_membro" value="remover">
                                            <input type="hidden" name="associado_membro_id" value="<?php echo (int)$m['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-dash"></i> Remover</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-light border small">Nenhum membro além do proprietário.</div>
            <?php endif; ?>

            <?php if ($associados): ?>
                <form method="POST" class="row g-2 align-items-end mt-2">
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <input type="hidden" name="acao_membro" value="adicionar">
                    <div class="col-md-6">
                        <label class="form-label">Adicionar membro (associado)</label>
                        <select class="form-select" name="associado_membro_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($associados as $a): ?>
                                <option value="<?php echo (int)$a['id']; ?>">
                                    <?php echo htmlspecialchars(trim(($a['nome'] ?? '') . ' ' . ($a['sobrenome'] ?? ''))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary"><i class="bi bi-person-plus"></i> Adicionar Membro</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-light border small mb-0">Todos os associados ativos já são membros ou não há candidatos disponíveis.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var cnpj = document.getElementById('cnpj');
    if (cnpj) {
        cnpj.addEventListener('input', function() {
            var d = this.value.replace(/\D/g, '').slice(0, 14);
            var out = d;
            if (d.length > 2)  out = d.slice(0,2) + '.' + d.slice(2);
            if (d.length > 5)  out = out.slice(0,6) + '.' + d.slice(5);
            if (d.length > 8)  out = out.slice(0,10) + '/' + d.slice(8);
            if (d.length > 12) out = out.slice(0,15) + '-' + d.slice(12);
            this.value = out;
        });
    }
});
</script>
