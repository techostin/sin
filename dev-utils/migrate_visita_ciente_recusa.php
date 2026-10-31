<?php
/**
 * Migração: Permitir que o agricultor recuse/peça reagendamento de uma visita
 * agendada (ciente_recusada + motivo + data/hora).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Migração: recusa de data (visitas_tecnicas) ===\n\n";

$check = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'ciente_recusada'");
if ($check->fetch()) {
    echo "Coluna 'ciente_recusada' já existe. Pulando criação.\n";
} else {
    $db->exec("ALTER TABLE visitas_tecnicas
               ADD COLUMN ciente_recusada TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Agricultor não pode na data agendada (pede reagendamento)' AFTER ciente_em,
               ADD COLUMN ciente_recusada_motivo TEXT NULL COMMENT 'Motivo informado pelo agricultor ao recusar a data' AFTER ciente_recusada,
               ADD COLUMN ciente_recusada_em DATETIME NULL COMMENT 'Quando o agricultor recusou a data' AFTER ciente_recusada_motivo");
    echo "Colunas 'ciente_recusada', 'ciente_recusada_motivo' e 'ciente_recusada_em' adicionadas.\n";
}

foreach (['ciente_recusada', 'ciente_recusada_motivo', 'ciente_recusada_em'] as $col) {
    $stmt = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE '" . $col . "'");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  $col: " . ($r ? $r['Type'] : 'ausente') . "\n";
}

echo "\nMigração concluída!\n";
