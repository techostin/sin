<?php
/**
 * Migração idempotente: Vínculo de produtos às unidades produtivas
 *
 * Cada produto passa a pertencer a UMA unidade produtiva (propriedade,
 * agroindústria ou ambiente de criação animal) e a um associado, permitindo
 * que cada Empresa/Razão social tenha sua própria lista de produtos.
 *
 * 1. Adiciona em `produtos`: associado_id, unidade_tipo, unidade_id.
 * 2. Cria `propriedade_membros` (N associados ↔ 1 propriedade).
 * 3. Cria `ambiente_criacao_membros` (N associados ↔ 1 ambiente de criação).
 * 4. Backfill: o proprietário de cada unidade vira membro automaticamente
 *    (mesmo padrão já usado em agroindustria_membros).
 * 5. Migra produtos existentes: preenche dono/unidade via lavoura→propriedade
 *    (produtos agrícolas) ou deixa como "sistema" (sem unidade) para revisão.
 *
 * Rode: php dev-utils/migrate_produto_unidades.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

function pu_tabelaExiste(PDO $db, string $nome): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$nome]);
    return (int)$stmt->fetchColumn() > 0;
}

function pu_colunaExiste(PDO $db, string $tabela, string $coluna): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tabela, $coluna]);
    return (int)$stmt->fetchColumn() > 0;
}

function pu_indiceExiste(PDO $db, string $tabela, string $indice): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
    $stmt->execute([$tabela, $indice]);
    return (int)$stmt->fetchColumn() > 0;
}

// ============================================================
// 1) produtos: associado_id + unidade_tipo + unidade_id
// ============================================================
echo "=== produtos ===\n";
if (pu_tabelaExiste($db, 'produtos')) {
    if (!pu_colunaExiste($db, 'produtos', 'associado_id')) {
        $db->exec("ALTER TABLE produtos ADD COLUMN associado_id INT NULL AFTER lavoura_id, ADD INDEX idx_produto_assoc (associado_id)");
        echo "  coluna associado_id adicionada.\n";
    } else {
        echo "  coluna associado_id já existe.\n";
    }
    if (!pu_colunaExiste($db, 'produtos', 'unidade_tipo')) {
        $db->exec("ALTER TABLE produtos ADD COLUMN unidade_tipo ENUM('propriedade','agroindustria','animal') NULL AFTER associado_id");
        echo "  coluna unidade_tipo adicionada.\n";
    } else {
        echo "  coluna unidade_tipo já existe.\n";
    }
    if (!pu_colunaExiste($db, 'produtos', 'unidade_id')) {
        $db->exec("ALTER TABLE produtos ADD COLUMN unidade_id INT NULL AFTER unidade_tipo, ADD INDEX idx_produto_unidade (unidade_tipo, unidade_id)");
        echo "  coluna unidade_id adicionada.\n";
    } else {
        echo "  coluna unidade_id já existe.\n";
    }
}

// ============================================================
// 2) propriedade_membros
// ============================================================
echo "\n=== propriedade_membros ===\n";
if (!pu_tabelaExiste($db, 'propriedade_membros')) {
    $db->exec("CREATE TABLE IF NOT EXISTS propriedade_membros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        propriedade_id INT NOT NULL,
        associado_id INT NOT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_prop_membro (propriedade_id, associado_id),
        INDEX idx_prop_membro_assoc (associado_id),
        CONSTRAINT fk_prop_membro_prop FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Associados que integram cada propriedade'");
    echo "  tabela propriedade_membros criada.\n";
} else {
    echo "  tabela propriedade_membros já existe.\n";
}

// Backfill: proprietário vira membro de cada propriedade
if (pu_tabelaExiste($db, 'propriedades')) {
    $stmt = $db->query("SELECT id AS propriedade_id, associado_id FROM propriedades WHERE associado_id IS NOT NULL");
    $ins = $db->prepare("INSERT IGNORE INTO propriedade_membros (propriedade_id, associado_id) VALUES (?, ?)");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ins->execute([(int)$r['propriedade_id'], (int)$r['associado_id']]);
        $count += $ins->rowCount();
    }
    echo "  proprietários vinculados como membros: {$count}.\n";
}

// ============================================================
// 3) ambiente_criacao_membros
// ============================================================
echo "\n=== ambiente_criacao_membros ===\n";
if (!pu_tabelaExiste($db, 'ambiente_criacao_membros')) {
    $db->exec("CREATE TABLE IF NOT EXISTS ambiente_criacao_membros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ambiente_id INT NOT NULL,
        associado_id INT NOT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ambiente_membro (ambiente_id, associado_id),
        INDEX idx_ambiente_membro_assoc (associado_id),
        CONSTRAINT fk_ambiente_membro_ambiente FOREIGN KEY (ambiente_id) REFERENCES ambiente_criacao_animal(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Associados que integram cada ambiente de criação animal'");
    echo "  tabela ambiente_criacao_membros criada.\n";
} else {
    echo "  tabela ambiente_criacao_membros já existe.\n";
}

// Backfill: proprietário vira membro de cada ambiente
if (pu_tabelaExiste($db, 'ambiente_criacao_animal')) {
    $stmt = $db->query("SELECT id AS ambiente_id, associado_id FROM ambiente_criacao_animal WHERE associado_id IS NOT NULL");
    $ins = $db->prepare("INSERT IGNORE INTO ambiente_criacao_membros (ambiente_id, associado_id) VALUES (?, ?)");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ins->execute([(int)$r['ambiente_id'], (int)$r['associado_id']]);
        $count += $ins->rowCount();
    }
    echo "  proprietários vinculados como membros: {$count}.\n";
}

// ============================================================
// 4) Migrar produtos existentes (dono + unidade via lavoura)
// ============================================================
echo "\n=== backfill produtos ===\n";
if (pu_tabelaExiste($db, 'produtos')) {
    // Produtos agrícolas: dono/unidade derivados da lavoura → propriedade
    $upd = $db->prepare("
        UPDATE produtos pr
        JOIN lavouras l ON pr.lavoura_id = l.id
        JOIN propriedades p ON l.propriedade_id = p.id
        SET pr.associado_id = p.associado_id,
            pr.unidade_tipo = 'propriedade',
            pr.unidade_id = p.id
        WHERE pr.associado_id IS NULL
    ");
    $upd->execute();
    echo "  produtos agrícolas vinculados: {$upd->rowCount()}.\n";

    // Produtos sem lavoura (agroindústria) permanecem SEM dono (visíveis apenas
    // para Admin/Gestor) até que o formulário de produto vincule uma unidade.
    echo "  produtos sem lavoura sem dono: (backfill manual opcional).\n";
}

echo "\nMigração concluída!\n";
