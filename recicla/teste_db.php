<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SELECT * FROM eventos LIMIT 5');
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Eventos no banco de dados:</h1>";
    echo "<pre>";
    print_r($eventos);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?> 