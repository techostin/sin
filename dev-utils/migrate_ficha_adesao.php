<?php
/**
 * Executa a migração das tabelas da Ficha de Adesão (ficha_adesao / ficha_adesao_produtos).
 * Idempotente: usa CREATE TABLE IF NOT EXISTS.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$pdo = Database::getInstance()->getConnection();
$sql = file_get_contents(__DIR__ . '/../sql/create_ficha_adesao.sql');
if ($sql === false) {
    fwrite(STDERR, "Nao foi possivel ler sql/create_ficha_adesao.sql
");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "MIGRACAO APLICADA COM SUCESSO
";
    foreach (['ficha_adesao', 'ficha_adesao_produtos'] as $t) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$t]);
        $existe = $stmt->fetchColumn();
        echo $t . ': ' . ($existe ? 'OK' : 'FALHOU') . "
";
    }
    $st = $pdo->query('SHOW COLUMNS FROM ficha_adesao');
    echo 'Colunas ficha_adesao: ' . $st->rowCount() . "
";
} catch (PDOException $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "
");
    exit(1);
}
