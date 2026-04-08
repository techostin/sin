<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associacao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo "<div class='alert alert-danger'>ID não informado.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$id = intval($_POST['id']);

try {
    $associacao = new Associacao();
    
    // Verificar se a associação existe
    $dados_associacao = $associacao->getById($id);
    
    if (!$dados_associacao) {
        echo "<div class='alert alert-danger'>Associação não encontrada.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
    
    // Verificar se há associados vinculados
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM associados WHERE vinculo = ?");
    $stmt->execute([$id]);
    $associados_vinculados = $stmt->fetchColumn();
    
    if ($associados_vinculados > 0) {
        echo "<div class='alert alert-warning'>
            <h5>Não é possível excluir esta associação</h5>
            <p>A associação <strong>{$dados_associacao['nome']}</strong> possui {$associados_vinculados} associado(s) vinculado(s).</p>
            <p>Para excluir esta associação, primeiro remova todos os vínculos com associados.</p>
            <a href='" . SITE_URL . "/associacoes' class='btn btn-secondary'>Voltar</a>
        </div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
    
    // Excluir a associação
    if ($associacao->delete($id)) {
        echo "<div class='alert alert-success'>
            <h5>Associação excluída com sucesso!</h5>
            <p>A associação <strong>{$dados_associacao['nome']}</strong> foi removida do sistema.</p>
            <a href='" . SITE_URL . "/associacoes' class='btn btn-primary'>Voltar à Lista</a>
        </div>";
    } else {
        echo "<div class='alert alert-danger'>
            <h5>Erro ao excluir associação</h5>
            <p>Não foi possível excluir a associação <strong>{$dados_associacao['nome']}</strong>.</p>
            <a href='" . SITE_URL . "/associacoes' class='btn btn-secondary'>Voltar</a>
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>
        <h5>Erro ao processar exclusão</h5>
        <p>Ocorreu um erro: " . $e->getMessage() . "</p>
        <a href='" . SITE_URL . "/associacoes' class='btn btn-secondary'>Voltar</a>
    </div>";
}

require_once __DIR__ . '/../../includes/footer.php';
?> 