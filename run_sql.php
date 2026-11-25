<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// 1. Criar mesorregioes_rs
$sql = file_get_contents('sql/create_mesorregioes_rs.sql');
$db->exec($sql);
echo "mesorregioes_rs: OK\n";

// 2. Criar microrregioes_rs
$sql = file_get_contents('sql/create_microrregioes_rs.sql');
$db->exec($sql);
echo "microrregioes_rs: OK\n";

// 3. Adicionar colunas
$sql = file_get_contents('sql/add_regiao_columns.sql');
$db->exec($sql);
echo "add_regiao_columns: OK\n";

echo "Tabelas criadas com sucesso!\n";