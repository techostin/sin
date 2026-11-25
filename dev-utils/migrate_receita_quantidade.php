<?php
/**
 * Migração: adiciona quantidade e unidade_medida aos ingredientes de receita.
 * O percentual orgânico passa a ser calculado pela soma das quantidades
 * dos insumos orgânicos sobre o total (baseado em peso/volume).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

$db->exec("ALTER TABLE ingredientes_receita
    ADD COLUMN quantidade DECIMAL(10,3) NULL DEFAULT NULL AFTER eh_organico,
    ADD COLUMN unidade_medida VARCHAR(10) NULL DEFAULT 'kg' AFTER quantidade");

// proporcao_porcentagem agora guarda o peso base (gramas/mL), não uma porcentagem
$db->exec("ALTER TABLE ingredientes_receita MODIFY proporcao_porcentagem DECIMAL(12,3) NOT NULL DEFAULT 0");

echo "PRONTO: colunas quantidade e unidade_medida adicionadas em ingredientes_receita.\n";