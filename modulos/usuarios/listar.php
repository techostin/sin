<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_nivel'])) {
    // Se não estiver logado, redireciona para o login
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Se for usuário comum, redireciona para o perfil
if ($_SESSION['user_nivel'] == NIVEL_USUARIO) {
    header('Location: ' . SITE_URL . '/usuario/' . $_SESSION['user_id'] . '/editar');
    exit;
}

// Define o título da página
$page_title = "Listar Usuários";

// Inclui os arquivos necessários
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Buscar usuários do banco (exemplo genérico)
$usuarios = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, nome, email, nivel, status FROM usuarios ORDER BY nome ASC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
}
?>
<h1 class="mt-4">Usuários</h1>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Usuários</span>
        <?php if (in_array($_SESSION['user_nivel'], [NIVEL_ADMIN, NIVEL_GESTOR], true)): ?>
            <a href="<?php echo SITE_URL; ?>/usuario/novo" class="btn btn-primary btn-sm">Adicionar Novo Usuário</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="usuariosTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Nível</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($usuarios) > 0): ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td>
                                <?php 
                                    $nivel_nome = 'Desconhecido';
                                    if ($usuario['nivel'] == 1) $nivel_nome = 'Admin';
                                    elseif ($usuario['nivel'] == 2) $nivel_nome = 'Gestor';
                                    elseif ($usuario['nivel'] == 3) $nivel_nome = 'Usuário';
                                    echo htmlspecialchars($nivel_nome);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($usuario['status']); ?></td>
                            <td>
                                <?php if ($_SESSION['user_nivel'] == NIVEL_ADMIN || $_SESSION['user_nivel'] == NIVEL_GESTOR || $usuario['id'] == $_SESSION['user_id']): ?>
                                    <a href="<?php echo SITE_URL; ?>/usuario/<?php echo $usuario['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <?php else: ?>
                                    <button class="btn btn-info btn-sm" disabled title="Você só pode editar seu próprio perfil"><i class="bi bi-pencil-fill"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum usuário encontrado.</td>
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
    $('#usuariosTable').DataTable({
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
                        <p class="text-center"><strong>A funcionalidade de exclusão de usuários foi temporariamente desabilitada.</strong></p>
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