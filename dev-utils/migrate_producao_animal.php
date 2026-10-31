<?php
/**
 * Migração idempotente: Produção Animal Orgânica
 * Base normativa: Portaria MAPA nº 52/2021 (Título III — Sistemas Orgânicos de
 * Produção Animal: nutrição arts. 34-37, ambiente de criação arts. 38-44,
 * manejo arts. 45-58, sanidade arts. 59-63, apicultura arts. 79-83),
 * Lei nº 10.831/2003 e Decreto nº 6.323/2007.
 *
 * Cria/expande:
 *  - animais (colunas de controle de rebanho e conversão)
 *  - animal_lotes
 *  - controle_alimentacao_animal
 *  - controle_sanidade_animal
 *  - controle_reproducao_animal
 *  - ambiente_criacao_animal
 *  - densidades_maximas_animais (valores de referência da norma)
 * Rode: php dev-utils/migrate_producao_animal.php
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

echo "=== Fase 1: expansão tabela animais ===\n";
$colunasAnimais = [
    'lote_id'                     => "ALTER TABLE animais ADD COLUMN lote_id INT NULL AFTER origem_conversao, ADD KEY idx_animais_lote (lote_id)",
    'peso_kg'                     => "ALTER TABLE animais ADD COLUMN peso_kg DECIMAL(8,2) NULL AFTER lote_id",
    'tipo_entrada'                => "ALTER TABLE animais ADD COLUMN tipo_entrada ENUM('NASCIDO_PROPRIEDADE','COMPRADO_ORGANICO','COMPRA_CONVENCIONAL','TRANSFERIDO_OCS') NULL AFTER peso_kg",
    'fornecedor_origem'           => "ALTER TABLE animais ADD COLUMN fornecedor_origem VARCHAR(150) NULL AFTER tipo_entrada",
    'data_entrada'                => "ALTER TABLE animais ADD COLUMN data_entrada DATE NULL AFTER fornecedor_origem",
    'observacoes_origem'          => "ALTER TABLE animais ADD COLUMN observacoes_origem TEXT NULL AFTER data_entrada",
];
foreach ($colunasAnimais as $coluna => $sql) {
    if (!colunaExiste($pdo, 'animais', $coluna)) {
        $pdo->exec($sql);
        echo "  animais.$coluna adicionada\n";
    } else {
        echo "  animais.$coluna já existe\n";
    }
}

echo "=== Fase 2: novo ENUM + coluna sanidade em manejo_animal_registros ===\n";
$tipoRegistro = $pdo->query("SHOW COLUMNS FROM manejo_animal_registros LIKE 'tipo_registro'")->fetch(PDO::FETCH_ASSOC);
if ($tipoRegistro && stripos($tipoRegistro['Type'], 'REPRODUCAO') === false) {
    $pdo->exec("ALTER TABLE manejo_animal_registros MODIFY COLUMN tipo_registro ENUM('CLINICO','VACINACAO','ALIMENTACAO','REPRODUCAO','PARASITARIO','BEM_ESTAR','OBITO') NOT NULL");
    echo "  tipo_registro expandido\n";
} else {
    echo "  tipo_registro já expandido\n";
}
if (!colunaExiste($pdo, 'manejo_animal_registros', 'periodo_carencia_horas')) {
    $pdo->exec("ALTER TABLE manejo_animal_registros ADD COLUMN periodo_carencia_horas INT NULL AFTER dose_quantidade");
    echo "  período_carencia_horas adicionada\n";
} else {
    echo "  período_carencia_horas já existe\n";
}

echo "=== Fase 3: novas tabelas ===\n";
$tabelas = [
    'animal_lotes' => "CREATE TABLE IF NOT EXISTS animal_lotes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        nome_lote VARCHAR(120) NOT NULL,
        especie VARCHAR(60) NULL,
        categoria VARCHAR(80) NULL,
        quantidade INT NULL DEFAULT 0,
        descricao TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_anut_lote_associado (associado_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'controle_alimentacao_animal' => "CREATE TABLE IF NOT EXISTS controle_alimentacao_animal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        animal_id INT NULL,
        lote_id INT NULL,
        data_alimentacao DATE NOT NULL,
        tipo_alimento ENUM('FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA') NOT NULL,
        produto_utilizado VARCHAR(150) NULL,
        quantidade_kg DECIMAL(10,2) NULL,
        percentual_materia_seca DECIMAL(5,2) NULL,
        origem ENUM('ORGANICO','EM_CONVERSAO','NAO_ORGANICO_AUTORIZADO') NOT NULL DEFAULT 'ORGANICO',
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_caali_animal (animal_id),
        KEY idx_caali_lote (lote_id),
        KEY idx_caali_associado (associado_id),
        CONSTRAINT fk_caali_animal FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE,
        CONSTRAINT fk_caali_lote FOREIGN KEY (lote_id) REFERENCES animal_lotes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'controle_sanidade_animal' => "CREATE TABLE IF NOT EXISTS controle_sanidade_animal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        animal_id INT NULL,
        lote_id INT NULL,
        data_tratamento DATE NOT NULL,
        tipo_tratamento ENUM('VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL') NOT NULL,
        produto_utilizado VARCHAR(150) NULL,
        motivo VARCHAR(250) NULL,
        dose_quantidade VARCHAR(60) NULL,
        via_aplicacao VARCHAR(40) NULL,
        responsavel VARCHAR(100) NULL,
        produto_normatizado TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = consta do Anexo II (Portaria 52/2021)',
        periodo_carencia_horas INT NULL COMMENT 'mínimo 96h em tratamento excepcional',
        data_liberacao_organica DATE NULL,
        aprovado_ocs TINYINT(1) NULL COMMENT 'tratamento excepcional aprovado pela OCS (art. 63)',
        numero_tratamento INT NULL DEFAULT 1 COMMENT 'limite de 2 tratamentos/ano por animal (não-vacinado)',
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_csan_animal (animal_id),
        KEY idx_csan_lote (lote_id),
        KEY idx_csan_associado (associado_id),
        CONSTRAINT fk_csan_animal FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE,
        CONSTRAINT fk_csan_lote FOREIGN KEY (lote_id) REFERENCES animal_lotes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'controle_reproducao_animal' => "CREATE TABLE IF NOT EXISTS controle_reproducao_animal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        animal_id INT NULL COMMENT 'animal mãe/fêmea',
        grupo_reprodutivo VARCHAR(80) NULL,
        tipo_evento ENUM('COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME') NOT NULL,
        data_evento DATE NOT NULL,
        macho_reprodutor_id INT NULL COMMENT 'identificação do macho',
        previsao_parto DATE NULL,
        nascidos_vivos INT NULL,
        natimortos INT NULL,
        periodo_aleitamento_dias INT NULL COMMENT 'mín. 90 (bovinos/bubalinos), 28 (ovinos/caprinos), 45 (suínos)',
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_crepro_animal (animal_id),
        KEY idx_crepro_associado (associado_id),
        CONSTRAINT fk_crepro_animal FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'ambiente_criacao_animal' => "CREATE TABLE IF NOT EXISTS ambiente_criacao_animal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        associado_id INT NULL,
        nome_instalacao VARCHAR(120) NOT NULL,
        especie ENUM('BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA') NULL,
        sistema ENUM('EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO') NULL,
        tipo_instalacao VARCHAR(80) NULL COMMENT 'pasto, piquete, galpão, estbulo, aviário, apiário...',
        area_total_m2 DECIMAL(10,2) NULL,
        quantidade_animais INT NULL,
        densidade_referencia DECIMAL(8,2) NULL COMMENT 'm²/animal referência da norma',
        tem_sombreamento_abrigo TINYINT(1) NULL,
        observacoes TEXT NULL,
        excluido TINYINT(1) NOT NULL DEFAULT 0,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ambi_associado (associado_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'densidades_maximas_animais' => "CREATE TABLE IF NOT EXISTS densidades_maximas_animais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        especie VARCHAR(40) NOT NULL,
        fase VARCHAR(80) NOT NULL,
        densidade VARCHAR(80) NOT NULL COMMENT 'ex.: 5 aves/m², 40kg/m²',
        referencia VARCHAR(60) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_densidade (especie, fase)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tabelas as $tabela => $sql) {
    if (!tabelaExiste($pdo, $tabela)) {
        $pdo->exec($sql);
        echo "  $tabela criada\n";
    } else {
        echo "  $tabela já existe\n";
    }
}

echo "=== Fase 4: seed densidades máximas (Anexo/art. 38-44 Portaria 52/2021) ===\n";
$densidades = [
    ['BOVINOS', 'Corte extensivo', '1 animal/hectare (UA 450kg)', 'art. 42'],
    ['BOVINOS', 'Leite (confinamento/piquete)', '10 m²/animal', 'art. 42.'],
    ['BUFALINOS', 'Corte extensivo', '1,2 hectare/animal', 'art. 42'],
    ['OVINOS', 'Cria/recria, pasto rotacionado', '14 m²/animal', 'art. 42'],
    ['CAPRINOS', 'Cria/recria', '10 m²/animal', 'art. 42'],
    ['SUINOS', 'Ciclo completo, pasto/área externa', '10 m²/animal (mín. 50% exercício)', 'art. 42'],
    ['SUINOS', 'Reprodutores', '15 m²/animal', 'art. 42'],
    ['AVES', 'Corte (piso, galpão)', '5 aves/m²', 'art. 42'],
    ['AVES', 'Corte (externo rotacionado)', '40 kg/m²', 'art. 42'],
    ['AVES', 'Postura (galpão)', '6 aves/m²', 'art. 42'],
    ['AVES', 'Postura (piquete rotacionado)', '10 m²/ave', 'art. 42'],
];
$stmtDens = $pdo->prepare("SELECT COUNT(*) FROM densidades_maximas_animais WHERE especie = ? AND fase = ?");
$stmtDensInsert = $pdo->prepare("INSERT INTO densidades_maximas_animais (especie, fase, densidade, referencia) VALUES (?, ?, ?, ?)");
foreach ($densidades as $d) {
    $stmtDens->execute([$d[0], $d[1]]);
    if ((int) $stmtDens->fetchColumn() === 0) {
        $stmtDensInsert->execute($d);
        echo "  densidade: {$d[0]} / {$d[1]} inserida\n";
    }
}

// Remove FK causadora de erro se tabelas antigas já existirem com itens
echo "\nMigração produção animal concluída.\n";