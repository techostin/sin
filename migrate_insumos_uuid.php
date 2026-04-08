<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Verificar se a coluna já existe
    $stmt = $db->query("SHOW COLUMNS FROM insumos LIKE 'uuid'");
    if ($stmt->rowCount() == 0) {
        echo "Adicionando coluna uuid...\n";
        $db->exec("ALTER TABLE insumos ADD COLUMN uuid CHAR(36) AFTER id, ADD UNIQUE KEY (uuid)");
    } else {
        echo "Coluna uuid já existe.\n";
    }

    // 2. Popular UUIDs vazios
    $stmt = $db->query("SELECT id FROM insumos WHERE uuid IS NULL OR uuid = ''");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($registros) > 0) {
        echo "Populando " . count($registros) . " registros com UUIDs...\n";
        $updateStmt = $db->prepare("UPDATE insumos SET uuid = :uuid WHERE id = :id");
        foreach ($registros as $reg) {
            $uuid = generate_uuid();
            $updateStmt->execute(['uuid' => $uuid, 'id' => $reg['id']]);
            echo "ID {$reg['id']} -> UUID {$uuid}\n";
        }
    } else {
        echo "Todos os registros já possuem UUID.\n";
    }

    echo "Sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
