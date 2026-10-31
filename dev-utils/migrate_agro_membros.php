<?php
/**
 * Migração idempotente: Membros da Agroindústria + Insumos Utilizados na origem
 *
 * 1. Cria `agroindustria_membros` (vínculo N associados ↔ 1 agroindústria),
 *    permitindo que usuários X e Y da MESMA agroindústria compartilhem insumos.
 *    O proprietário (agroindustrias.associado_id) vira membro automaticamente.
 * 2. Adiciona `insumo_id` e `numero_nf` em `rastreabilidade_insumos_processo`
 *    para rastrear a origem do insumo (fornecedor/NF) até o lote final.
 *
 * Rode: php dev-utils/migrate_agro_membros.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

function m_tabelaExiste(PDO $db, string $nome): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$nome]);
    return (int)$stmt->fetchColumn() > 0;
}

function m_colunaExiste(PDO $db, string $tabela, string $coluna): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tabela, $coluna]);
    return (int)$stmt->fetchColumn() > 0;
}

// ============================================================
// 1) agroindustria_membros
// ============================================================
echo "=== agroindustria_membros ===\n";
if (!m_tabelaExiste($db, 'agroindustria_membros')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agroindustria_membros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agroindustria_id INT NOT NULL,
        associado_id INT NOT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_agro_membro (agroindustria_id, associado_id),
        INDEX idx_agro_membro_assoc (associado_id),
        CONSTRAINT fk_agro_membro_agro FOREIGN KEY (agroindustria_id) REFERENCES agroindustrias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Associados que integram cada agroindústria'");
    echo "  tabela agroindustria_membros criada.\n";
} else {
    echo "  tabela agroindustria_membros já existe.\n";
}

// Backfill: proprietário vira membro de cada agroindústria
if (m_tabelaExiste($db, 'agroindustrias')) {
    $stmt = $db->query("SELECT a.id AS agroindustria_id, a.associado_id FROM agroindustrias a WHERE a.excluido = 0 AND a.associado_id IS NOT NULL");
    $ins = $db->prepare("INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id) VALUES (?, ?)");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ins->execute([(int)$r['agroindustria_id'], (int)$r['associado_id']]);
        $count += $ins->rowCount();
    }
    echo "  proprietários vinculados como membros: {$count}.\n";
}

// ============================================================
// 2) rastreabilidade_insumos_processo: insumo_id + numero_nf
// ============================================================
echo "\n=== rastreabilidade_insumos_processo ===\n";
if (m_tabelaExiste($db, 'rastreabilidade_insumos_processo')) {
    if (!m_colunaExiste($db, 'rastreabilidade_insumos_processo', 'insumo_id')) {
        $db->exec("ALTER TABLE rastreabilidade_insumos_processo ADD COLUMN insumo_id INT NULL AFTER produto_id");
        echo "  coluna insumo_id adicionada.\n";
    } else {
        echo "  coluna insumo_id já existe.\n";
    }
    if (!m_colunaExiste($db, 'rastreabilidade_insumos_processo', 'numero_nf')) {
        $db->exec("ALTER TABLE rastreabilidade_insumos_processo ADD COLUMN numero_nf VARCHAR(30) NULL AFTER insumo_id");
        echo "  coluna numero_nf adicionada.\n";
    } else {
        echo "  coluna numero_nf já existe.\n";
    }
}

echo "\nMigração concluída!\n";