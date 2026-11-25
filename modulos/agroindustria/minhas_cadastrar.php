<?php
$page_title = "Nova Agroindústria";
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

$isGestor = $user->hasPermission(NIVEL_GESTOR);
$meuAssociado = $user->getAssociadoId();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFProtection::validatePostRequest()) {
        $message = 'Erro de segurança: token inválido. Atualize a página e tente novamente.';
        $message_type = 'danger';
    } else {
        $associadoId = $isGestor
            ? (int)($_POST['associado_id'] ?? 0)
            : (int)$meuAssociado;

        if ($associadoId <= 0) {
            $message = 'Selecione o associado dono da agroindústria.';
            $message_type = 'danger';
        } else {
            $dados = $_POST;
            $dados['associado_id'] = $associadoId;
            $dados['criado_por'] = (int)$_SESSION['user_id'];

            $agroClass = new Agroindustria();
            $resultado = $agroClass->create($dados);

            if (is_numeric($resultado)) {
                AuditLog::registrar('AGROINDUSTRIA_CADASTRADA', 'agroindustrias', (int)$resultado, 'Nova agroindústria cadastrada');
                header('Location: ' . SITE_URL . '/agroindustria/minhas?criada=1');
                exit;
            }
            $message = $resultado;
            $message_type = 'danger';
        }
    }
}

// Lista de associados para seleção (apenas gestor)
$associados = [];
if ($isGestor) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC");
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <h1><i class="bi bi-buildings text-success"></i> Nova Agroindústria</h1>
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
                <?php if ($isGestor): ?>
                    <div class="col-md-6">
                        <label class="form-label">Associado (proprietário) <span class="text-danger">*</span></label>
                        <select class="form-select" name="associado_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($associados as $a): ?>
                                <option value="<?php echo $a['id']; ?>" <?php echo (($_POST['associado_id'] ?? '') == $a['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="form-label">Nome Fantasia <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nome_fantasia" required
                           value="<?php echo htmlspecialchars($_POST['nome_fantasia'] ?? ''); ?>"
                           placeholder="Ex.: Agroindústria Sabor do Campo">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Razão Social</label>
                    <input type="text" class="form-control" name="razao_social"
                           value="<?php echo htmlspecialchars($_POST['razao_social'] ?? ''); ?>"
                           placeholder="Ex.: Cooperativa Sabor do Campo Ltda">
                </div>
                <div class="col-md-4">
                    <label class="form-label">CNPJ</label>
                    <input type="text" class="form-control" name="cnpj" id="cnpj"
                           value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>"
                           placeholder="00.000.000/0000-00">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Inscrição Estadual</label>
                    <input type="text" class="form-control" name="inscricao_estadual"
                           value="<?php echo htmlspecialchars($_POST['inscricao_estadual'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="Ativa" <?php echo (($_POST['status'] ?? 'Ativa') === 'Ativa') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="Inativa" <?php echo (($_POST['status'] ?? '') === 'Inativa') ? 'selected' : ''; ?>>Inativa</option>
                    </select>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-4">
                    <label class="form-label">Endereço</label>
                    <input type="text" class="form-control" name="endereco"
                           value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Número</label>
                    <input type="text" class="form-control" name="numero"
                           value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bairro</label>
                    <input type="text" class="form-control" name="bairro"
                           value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cidade</label>
                    <input type="text" class="form-control" name="cidade"
                           value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Selecione...</option>
                        <?php
                        $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                        $selUf = $_POST['estado'] ?? '';
                        foreach ($ufs as $uf): ?>
                            <option value="<?php echo $uf; ?>" <?php echo $selUf === $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">CEP</label>
                    <input type="text" class="form-control" name="cep"
                           value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                           placeholder="00000-000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-control" name="telefone"
                           value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>"
                           placeholder="(00) 00000-0000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" name="email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-6">
                    <label class="form-label">Responsável Técnico (RT)</label>
                    <input type="text" class="form-control" name="responsavel_tecnico"
                           value="<?php echo htmlspecialchars($_POST['responsavel_tecnico'] ?? ''); ?>"
                           placeholder="Ex.: Eng. Agrônomo, Téc. em Agroindústria">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observações</label>
                    <input type="text" class="form-control" name="observacoes"
                           value="<?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?>">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white text-end">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Salvar Agroindústria</button>
        </div>
    </form>
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
