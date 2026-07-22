<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

$user_id = 13;

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar na tabela usuarios
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Verificando usuário ID: $user_id</h2>";
    
    if ($usuario) {
        echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin: 10px 0;'>";
        echo "<strong>Usuário encontrado na tabela 'usuarios':</strong><br>";
        echo "<pre>";
        print_r($usuario);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;'>";
        echo "<strong>Usuário NÃO encontrado na tabela 'usuarios'</strong><br>";
        echo "</div>";
    }
    
    // Verificar na tabela associados também
    $stmt = $db->prepare("SELECT * FROM associados WHERE id = ?");
    $stmt->execute([$user_id]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($associado) {
        echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin: 10px 0;'>";
        echo "<strong>Usuário encontrado na tabela 'associados':</strong><br>";
        echo "<pre>";
        print_r($associado);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;'>";
        echo "<strong>Usuário NÃO encontrado na tabela 'associados'</strong><br>";
        echo "</div>";
    }
    
    // Listar todos os usuários para debug
    echo "<h3>Todos os usuários na tabela 'usuarios':</h3>";
    $stmt = $db->query("SELECT id, nome, email, nivel, status FROM usuarios ORDER BY id");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Nível</th><th>Status</th></tr>";
    foreach ($usuarios as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['nome']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['nivel']}</td>";
        echo "<td>{$u['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin: 10px 0;'>";
    echo "<strong>Erro:</strong> " . $e->getMessage();
    echo "</div>";
}
?> 