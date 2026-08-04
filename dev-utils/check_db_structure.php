<?php
require_once 'classes/Database.php';
require_once 'config/config.php';

$db = Database::getInstance()->getConnection();

echo "=== MIGRACAO: Adicionar colunas de perfil ===\n\n";

function addColumnIfNotExists($db, $table, $column, $after) {
    $check = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    if (!$check->fetch()) {
        $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} VARCHAR(100) DEFAULT NULL AFTER {$after}");
        echo "  [OK] Coluna '{$column}' adicionada em {$table}\n";
    } else {
        echo "  [OK] Coluna '{$column}' ja existe em {$table}\n";
    }
}

// Gestor fields: orgao, setor, especialidade
// Usuario field: nicho (agricultor / produtor_agroindustria)
addColumnIfNotExists($db, 'associados', 'orgao', 'tipo');
addColumnIfNotExists($db, 'associados', 'setor', 'orgao');
addColumnIfNotExists($db, 'associados', 'especialidade', 'setor');
addColumnIfNotExists($db, 'associados', 'nicho', 'especialidade');

addColumnIfNotExists($db, 'usuarios', 'orgao', 'tipo');
addColumnIfNotExists($db, 'usuarios', 'setor', 'orgao');
addColumnIfNotExists($db, 'usuarios', 'especialidade', 'setor');
addColumnIfNotExists($db, 'usuarios', 'nicho', 'especialidade');

echo "\n=== Estrutura atualizada com sucesso! ===\n\n";

echo "=== associados ===\n";
$stmt = $db->query('SHOW COLUMNS FROM associados');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . ($row['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
}

echo "\n=== usuarios ===\n";
$stmt = $db->query('SHOW COLUMNS FROM usuarios');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . ($row['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
}
