<?php
/**
 * Migração: Registro de ciência do GESTOR sobre o status das visitas agendadas.
 *
 * Adiciona colunas em visitas_tecnicas para que o gestor/técnico possa
 * registrar que tomou ciência do status atual do agendamento (Confirmado /
 * Recusou / Pendente), individualmente por visita, gerando histórico para
 * relatórios futuros.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Migração: ciência do gestor (visitas_tecnicas) ===\n\n";

$check = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE 'gestor_ciente'");
if ($check->fetch()) {
    echo "Coluna 'gestor_ciente' já existe. Pulando criação.\n";
} else {
    $db->exec("ALTER TABLE visitas_tecnicas
               ADD COLUMN gestor_ciente TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Gestor registrou ciência do status do agendamento' AFTER ciente_recusada_em,
               ADD COLUMN gestor_ciente_em DATETIME NULL COMMENT 'Quando o gestor registrou ciência' AFTER gestor_ciente,
               ADD COLUMN gestor_ciente_user_id INT NULL COMMENT 'ID do usuário que registrou ciência' AFTER gestor_ciente_em,
               ADD COLUMN gestor_ciente_user_table VARCHAR(20) NULL COMMENT 'Tabela do usuário (usuarios/associados)' AFTER gestor_ciente_user_id");
    echo "Colunas 'gestor_ciente', 'gestor_ciente_em', 'gestor_ciente_user_id' e 'gestor_ciente_user_table' adicionadas.\n";
}

foreach (['gestor_ciente', 'gestor_ciente_em', 'gestor_ciente_user_id', 'gestor_ciente_user_table'] as $col) {
    $stmt = $db->query("SHOW COLUMNS FROM visitas_tecnicas LIKE '" . $col . "'");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  $col: " . ($r ? $r['Type'] : 'ausente') . "\n";
}

echo "\nMigração concluída!\n";
