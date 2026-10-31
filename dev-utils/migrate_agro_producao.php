<?php
/**
 * Migração idempotente: Caderno de Produção da Agroindústria
 *
 * Cria as tabelas exclusivas do módulo de Agroindústria (escopo por
 * associado, em vez da cadeia produto → lavoura → propriedade do
 * Caderno de Campo):
 *   - agro_setores               (Setor no lugar de Lavoura)
 *   - agro_categorias_atividades (Categorias)
 *   - agro_atividades_diarias    (Atividades Diárias)
 *   - agro_ocorrencias           (Ocorrências)
 *
 * Também adiciona `associado_id` em `lotes_producao` e faz o backfill
 * da cadeia produto → lavoura → propriedade, permitindo que lotes de
 * agroindústria (produtos sem lavoura) sejam vinculados ao produtor.
 *
 * Rode: php dev-utils/migrate_agro_producao.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

function tabelaExiste(PDO $db, string $nome): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$nome]);
    return (int)$stmt->fetchColumn() > 0;
}

function colunaExiste(PDO $db, string $tabela, string $coluna): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tabela, $coluna]);
    return (int)$stmt->fetchColumn() > 0;
}

// ============================================================
// 1) lotes_producao.associado_id + backfill
// ============================================================
echo "=== lotes_producao.associado_id ===\n";
if (tabelaExiste($db, 'lotes_producao') && !colunaExiste($db, 'lotes_producao', 'associado_id')) {
    $db->exec("ALTER TABLE lotes_producao ADD COLUMN associado_id INT DEFAULT NULL AFTER produto_id");
    echo "Coluna associado_id adicionada.\n";
} else {
    echo "Coluna associado_id já existe (ou tabela ausente).\n";
}

if (tabelaExiste($db, 'lotes_producao')) {
    $stmt = $db->exec(
        "UPDATE lotes_producao lp
         LEFT JOIN produtos p ON lp.produto_id = p.id
         LEFT JOIN lavouras l ON p.lavoura_id = l.id
         LEFT JOIN propriedades pr ON l.propriedade_id = pr.id
         SET lp.associado_id = pr.associado_id
         WHERE lp.associado_id IS NULL AND pr.associado_id IS NOT NULL"
    );
    echo "Backfill por cadeia produto→lavoura→propriedade concluído.\n";
}

// ============================================================
// 2) agro_setores
// ============================================================
echo "\n=== agro_setores ===\n";
if (!tabelaExiste($db, 'agro_setores')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agro_setores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) DEFAULT NULL,
        associado_id INT NOT NULL,
        agroindustria_id INT DEFAULT NULL,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT DEFAULT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agro_setores_associado (associado_id),
        INDEX idx_agro_setores_agroindustria (agroindustria_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Setores de produção da agroindústria'");
    echo "Tabela agro_setores criada.\n";
} else {
    echo "Tabela agro_setores já existe.\n";
}

// ============================================================
// 3) agro_categorias_atividades (categorias globais, como no caderno)
// ============================================================
echo "\n=== agro_categorias_atividades ===\n";
if (!tabelaExiste($db, 'agro_categorias_atividades')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agro_categorias_atividades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) DEFAULT NULL,
        associado_id INT DEFAULT NULL,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT DEFAULT NULL,
        cor VARCHAR(7) NOT NULL DEFAULT '#0d6efd',
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agro_cat_associado (associado_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Categorias de atividades da agroindústria'");
    echo "Tabela agro_categorias_atividades criada.\n";

    $padrao = [
        ['Recebimento de Matéria-Prima', 'Conferência e recebimento de insumos e matéria-prima.', '#198754'],
        ['Processamento', 'Etapas de transformação do produto.', '#0d6efd'],
        ['Embalagem', 'Acondicionamento e rotulagem do produto final.', '#fd7e14'],
        ['Controle de Qualidade', 'Análises e verificações de conformidade.', '#dc3545'],
        ['Higienização', 'Limpeza e sanitização de equipamentos e instalações.', '#0dcaf0'],
        ['Estocagem e Expedição', 'Armazenamento e saída de produtos.', '#6f42c1'],
    ];
    $ins = $db->prepare("INSERT INTO agro_categorias_atividades (nome, descricao, cor) VALUES (?, ?, ?)");
    foreach ($padrao as [$nome, $desc, $cor]) {
        $ins->execute([$nome, $desc, $cor]);
    }
    echo "Categorias padrão inseridas (" . count($padrao) . ").\n";
} else {
    echo "Tabela agro_categorias_atividades já existe.\n";
}

// ============================================================
// 4) agro_atividades_diarias
// ============================================================
echo "\n=== agro_atividades_diarias ===\n";
if (!tabelaExiste($db, 'agro_atividades_diarias')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agro_atividades_diarias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) DEFAULT NULL,
        associado_id INT NOT NULL,
        setor_id INT DEFAULT NULL,
        categoria_id INT DEFAULT NULL,
        nome VARCHAR(255) NOT NULL,
        data_atividade DATE NOT NULL,
        hora_inicio TIME DEFAULT NULL,
        hora_fim TIME DEFAULT NULL,
        descricao TEXT DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Concluída',
        tipo_atividade VARCHAR(100) DEFAULT NULL,
        insumo_utilizado VARCHAR(255) DEFAULT NULL,
        observacoes TEXT DEFAULT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agro_ativ_associado (associado_id),
        INDEX idx_agro_ativ_setor (setor_id),
        INDEX idx_agro_ativ_categoria (categoria_id),
        INDEX idx_agro_ativ_data (data_atividade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Atividades diárias da agroindústria'");
    echo "Tabela agro_atividades_diarias criada.\n";
} else {
    echo "Tabela agro_atividades_diarias já existe.\n";
}

// ============================================================
// 5) agro_ocorrencias
// ============================================================
echo "\n=== agro_ocorrencias ===\n";
if (!tabelaExiste($db, 'agro_ocorrencias')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agro_ocorrencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) DEFAULT NULL,
        associado_id INT NOT NULL,
        setor_id INT DEFAULT NULL,
        tipo VARCHAR(60) NOT NULL DEFAULT 'Outro',
        titulo VARCHAR(150) NOT NULL,
        data_ocorrencia DATE NOT NULL,
        severidade ENUM('Baixa', 'Média', 'Alta') NOT NULL DEFAULT 'Baixa',
        descricao TEXT DEFAULT NULL,
        acao_manejo TEXT DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Aberta',
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_agro_ocorr_associado (associado_id),
        INDEX idx_agro_ocorr_setor (setor_id),
        INDEX idx_agro_ocorr_data (data_ocorrencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Ocorrências registradas na agroindústria'");
    echo "Tabela agro_ocorrencias criada.\n";
} else {
    echo "Tabela agro_ocorrencias já existe.\n";
}

echo "\nMigração concluída.\n";
