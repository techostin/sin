<?php
/*
 * Backfill da tabela fornecedor_unidades a partir dos dados EXISTENTES.
 *
 * Regra (Abordagem B — escopo por unidade):
 * Para cada fornecedor usado em insumos (insumos.fornecedor_id = X), pega os
 * associados que registraram esses insumos (insumos.associado_id) e vincula o
 * fornecedor às UNIDADES onde esses associados são DONOS:
 *   - propriedades      (propriedades.associado_id)
 *   - agroindustrias    (agroindustrias.associado_id, excluido=0)
 *   - ambiente criaçao  (ambiente_criacao_animal.associado_id, excluido=0)
 *
 * Idempotente: usa INSERT ... ON DUPLICATE KEY UPDATE.
 */
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "== Backfill fornecedor_unidades (baseado em insumos) ==\n";

$ins = $db->prepare("
    INSERT INTO fornecedor_unidades (fornecedor_id, unidade_tipo, unidade_id, associado_id)
    VALUES (:fid, :tipo, :uid, :assoc)
    ON DUPLICATE KEY UPDATE excluido = 0, associado_id = VALUES(associado_id)
");

$total = 0;
$inserted = [];

// Fornecedores que possuem insumos associados
$fornecedores = $db->query("
    SELECT DISTINCT i.fornecedor_id, f.nome
    FROM insumos i
    JOIN fornecedores f ON i.fornecedor_id = f.id
    WHERE i.excluido = 0 AND i.fornecedor_id IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($fornecedores as $forn) {
    $fid = (int)$forn['fornecedor_id'];
    // Associados que usam este fornecedor em insumos
    $assocs = $db->prepare("SELECT DISTINCT associado_id FROM insumos WHERE fornecedor_id = :fid AND excluido = 0 AND associado_id IS NOT NULL");
    $assocs->bindParam(':fid', $fid, PDO::PARAM_INT);
    $assocs->execute();
    $assoc_ids = array_map('intval', $assocs->fetchAll(PDO::FETCH_COLUMN));

    foreach ($assoc_ids as $assoc) {
        // 1) Propriedades em que o associado é DONO
        $q = $db->prepare("SELECT id FROM propriedades WHERE associado_id = :assoc");
        $q->bindParam(':assoc', $assoc, PDO::PARAM_INT);
        $q->execute();
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $ins->bindValue(':fid', $fid, PDO::PARAM_INT);
            $ins->bindValue(':tipo', 'propriedade', PDO::PARAM_STR);
            $ins->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
            $ins->bindValue(':assoc', $assoc, PDO::PARAM_INT);
            $ins->execute();
            $inserted[] = "forn $fid -> propriedade $uid (assoc $assoc)";
            $total++;
        }

        // 2) Agroindústrias em que o associado é DONO
        $q = $db->prepare("SELECT id FROM agroindustrias WHERE associado_id = :assoc AND excluido = 0");
        $q->bindParam(':assoc', $assoc, PDO::PARAM_INT);
        $q->execute();
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $ins->bindValue(':fid', $fid, PDO::PARAM_INT);
            $ins->bindValue(':tipo', 'agroindustria', PDO::PARAM_STR);
            $ins->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
            $ins->bindValue(':assoc', $assoc, PDO::PARAM_INT);
            $ins->execute();
            $inserted[] = "forn $fid -> agroindustria $uid (assoc $assoc)";
            $total++;
        }

        // 3) Ambientes de criação animal em que o associado é DONO
        $q = $db->prepare("SELECT id FROM ambiente_criacao_animal WHERE associado_id = :assoc AND excluido = 0");
        $q->bindParam(':assoc', $assoc, PDO::PARAM_INT);
        $q->execute();
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $ins->bindValue(':fid', $fid, PDO::PARAM_INT);
            $ins->bindValue(':tipo', 'animal', PDO::PARAM_STR);
            $ins->bindValue(':uid', (int)$uid, PDO::PARAM_INT);
            $ins->bindValue(':assoc', $assoc, PDO::PARAM_INT);
            $ins->execute();
            $inserted[] = "forn $fid -> animal $uid (assoc $assoc)";
            $total++;
        }
    }
    echo "  {$forn['nome']} (id $fid): ok\n";
}

echo "\n$total vínculos criados/atualizados:\n";
foreach ($inserted as $i) echo "  $i\n";

echo "\n== FIM ==\n";