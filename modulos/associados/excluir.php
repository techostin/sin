<?php
// Iniciar a sessão
session_start();

// Incluir o arquivo de configuração para ter acesso às constantes
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != NIVEL_ADMIN) {
    // Redireciona para a lista com mensagem de erro
    header('Location: ' . SITE_URL . '/associados?erro=permissao_admin');
    exit;
}

$id = $_POST['id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRFProtection::validatePostRequest() || !$id) {
    header('Location: ' . SITE_URL . '/associados?erro=csrf');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se o associado existe
    $stmt = $db->prepare("SELECT nome, sobrenome FROM associados WHERE id = ?");
    $stmt->execute([$id]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$associado) {
        echo "<div class='alert alert-danger'>Associado não encontrado.</div>";
        require_once __DIR__ . '/../../includes/footer.php';
        exit;
    }
    
    // Verificar se há dependências (propriedades, atividades, etc.)
    $stmt = $db->prepare("SELECT COUNT(*) FROM propriedades WHERE associado_id = ?");
    $stmt->execute([$id]);
    $propriedades_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM atividades_diarias WHERE associado_id = ?");
    $stmt->execute([$id]);
    $atividades_count = $stmt->fetchColumn();
    
    if ($propriedades_count > 0 || $atividades_count > 0) {
        $mensagem_erro = "Não é possível excluir o associado. Existem registros vinculados: ";
        $mensagem_erro .= $propriedades_count > 0 ? "$propriedades_count propriedade(s)" : "";
        $mensagem_erro .= $atividades_count > 0 ? ($propriedades_count > 0 ? " e " : "") . "$atividades_count atividade(s)" : "";
        
        header('Location: ' . SITE_URL . '/associados?erro=registros_vinculados&msg=' . urlencode($mensagem_erro));
        exit;
    }
    
    // Iniciar transação para garantir a integridade dos dados
    $db->beginTransaction();
    
    try {
        // Primeiro, excluir as dependências (se houver)
        // Exemplo: $db->prepare("DELETE FROM dependencias WHERE associado_id = ?")->execute([$id]);
        
        // Depois, excluir o associado
        $stmt = $db->prepare("DELETE FROM associados WHERE id = ?");
        $stmt->execute([$id]);
        
        // Confirmar a transação
        $db->commit();
        
        // Redirecionar com mensagem de sucesso
        header('Location: ' . SITE_URL . '/associados?msg=excluido&nome=' . urlencode($associado['nome'] . ' ' . $associado['sobrenome']));
        exit;
        
    } catch (Exception $e) {
        // Em caso de erro, desfaz a transação
        $db->rollBack();
        throw $e; // Relança a exceção para ser tratada no bloco catch externo
    }
    
} catch (PDOException $e) {
    // Registrar o erro em um log
    error_log("Erro ao excluir associado ID $id: " . $e->getMessage());
    
    // Redirecionar com mensagem de erro
    header('Location: ' . SITE_URL . '/associados?erro=exclusao&msg=' . urlencode('Erro ao excluir o associado. Por favor, tente novamente.'));
    exit;
}