<?php
session_start();
if (isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'usuario') {
    echo '<div class="modal fade" id="permissaoModal" tabindex="-1" aria-labelledby="permissaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header bg-danger text-white"><h5 class="modal-title" id="permissaoModalLabel">Permissão Negada</h5></div>
        <div class="modal-body">Você não tem permissão para acessar esta página.</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div>
        </div></div></div>';
    echo '<script>var permissaoModal = new bootstrap.Modal(document.getElementById("permissaoModal")); permissaoModal.show();</script>';
    exit;
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listar.php?msg=erro_id');
    exit;
}

$id = intval($_GET['id']);

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("DELETE FROM fornecedores WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        header('Location: listar.php?msg=excluido');
        exit;
    } else {
        header('Location: listar.php?msg=erro_excluir');
        exit;
    }
} catch (PDOException $e) {
    header('Location: listar.php?msg=erro_excluir');
    exit;
}
?>