<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== USUÁRIOS (tabela usuarios) ===\n";
    $stmt = $db->query("SELECT id, nome, email, nivel, tipo, status FROM usuarios ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("ID: %s | Nome: %s | Email: %s | Nivel: %s | Tipo: %s | Status: %s\n",
            $row['id'], $row['nome'], $row['email'], $row['nivel'], $row['tipo'], $row['status']);
    }
    
    echo "\n=== ASSOCIADOS (tabela associados) ===\n";
    $stmt2 = $db->query("SELECT id, nome, email, nivel, tipo, status FROM associados ORDER BY id LIMIT 20");
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        printf("ID: %s | Nome: %s | Email: %s | Nivel: %s | Tipo: %s | Status: %s\n",
            $row['id'], $row['nome'], $row['email'], $row['nivel'], $row['tipo'], $row['status']);
    }
    
    echo "\n=== NÍVEIS ===\n";
    echo "NIVEL_ADMIN = 1 | NIVEL_GESTOR = 2 | NIVEL_USUARIO = 3\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
