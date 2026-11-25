<?php
/**
 * Migração idempotente: Módulo de Agroindústrias
 *
 * 1. Cria a tabela `agroindustrias` (entidade da agroindústria pertencente a um
 *    associado) — suporta 1 associado → N agroindústrias e o caso "agricultor
 *    com agroindústria".
 * 2. Adiciona a coluna `tem_agroindustria` em `associados`.
 * 3. Backfill: associados já cadastrados como produtor de agroindústria passam
 *    a ter `tem_agroindustria = 1`.
 *
 * Rode: php dev-utils/migrate_agroindustrias.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

function _agro_tabelaExiste(PDO $pdo, $tabela): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tabela]);
    return (bool) $stmt->fetch();
}

function _agro_colunaExiste(PDO $pdo, $tabela, $coluna): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
    $stmt->execute([$coluna]);
    return (bool) $stmt->fetch();
}

echo "=== Tabela agroindustrias ===\n";
if (!_agro_tabelaExiste($db, 'agroindustrias')) {
    $db->exec("CREATE TABLE IF NOT EXISTS agroindustrias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        associado_id INT NOT NULL COMMENT 'Associado dono da agroindústria',

        -- Identificação
        razao_social VARCHAR(200) NULL,
        nome_fantasia VARCHAR(200) NULL,
        cnpj VARCHAR(18) NULL,
        inscricao_estadual VARCHAR(30) NULL,
        telefone VARCHAR(15) NULL,
        email VARCHAR(100) NULL,
        responsavel_tecnico VARCHAR(150) NULL COMMENT 'Responsável técnico/RT',

        -- Endereço
        endereco VARCHAR(200) NULL,
        numero VARCHAR(10) NULL,
        bairro VARCHAR(100) NULL,
        cidade VARCHAR(100) NULL,
        estado CHAR(2) NULL,
        cep VARCHAR(9) NULL,

        -- Situação
        status ENUM('Ativa','Inativa') NOT NULL DEFAULT 'Ativa',
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,

        -- Auditoria
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
        INDEX idx_agro_associado (associado_id),
        INDEX idx_agro_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Agroindústrias vinculadas a associados'");
    echo "  agroindustrias criada.\n";
} else {
    echo "  agroindustrias já existe.\n";
}

echo "=== associados.tem_agroindustria ===\n";
if (!_agro_colunaExiste($db, 'associados', 'tem_agroindustria')) {
    $db->exec("ALTER TABLE associados
               ADD COLUMN tem_agroindustria TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Possui agroindústria (pode ser também agricultor)' AFTER tipo_usuario");
    echo "  coluna tem_agroindustria adicionada.\n";
} else {
    echo "  coluna tem_agroindustria já existe.\n";
}

echo "=== Backfill (produtores de agroindústria já cadastrados) ===\n";
$stmt = $db->query("UPDATE associados
                    SET tem_agroindustria = 1
                    WHERE tipo_usuario IN ('produtor_agroindustria','agroindustria','agricultor_agroindustria')
                      AND status <> 'inativo'");
echo "  associados marcados: " . $stmt->rowCount() . "\n";

echo "\nMigração concluída!\n";
