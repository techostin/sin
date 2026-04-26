<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

$user = new User();
echo "Logado: " . ($user->isLoggedIn() ? 'Sim' : 'Não') . "\n";
if ($user->isLoggedIn()) {
    echo "ID: " . $_SESSION['user_id'] . "\n";
    echo "Nome: " . $_SESSION['user_nome'] . "\n";
    echo "Tabela: " . $_SESSION['user_table'] . "\n";
    echo "Associado ID: " . var_export($user->getAssociadoId(), true) . "\n";
    
    $currentUser = $user->getCurrentUser();
    echo "Dados do Banco:\n";
    print_r($currentUser);
}

try {
    $db = Database::getInstance()->getConnection();
    echo "\nEstrutura da tabela usuarios:\n";
    $stmt = $db->query("DESC usuarios");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
