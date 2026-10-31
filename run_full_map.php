<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$municipioNomeParaMicroIBGE = require_once 'municipio_micro_map.php';

$db = Database::getInstance()->getConnection();

// Buscar mapa: codigo_ibge_micro -> id
$stmt = $db->query("SELECT id, codigo_ibge FROM microrregioes_rs");
$microMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $microMap[$row['codigo_ibge']] = $row['id'];
}

echo "Microrregiões disponíveis: " . count($microMap) . "\n";

// Buscar todas as cidades do RS
$stmt = $db->query("
    SELECT c.id, c.nome, c.ibge 
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS'
    ORDER BY c.nome
");
$cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total cidades RS no banco: " . count($cidades) . "\n\n";

$atualizados = 0;
$naoEncontrados = [];
$jaTinham = 0;

foreach ($cidades as $cidade) {
    $nome = $cidade['nome'];
    $microIBGE = $municipioNomeParaMicroIBGE[$nome] ?? null;
    
    if ($microIBGE && isset($microMap[$microIBGE])) {
        $microId = $microMap[$microIBGE];
        
        // Verificar se já tem
        $check = $db->prepare("SELECT microrregiao_id FROM cidades WHERE id = ?");
        $check->execute([$cidade['id']]);
        $atual = $check->fetchColumn();
        
        if ($atual) {
            $jaTinham++;
            if ($atual != $microId) {
                echo "  ATENÇÃO: {$nome} já tem microrregiao_id={$atual}, novo seria {$microId}\n";
            }
        } else {
            $upd = $db->prepare("UPDATE cidades SET microrregiao_id = ? WHERE id = ?");
            $upd->execute([$microId, $cidade['id']]);
            $atualizados++;
        }
    } else {
        $naoEncontrados[] = $nome;
    }
}

echo "\n--- RESULTADO ---\n";
echo "Já tinham microrregiao: $jaTinham\n";
echo "Atualizados agora: $atualizados\n";
echo "Não encontrados no mapa: " . count($naoEncontrados) . "\n";

if ($naoEncontrados) {
    echo "\nNão encontrados (primeiros 30):\n";
    foreach (array_slice($naoEncontrados, 0, 30) as $n) {
        echo "  - $n\n";
    }
}

// Verificar resultado final
$stmt = $db->query("
    SELECT COUNT(*) as total FROM cidades 
    WHERE estado = (SELECT id FROM estados WHERE uf = 'RS') AND microrregiao_id IS NOT NULL
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nCidades RS com microrregiao: {$row['total']} / 497\n";

// Verificar distribuição
$stmt = $db->query("
    SELECT mr.nome as mesorregiao, mi.nome as microrregiao, COUNT(c.id) as qtd
    FROM cidades c
    JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
    JOIN mesorregioes_rs mr ON mr.id = mi.mesorregiao_id
    WHERE c.estado = (SELECT id FROM estados WHERE uf = 'RS')
    GROUP BY mr.id, mi.id
    ORDER BY mr.nome, mi.nome
");
echo "\nDistribuição por microrregião:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['mesorregiao']} / {$row['microrregiao']}: {$row['qtd']} cidades\n";
}