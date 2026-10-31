<?php
/**
 * Migração: Adicionar colunas de confirmação "ciente" à tabela visitas_tecnicas
 * 
 * A cada visita agendada, o agricultor deve confirmar que está ciente do
 * agendamento (modal no painel). O gestor/técnico vê esse status.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Migração: ciente para visitas_tecnicas ===\n\n";

$check = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'ciente'");
if ($check->fetch()) {
    echo "Coluna 'ciente' já existe. Pulando criação.\n";
} else {
    $db->exec("ALTER TABLE visitas_tecnicas
               ADD COLUMN ciente TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Agricultor confirmou que está ciente do agendamento' AFTER status,
               ADD COLUMN ciente_em DATETIME NULL COMMENT 'Quando o agricultor confirmou ciência' AFTER ciente");
    echo "Colunas 'ciente' e 'ciente_em' adicionadas.\n";
}

$stmt = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'ciente'");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt2 = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'ciente_em'");
$r2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "\nResumo:\n  ciente:     {$r['Type']} default={$r['Default']}\n";
echo "  ciente_em:  " . ($r2 ? $r2['Type'] : 'ausente') . "\n";
echo "\nMigração concluída!\n";
