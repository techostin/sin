<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Buscar mapa: cidade.id -> microrregiao_id, mesorregiao_id
$stmt = $db->query("
    SELECT c.id as cidade_id, c.microrregiao_id, mi.mesorregiao_id
    FROM cidades c
    JOIN microrregioes_rs mi ON c.microrregiao_id = mi.id
    JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS' AND c.microrregiao_id IS NOT NULL
");
$cidadeMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cidadeMap[$row['cidade_id']] = [
        'microrregiao_id' => $row['microrregiao_id'],
        'mesorregiao_id' => $row['mesorregiao_id']
    ];
}

echo "Cidades RS no mapa: " . count($cidadeMap) . "\n";

// Definir clusters por mesorregião
$clusterPorMeso = [
    1 => 'METROPOLE',           // Metropolitana de Porto Alegre
    2 => 'SERRA_NORDESTE',      // Nordeste Rio-grandense
    3 => 'CENTRO_OESTE',        // Centro Ocidental Rio-grandense
    4 => 'NOROESTE_CENTRAL',    // Centro Oriental Rio-grandense
    5 => 'FRONTEIRA_OESTE',     // Sudoeste Rio-grandense
    6 => 'SUL_LITORAL',         // Sudeste Rio-grandense
    7 => 'NOROESTE',            // Noroeste Rio-grandense
];

// Atualizar associados do RS (estado = 23)
$stmt = $db->query("
    SELECT id, cidade, estado 
    FROM associados 
    WHERE estado = 23 
    AND (microrregiao_id IS NULL OR cluster_comunicacao IS NULL)
");
$associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Associados RS para atualizar: " . count($associados) . "\n\n";

$atualizados = 0;
$naoEncontrados = [];

foreach ($associados as $assoc) {
    $cidadeId = (int)$assoc['cidade'];
    $info = $cidadeMap[$cidadeId] ?? null;
    
    if ($info) {
        $cluster = $clusterPorMeso[$info['mesorregiao_id']] ?? 'OUTRO';
        
        $upd = $db->prepare("
            UPDATE associados 
            SET microrregiao_id = ?, cluster_comunicacao = ? 
            WHERE id = ?
        ");
        $upd->execute([$info['microrregiao_id'], $cluster, $assoc['id']]);
        $atualizados++;
    } else {
        $naoEncontrados[] = $assoc['cidade'];
    }
}

echo "Atualizados: $atualizados\n";
echo "Não encontrados: " . count($naoEncontrados) . "\n";

if ($naoEncontrados) {
    $unicos = array_unique($naoEncontrados);
    echo "Cidades únicas não mapeadas: " . count($unicos) . "\n";
    foreach (array_slice($unicos, 0, 20) as $c) {
        echo "  - Cidade ID: $c\n";
    }
}

// Verificar resultado
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        COUNT(microrregiao_id) as com_micro,
        COUNT(cluster_comunicacao) as com_cluster
    FROM associados 
    WHERE estado = 23
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nAssociados RS:\n";
echo "  Total: {$row['total']}\n";
echo "  Com microrregiao_id: {$row['com_micro']}\n";
echo "  Com cluster_comunicacao: {$row['com_cluster']}\n";

// Distribuição por cluster
$stmt = $db->query("
    SELECT cluster_comunicacao, COUNT(*) as qtd
    FROM associados 
    WHERE estado = 23 AND cluster_comunicacao IS NOT NULL
    GROUP BY cluster_comunicacao
    ORDER BY qtd DESC
");
echo "\nDistribuição por cluster:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['cluster_comunicacao']}: {$row['qtd']}\n";
}

// Distribuição por microrregião
$stmt = $db->query("
    SELECT mi.nome as microrregiao, mr.nome as mesorregiao, COUNT(a.id) as qtd
    FROM associados a
    JOIN microrregioes_rs mi ON a.microrregiao_id = mi.id
    JOIN mesorregioes_rs mr ON mi.mesorregiao_id = mr.id
    WHERE a.estado = 23 AND a.microrregiao_id IS NOT NULL
    GROUP BY mi.id, mi.nome, mr.nome
    ORDER BY mr.nome, mi.nome
");
echo "\nDistribuição por microrregião:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['mesorregiao']} / {$row['microrregiao']}: {$row['qtd']}\n";
}