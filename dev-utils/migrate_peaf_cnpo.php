<?php
/**
 * Migração: tabelas de referência externa (PEAF e CNPO)
 *
 * Cria e popula:
 *   - `agroindustria_existentes`  ← storage/externos/peaf_julho2026_organicos.xlsx
 *     (agroindústrias orgânicas/em transição inclusas no PEAF, SDR-RS)
 *   - `cnpo`                      ← storage/externos/cnpo_mapa_2026.xlsx
 *     (Cadastro Nacional de Produtores Orgânicos, MAPA)
 *
 * Idempotente: recria as tabelas se o parâmetro --drop==1 for passado.
 * Rode: php dev-utils/migrate_peaf_cnpo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

$db = Database::getInstance()->getConnection();
$drop = in_array('--drop=1', $argv, true);

function tabelaExiste(PDO $db, string $nome): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$nome]);
    return (int)$stmt->fetchColumn() > 0;
}

function normaliza($v): ?string {
    if ($v === null) return null;
    if (is_int($v) || is_float($v)) return (string)$v;
    return $v;
}

function lerXlsx(string $caminho, int $linhaCabecalho): array {
    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $spreadsheet = $reader->load($caminho);
    $sheet = $spreadsheet->getActiveSheet();
    $linhas = [];
    foreach ($sheet->toArray(null, true, false, false) as $idx => $linha) {
        if ($idx < $linhaCabecalho) continue;
        $linhas[] = $linha;
    }
    return $linhas;
}

echo "=== agroindustria_existentes ===\n";
if ($drop && tabelaExiste($db, 'agroindustria_existentes')) {
    $db->exec("DROP TABLE agroindustria_existentes");
    echo "  tabela antiga removida.\n";
}
if (!tabelaExiste($db, 'agroindustria_existentes')) {
    $db->exec("CREATE TABLE agroindustria_existentes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        numero INT NULL,
        agroindustria VARCHAR(200) NULL,
        municipio VARCHAR(100) NULL,
        processamento VARCHAR(255) NULL,
        data_inclusao DATE NULL,
        peaf VARCHAR(30) NULL,
        licenciamento_sanitario VARCHAR(100) NULL,
        ultima_atualizacao DATE NULL,
        organicos VARCHAR(60) NULL,
        INDEX idx_ae_municipio (municipio),
        INDEX idx_ae_organicos (organicos)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agroindústrias orgânicas/em transição inclusas no PEAF (SDR-RS)'");
    echo "  tabela criada.\n";
}

$arquivoPeaf = __DIR__ . '/../storage/externos/peaf_julho2026_organicos.xlsx';
if (!is_file($arquivoPeaf)) {
    fwrite(STDERR, "ERRO: planilha do PEAF nao encontrada em {$arquivoPeaf}\n");
    fwrite(STDERR, "Coloque o arquivo em storage/externos/ (ou rode dev-utils/atualizar_peaf.php antes).\n");
    exit(1);
}
$peaf = lerXlsx($arquivoPeaf, 1);
$nPeaf = 0;
$stmt = $db->prepare(
    "INSERT INTO agroindustria_existentes
        (numero, agroindustria, municipio, processamento, data_inclusao, peaf, licenciamento_sanitario, ultima_atualizacao, organicos)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$db->beginTransaction();
try {
    foreach ($peaf as $linha) {
        $limparData = function ($v) {
            if (empty($v)) return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$v, $m)) return $m[0];
            $t = strtotime((string)$v);
            return $t ? date('Y-m-d', $t) : null;
        };
        $stmt->execute([
            normaliza($linha[0]) !== null ? (int)normaliza($linha[0]) : null,
            normaliza($linha[1]),
            normaliza($linha[2]),
            normaliza($linha[3]),
            $limparData($linha[4]),
            normaliza($linha[5]),
            normaliza($linha[6]),
            $limparData($linha[7]),
            normaliza($linha[8]),
        ]);
        $nPeaf++;
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
echo "  registros inseridos: {$nPeaf}\n";

echo "\n=== cnpo ===\n";
if ($drop && tabelaExiste($db, 'cnpo')) {
    $db->exec("DROP TABLE cnpo");
    echo "  tabela antiga removida.\n";
}
if (!tabelaExiste($db, 'cnpo')) {
    $db->exec("CREATE TABLE cnpo (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tipo_entidade VARCHAR(40) NULL,
        entidade VARCHAR(255) NULL,
        pais VARCHAR(60) NULL,
        uf VARCHAR(10) NULL,
        cidade VARCHAR(120) NULL,
        situacao VARCHAR(20) NULL,
        cnpf_cnpj_nif VARCHAR(40) NULL,
        nome_produtor VARCHAR(255) NULL,
        escopo TEXT NULL,
        atividades TEXT NULL,
        contato VARCHAR(255) NULL,
        INDEX idx_cnpo_uf (uf),
        INDEX idx_cnpo_cidade (cidade),
        INDEX idx_cnpo_nome (nome_produtor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro Nacional de Produtores Orgânicos (MAPA)'");
    echo "  tabela criada.\n";
}

$arquivoCnpo = __DIR__ . '/../storage/externos/cnpo_mapa_2026.xlsx';
if (!is_file($arquivoCnpo)) {
    fwrite(STDERR, "ERRO: planilha do CNPO nao encontrada em {$arquivoCnpo}\n");
    fwrite(STDERR, "Coloque o arquivo em storage/externos/ (ou rode dev-utils/atualizar_cnpo.php antes).\n");
    exit(1);
}
$cnpo = lerXlsx($arquivoCnpo, 3);
$nCnpo = 0;
$stmtCnpo = $db->prepare(
    "INSERT INTO cnpo
        (tipo_entidade, entidade, pais, uf, cidade, situacao, cnpf_cnpj_nif, nome_produtor, escopo, atividades, contato)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$db->beginTransaction();
try {
    foreach ($cnpo as $linha) {
        $stmtCnpo->execute([
            normaliza($linha[0]),
            normaliza($linha[1]),
            normaliza($linha[2]),
            normaliza($linha[3]),
            normaliza($linha[4]),
            normaliza($linha[5]),
            normaliza($linha[6]),
            normaliza($linha[7]),
            normaliza($linha[8]),
            normaliza($linha[9]),
            normaliza($linha[10]),
        ]);
        $nCnpo++;
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
echo "  registros inseridos: {$nCnpo}\n";

echo "\nMigração concluída!\n";