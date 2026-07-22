<?php
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "--- USUARIOS (kajiani) ---\n";
    $stmt = $db->prepare("SELECT id, nome, email, associado_id FROM usuarios WHERE nome LIKE ? OR email LIKE ?");
    $stmt->execute(['%kajiani%', '%kajiani%']);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo "\n--- ASSOCIADOS (kajiani) ---\n";
    $stmt = $db->prepare("SELECT id, nome, email FROM associados WHERE nome LIKE ? OR email LIKE ?");
    $stmt->execute(['%kajiani%', '%kajiani%']);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
