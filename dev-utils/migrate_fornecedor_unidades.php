<?php
/*
 * Migração idempotente — Abordagem B: fornecedores vinculados a unidades produtivas
 * por tipo (propriedade / agroindústria / criação animal).
 *
 * Cria a tabela pivot `fornecedor_unidades` permitindo que UM fornecedor atenda
 * VÁRIAS unidades de TIPOS diferentes (ex.: agroindústria + criação animal).
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "== Tabela fornecedor_unidades ==\n";
$existe = $db->query("SHOW TABLES LIKE 'fornecedor_unidades'")->fetch();
if ($existe) {
    // idempotente: garante colunas/índices
    $db->exec("ALTER TABLE fornecedor_unidades ADD COLUMN excluido TINYINT(1) NOT NULL DEFAULT 0 AFTER associado_id");
    $db->exec("ALTER TABLE fornecedor_unidades ADD COLUMN criado_por INT DEFAULT NULL AFTER excluido");
    $db->exec("ALTER TABLE fornecedor_unidades ADD COLUMN criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER criado_por");
    echo "tabela já existe; colunas garantidas\n";
} else {
    $db->exec("CREATE TABLE fornecedor_unidades (
        id INT NOT NULL AUTO_INCREMENT,
        fornecedor_id INT NOT NULL,
        unidade_tipo ENUM('propriedade','agroindustria','animal') NOT NULL,
        unidade_id INT NOT NULL,
        associado_id INT DEFAULT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT DEFAULT NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_forn_unidade (fornecedor_id, unidade_tipo, unidade_id),
        KEY idx_forn_unid_unidade (unidade_tipo, unidade_id),
        KEY idx_forn_unid_assoc (associado_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Vínculo de fornecedores a unidades produtivas (multi-tipo)'");
    echo "tabela criada\n";
}

// Check de colunas (para idempotência com charset/índices)
$cols = $db->query("SHOW COLUMNS FROM fornecedor_unidades")->fetchAll(PDO::FETCH_COLUMN);
echo "colunas: " . implode(',', $cols) . "\n";

// Backfill: fornecedores existentes ficam SEM vínculo (visíveis apenas a gestores)
// até serem reatribuídos nas telas. Não atribuímos dono porque não havia o dado.
$count = $db->query("SELECT COUNT(*) FROM fornecedores")->fetchColumn();
echo "fornecedores existentes: $count (sem vínculo na pivot até reatribuição)\n";

echo "== FIM ==\n";