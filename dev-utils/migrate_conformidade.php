<?php
/**
 * Migração idempotente: Fase 1 — Conformidade & Governança
 * - NFR03: tabela audit_log
 * - NFR01: coluna excluido (soft delete) em tabelas de conformidade
 * Rode: php dev-utils/migrate_conformidade.php
 */
require __DIR__ . '/../config/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

function colunaExiste(PDO $pdo, $tabela, $coluna) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tabela` LIKE ?");
        $stmt->execute([$coluna]);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function tabelaExiste(PDO $pdo, $tabela) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tabela]);
    return (bool) $stmt->fetch();
}

echo "=== NFR03: audit_log ===\n";
if (!tabelaExiste($pdo, 'audit_log')) {
    $pdo->exec("CREATE TABLE audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        acao VARCHAR(50) NOT NULL,
        tabela VARCHAR(50) NULL,
        registro_id INT NULL,
        descricao VARCHAR(500) NULL,
        ip VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_audit_tabela_registro (tabela, registro_id),
        KEY idx_audit_acao (acao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "criada.\n";
} else {
    echo "já existe.\n";
}

echo "=== NFR01: coluna excluido ===\n";
$tabelas = ['atividades_diarias', 'insumos', 'lotes_producao', 'ocorrencias_manejo', 'certificacao_participativa', 'plano_manejo'];
foreach ($tabelas as $t) {
    if (colunaExiste($pdo, $t, 'excluido')) {
        echo "  $t: já tem excluido\n";
        continue;
    }
    if (tabelaExiste($pdo, $t)) {
        $pdo->exec("ALTER TABLE `$t` ADD COLUMN excluido TINYINT(1) NOT NULL DEFAULT 0");
        echo "  $t: coluna excluido adicionada\n";
    } else {
        echo "  $t: tabela não existe (ignorada)\n";
    }
}

echo "=== Fase 2 (agroindústria): tabelas ===\n";
$fase2 = [
    'receitas_agroindustria' => "CREATE TABLE IF NOT EXISTS receitas_agroindustria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_produto_final VARCHAR(150) NOT NULL,
        descricao TEXT NULL,
        percentual_organico_calculado DECIMAL(5,2) NOT NULL DEFAULT 0,
        selo_autorizado ENUM('PRODUTO_ORGANICO_BRASIL','INGREDIENTES_ORGANICOS','SEM_SELO') NOT NULL DEFAULT 'SEM_SELO',
        associado_id INT NULL,
        status ENUM('Rascunho','Ativa','Inativa') NOT NULL DEFAULT 'Rascunho',
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'ingredientes_receita' => "CREATE TABLE IF NOT EXISTS ingredientes_receita (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receita_id INT NOT NULL,
        nome_ingrediente VARCHAR(100) NOT NULL,
        eh_organico TINYINT(1) NOT NULL DEFAULT 1,
        proporcao_porcentagem DECIMAL(5,2) NOT NULL,
        observacoes VARCHAR(255) NULL,
        KEY idx_ingredientes_receita (receita_id),
        CONSTRAINT fk_ing_receita FOREIGN KEY (receita_id) REFERENCES receitas_agroindustria(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'controle_higienizacao' => "CREATE TABLE IF NOT EXISTS controle_higienizacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_hora_inicio DATETIME NOT NULL,
        data_hora_fim DATETIME NULL,
        equipamento_linha VARCHAR(100) NOT NULL,
        sanitizante_utilizado VARCHAR(100) NOT NULL,
        responsavel_nome VARCHAR(100) NOT NULL,
        associado_id INT NULL,
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'ordens_producao' => "CREATE TABLE IF NOT EXISTS ordens_producao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receita_id INT NOT NULL,
        higienizacao_previa_id INT NULL,
        lote_processado_final VARCHAR(50) NOT NULL,
        data_processamento DATETIME NOT NULL,
        quantidade_final_obtida DECIMAL(10,2) NOT NULL,
        unidade_medida VARCHAR(10) NOT NULL DEFAULT 'KG',
        status_auditoria ENUM('APROVADO','ALERTA_FRAUDE','EM_ANALISE') NOT NULL DEFAULT 'EM_ANALISE',
        percentual_organico DECIMAL(5,2) NULL,
        selo_autorizado VARCHAR(30) NULL,
        associado_id INT NULL,
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_op_receita (receita_id),
        CONSTRAINT fk_op_receita FOREIGN KEY (receita_id) REFERENCES receitas_agroindustria(id),
        CONSTRAINT fk_op_higienizacao FOREIGN KEY (higienizacao_previa_id) REFERENCES controle_higienizacao(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'rastreabilidade_insumos_processo' => "CREATE TABLE IF NOT EXISTS rastreabilidade_insumos_processo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ordem_producao_id INT NOT NULL,
        lote_primario_id INT NULL,
        produto_id INT NULL,
        quantidade_utilizada DECIMAL(10,2) NOT NULL,
        unidade_medida VARCHAR(10) NOT NULL DEFAULT 'KG',
        KEY idx_rast_op (ordem_producao_id),
        CONSTRAINT fk_rast_op FOREIGN KEY (ordem_producao_id) REFERENCES ordens_producao(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($fase2 as $tabela => $sql) {
    if (!tabelaExiste($pdo, $tabela)) {
        $pdo->exec($sql);
        echo "  $tabela criada\n";
    } else {
        echo "  $tabela já existe\n";
    }
}

echo "=== Fase 3: colunas UPO ===\n";
// RF02: zona de amortecimento em propriedades
if (tabelaExiste($pdo, 'propriedades') && !colunaExiste($pdo, 'propriedades', 'zona_amortecimento')) {
    $pdo->exec("ALTER TABLE propriedades ADD COLUMN zona_amortecimento TEXT NULL");
    echo "  propriedades.zona_amortecimento adicionada\n";
} else {
    echo "  propriedades.zona_amortecimento ok\n";
}
// RF03: histórico de conversão
if (!tabelaExiste($pdo, 'historico_conversao')) {
    $pdo->exec("CREATE TABLE historico_conversao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        propriedade_id INT NULL,
        associado_id INT NULL,
        tipo_uso_solo VARCHAR(100) NOT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NULL,
        status ENUM('EM_CONVERSAO','ORGANICO','CONVENCIONAL') NOT NULL DEFAULT 'EM_CONVERSAO',
        observacoes TEXT NULL,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_hist_conv_prop (propriedade_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  historico_conversao criada\n";
} else {
    echo "  historico_conversao já existe\n";
}

echo "=== Fase 3: RF01 tipo de certificação ===\n";
if (tabelaExiste($pdo, 'associados') && !colunaExiste($pdo, 'associados', 'tipo_certificacao')) {
    $pdo->exec("ALTER TABLE associados ADD COLUMN tipo_certificacao ENUM('OCS','SPG','AUDITORIA') NOT NULL DEFAULT 'SPG' AFTER certificadora_id");
    echo "  associados.tipo_certificacao adicionada\n";
} else {
    echo "  associados.tipo_certificacao ok\n";
}

echo "=== Fase 3: RF05 manejo animal ===\n";
$fase3 = [
    'animais' => "CREATE TABLE IF NOT EXISTS animais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        nome_animal VARCHAR(100) NOT NULL,
        especie VARCHAR(60) NOT NULL,
        raca VARCHAR(60) NULL,
        identificacao VARCHAR(50) NULL,
        sexo ENUM('M','F') NULL,
        data_nascimento DATE NULL,
        status ENUM('ATIVO','VENDIDO','ABATIDO','MORTO') NOT NULL DEFAULT 'ATIVO',
        origem_conversao ENUM('ORGANICO','EM_CONVERSAO','CONVENCIONAL') NOT NULL DEFAULT 'ORGANICO',
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_animais_associado (associado_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    'manejo_animal_registros' => "CREATE TABLE IF NOT EXISTS manejo_animal_registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        animal_id INT NOT NULL,
        tipo_registro ENUM('CLINICO','VACINACAO','ALIMENTACAO') NOT NULL,
        data_registro DATE NOT NULL,
        descricao VARCHAR(500) NULL,
        produto_utilizado VARCHAR(150) NULL,
        dose_quantidade VARCHAR(60) NULL,
        responsavel VARCHAR(100) NULL,
        observacoes TEXT NULL,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mareg_animal (animal_id),
        CONSTRAINT fk_mareg_animal FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($fase3 as $tabela => $sql) {
    if (!tabelaExiste($pdo, $tabela)) {
        $pdo->exec($sql);
        echo "  $tabela criada\n";
    } else {
        echo "  $tabela já existe\n";
    }
}

echo "\nMigração concluída.\n";
