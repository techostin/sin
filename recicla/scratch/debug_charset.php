<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// Verificar charset da conexão
$stmt = $db->query("SHOW VARIABLES LIKE 'character_set%'");
$vars = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== CHARACTER SETS ===" . PHP_EOL;
foreach ($vars as $v) {
    echo $v['Variable_name'] . " = " . $v['Value'] . PHP_EOL;
}

// Verificar charset da tabela
$stmt2 = $db->query("SELECT CCSA.character_set_name, CCSA.collation_name
    FROM information_schema.`TABLES` T
    INNER JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA
        ON CCSA.collation_name = T.TABLE_COLLATION
    WHERE T.TABLE_SCHEMA = 'sisagro' AND T.TABLE_NAME = 'plano_manejo'");
$tblCharset = $stmt2->fetch(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== TABELA CHARSET ===" . PHP_EOL;
echo json_encode($tblCharset, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Verificar charset da coluna status especificamente
$stmt3 = $db->query("SELECT CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='sisagro' AND TABLE_NAME='plano_manejo' AND COLUMN_NAME='status'");
$colInfo = $stmt3->fetch(PDO::FETCH_ASSOC);
echo PHP_EOL . "=== COLUNA STATUS CHARSET ===" . PHP_EOL;
echo json_encode($colInfo, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Tentar SET NAMES e ver os valores do ENUM
$db->exec("SET NAMES utf8mb4");
$stmt4 = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='sisagro' AND TABLE_NAME='plano_manejo' AND COLUMN_NAME='status'");
$enumRaw = $stmt4->fetchColumn();
echo PHP_EOL . "=== ENUM RAW (após SET NAMES utf8mb4) ===" . PHP_EOL;
echo $enumRaw . PHP_EOL;

// Extrair os valores do ENUM
preg_match_all("/'([^']+)'/", $enumRaw, $matches);
echo PHP_EOL . "=== VALORES DO ENUM ===" . PHP_EOL;
foreach ($matches[1] as $val) {
    echo " - [" . bin2hex($val) . "] = " . $val . PHP_EOL;
}

// Verificar o que o PHP envia como 'Em Análise'
$emAnalise = 'Em Análise';
echo PHP_EOL . "=== PHP 'Em Análise' hex ===" . PHP_EOL;
echo bin2hex($emAnalise) . PHP_EOL;

// Tentar INSERT direto para testar
echo PHP_EOL . "=== TESTE: atualizar status do plano 1 ===" . PHP_EOL;
$db->exec("SET NAMES utf8mb4");
$stmt5 = $db->prepare("UPDATE plano_manejo SET status = 'Em Análise' WHERE id = 1");
$ok = $stmt5->execute();
echo "Execute ok: " . ($ok ? 'sim' : 'nao') . PHP_EOL;
echo "Rows affected: " . $stmt5->rowCount() . PHP_EOL;

// Busca o valor salvo
$stmt6 = $db->prepare("SELECT status FROM plano_manejo WHERE id = 1");
$stmt6->execute();
echo "Status salvo: [" . $stmt6->fetchColumn() . "]" . PHP_EOL;

// Reverter
$db->prepare("UPDATE plano_manejo SET status = '' WHERE id = 1")->execute();
?>
