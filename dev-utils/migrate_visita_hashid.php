<?php
/**
 * Migração: Adicionar coluna hashid à tabela visitas_tecnicas
 * e preencher registros existentes com hash únicos
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Migração: hashid para visitas_tecnicas ===\n\n";

// 1. Verificar se coluna já existe
$stmt = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'hashid'");
if ($stmt->fetch()) {
    echo "Coluna 'hashid' já existe. Pulando criação.\n";
} else {
    // 2. Adicionar coluna hashid
    $sql = "ALTER TABLE visitas_tecnicas 
            ADD COLUMN hashid VARCHAR(32) NULL UNIQUE AFTER id,
            ADD INDEX idx_hashid (hashid)";
    $db->exec($sql);
    echo "Coluna 'hashid' adicionada com sucesso.\n";
}

// 3. Preencher registros existentes que não têm hashid
$stmt = $db->query("SELECT id FROM visitas_tecnicas WHERE hashid IS NULL");
$semHash = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($semHash) > 0) {
    echo "Preenchendo " . count($semHash) . " registros sem hashid...\n";
    $update = $db->prepare("UPDATE visitas_tecnicas SET hashid = ? WHERE id = ?");
    
    foreach ($semHash as $row) {
        $id = $row['id'];
        // Gerar hash único baseado no ID + microtime + salt
        $hash = substr(md5($id . '|' . microtime(true) . '|visita_salt'), 0, 16);
        $update->execute([$hash, $id]);
        echo "  ID $id -> hash: $hash\n";
    }
} else {
    echo "Todos os registros já possuem hashid.\n";
}

// 4. Verificar resultado final
$stmt = $db->query("SELECT COUNT(*) as total, COUNT(hashid) as com_hash FROM visitas_tecnicas");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nResumo: {$result['total']} registros, {$result['com_hash']} com hash.\n";

// 5. Listar alguns exemplos
$stmt = $db->query("SELECT id, hashid FROM visitas_tecnicas ORDER BY id DESC LIMIT 5");
echo "\nExemplos:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id']} -> hashid: {$row['hashid']}\n";
}

echo "\nMigração concluída!\n";

