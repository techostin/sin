<?php
require 'classes/Database.php';
$db = Database::getInstance()->getConnection();
$r = $db->query("SHOW CREATE TABLE fornecedores")->fetch(PDO::FETCH_ASSOC);
echo $r['Create Table'] . "\n\n";
echo "== fornecedores existentes ==\n";
$q = $db->query("SELECT id, nome, produto_fornecido FROM fornecedores");
while ($row = $q->fetch(PDO::FETCH_ASSOC)) echo "  #{$row['id']} '{$row['nome']}' produto='{$row['produto_fornecido']}'\n";
if ($q->rowCount() == 0) echo "  (nenhum)\n";
echo "\n== insumos (amostra) ==\n";
$q2 = $db->query("SELECT i.id, i.nome, i.tipo_insumo, i.associado_id, i.fornecedor_id, a.tipo_usuario FROM insumos i LEFT JOIN associados a ON i.associado_id = a.id LIMIT 15");
while ($row = $q2->fetch(PDO::FETCH_ASSOC)) echo "  insumo#{$row['id']} '{$row['nome']}' tipo='{$row['tipo_insumo']}' assoc={$row['associado_id']} ({$row['tipo_usuario']}) forn={$row['fornecedor_id']}\n";
echo "\n== associados com tipo_usuario ==\n";
$q3 = $db->query("SELECT id, nome, tipo_usuario FROM associados WHERE status='ativo'");
while ($row = $q3->fetch(PDO::FETCH_ASSOC)) echo "  #{$row['id']} '{$row['nome']}' tipo='{$row['tipo_usuario']}'\n";
