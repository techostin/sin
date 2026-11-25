<?php
$page_title = "Listar Contas";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->podeAcessarDiretoria()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
$podeGerenciarContas = $user->hasPermission(NIVEL_GESTOR);

// Buscar contas do banco (exemplo genérico)
$contas = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, nome, tipo, valor, data FROM contas ORDER BY data DESC");
    $stmt->execute();
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contas = [];
}
?>
<h1 class="mt-4">Contas</h1>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Contas</span>
        <?php if ($podeGerenciarContas): ?>
        <a href="<?php echo SITE_URL; ?>/conta/novo" class="btn btn-primary btn-sm">Adicionar Nova Conta</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="contasTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($contas) > 0): ?>
                    <?php foreach ($contas as $conta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conta['id']); ?></td>
                            <td><?php echo htmlspecialchars($conta['nome']); ?></td>
                            <td><?php echo htmlspecialchars($conta['tipo']); ?></td>
                            <td><?php echo htmlspecialchars($conta['valor']); ?></td>
                            <td><?php echo htmlspecialchars($conta['data']); ?></td>
                            <td>
                                <?php if ($podeGerenciarContas): ?>
                                <a href="<?php echo SITE_URL; ?>/conta/<?php echo $conta['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhuma conta encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<style>
.btn-danger[title*="desabilitada"] {
    opacity: 0.6;
    cursor: pointer;
    position: relative;
}

.btn-danger[title*="desabilitada"]:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.btn-danger[title*="desabilitada"]::after {
    content: "🚫";
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
$(document).ready(function() {
    $('#contasTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        }
    });
});

function mostrarAdvertenciaExclusao() {
    // Criar modal de advertência
    const modalHtml = `
        <div class="modal fade" id="modalAdvertencia" tabindex="-1" aria-labelledby="modalAdvertenciaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="modalAdvertenciaLabel">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Aviso Importante
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="bi bi-shield-exclamation text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <p class="text-center"><strong>A funcionalidade de exclusão de contas foi temporariamente desabilitada.</strong></p>
                        <p>Esta medida foi implementada para:</p>
                        <ul>
                            <li>🔒 Proteger dados importantes do sistema</li>
                            <li>⚠️ Evitar perda acidental de informações</li>
                            <li>🛡️ Manter a integridade dos registros</li>
                        </ul>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Alternativas disponíveis:</strong><br>
                            • Utilize a opção de <strong>Editar</strong> para fazer alterações<br>
                            • Entre em contato com o administrador do sistema se necessário
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                            <i class="bi bi-check-circle me-2"></i>Entendi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior se existir
    $('#modalAdvertencia').remove();
    
    // Adicionar modal ao body
    $('body').append(modalHtml);
    
    // Mostrar modal
    $('#modalAdvertencia').modal('show');
    
    // Remover modal do DOM após fechar
    $('#modalAdvertencia').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}
</script> 