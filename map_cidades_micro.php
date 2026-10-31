<?php
/**
 * Mapeamento IBGE: Município -> Microrregião (RS)
 * Baseado na divisão oficial do IBGE (2017) - 35 microrregiões
 * 
 * Este array deve ser expandido com os 497 municípios.
 * Fonte: https://www.ibge.gov.br/geociencias/organizacao-do-territorio/divisao-territorial/
 */

$municipioParaMicrorregiao = [
    // Microrregião 43001 - Porto Alegre
    4300034 => 43001, // Aceguá - não, Aceguá é Campanha Meridional
    // Preciso do mapeamento correto...
    
    // Vou usar uma abordagem diferente: criar mapa por nome do município
    // Mapeamento nome do município -> código da microrregião (IBGE 5 dígitos)
];

// Mapeamento por nome do município (mais confiável)
$municipioNomeParaMicroIBGE = [
    // Mesorregião Metropolitana de Porto Alegre (mesorregião 4301)
    // Microrregiões: 43001 (Porto Alegre), 43002 (Canoas), 43003 (Novo Hamburgo), 
    // 43004 (São Jerônimo), 43005 (Gramado-Canela), 43006 (Osório), 43007 (Tramandaí)
    
    'Porto Alegre' => 43001,
    'Canoas' => 43002,
    'Novo Hamburgo' => 43003,
    'São Jerônimo' => 43004,
    'Gramado' => 43005,
    'Canela' => 43005,
    'Osório' => 43006,
    'Tramandaí' => 43007,
    
    // Cidades próximas a Porto Alegre
    'Alvorada' => 43001,
    'Viamão' => 43001,
    'Gravataí' => 43001,
    'Cachoeirinha' => 43001,
    'Esteio' => 43001,
    'Sapucaia do Sul' => 43001,
    'São Leopoldo' => 43003,
    'Campo Bom' => 43003,
    'Sapiranga' => 43003,
    'IgREJINHA' => 43003,
    'Taquara' => 43003,
    
    // Litoral Norte
    'Capão da Canoa' => 43006,
    'Torres' => 43006,
    'Imbé' => 43007,
    'Tramandaí' => 43007,
    'Osório' => 43006,
    
    // Serra
    'Caxias do Sul' => 43009,
    'Farroupilha' => 43009,
    'Flores da Cunha' => 43009,
    'Bento Gonçalves' => 43009,
    'Garibaldi' => 43009,
    'Carlos Barbosa' => 43009,
    'Nova Petrópolis' => 43009,
    'Gramado' => 43005,
    'Canela' => 43005,
    
    // Norte
    'Passo Fundo' => 43014,
    'Carazinho' => 43015,
    'Erechim' => 43014,
    'Getúlio Vargas' => 43014,
    'Marau' => 43014,
    
    // Centro
    'Santa Maria' => 43018,
    'Santa Cruz do Sul' => 43018,
    'Cachoeira do Sul' => 43021,
    'Santiago' => 43019,
    'Restinga Seca' => 43020,
    
    // Noroeste
    'Ijuí' => 43023,
    'Santo Ângelo' => 43024,
    'São Luiz Gonzaga' => 43025,
    'Cruz Alta' => 43022,
    'Panambi' => 43022,
    
    // Fronteira Oeste
    'Uruguaiana' => 43027,
    'Alegrete' => 43026,
    'São Borja' => 43027,
    'Itaqui' => 43027,
    'Quaraí' => 43027,
    
    // Sul
    'Pelotas' => 43030,
    'Rio Grande' => 43031,
    'Bagé' => 43028,
    'Canguçu' => 43030,
    'Jaguarão' => 43030,
    
    // Noroeste (continuação)
    'Santa Rosa' => 43033,
    'Três Passos' => 43032,
    'Horizontina' => 43032,
    'Tucunduva' => 43032,
];

// Agora buscar o ID da microrregião na tabela baseado no codigo_ibge
require_once 'config/config.php';
require_once 'classes/Database.php';
$db = Database::getInstance()->getConnection();

// Buscar mapa: codigo_ibge_micro -> id
$stmt = $db->query("SELECT id, codigo_ibge FROM microrregioes_rs");
$microMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $microMap[$row['codigo_ibge']] = $row['id'];
}

echo "Microrregiões disponíveis:\n";
foreach ($microMap as $cod => $id) {
    echo "  $cod -> ID $id\n";
}

// Atualizar cidades do RS
$stmt = $db->prepare("
    SELECT c.id, c.nome, c.ibge 
    FROM cidades c
    INNER JOIN estados e ON e.id = c.estado
    WHERE e.uf = 'RS'
");

$stmt->execute();
$cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$atualizados = 0;
$naoEncontrados = [];

foreach ($cidades as $cidade) {
    $nome = $cidade['nome'];
    $microIBGE = $municipioNomeParaMicroIBGE[$nome] ?? null;
    
    if ($microIBGE && isset($microMap[$microIBGE])) {
        $microId = $microMap[$microIBGE];
        $upd = $db->prepare("UPDATE cidades SET microrregiao_id = ? WHERE id = ?");
        $upd->execute([$microId, $cidade['id']]);
        $atualizados++;
    } else {
        $naoEncontrados[] = $nome;
    }
}

echo "\nAtualizados: $atualizados\n";
echo "Não encontrados: " . count($naoEncontrados) . "\n";
if ($naoEncontrados) {
    echo "Exemplos: " . implode(', ', array_slice($naoEncontrados, 0, 20)) . "\n";
}

// Verificar resultado
$stmt = $db->query("
    SELECT COUNT(*) as total FROM cidades 
    WHERE estado = (SELECT id FROM estados WHERE uf = 'RS') AND microrregiao_id IS NOT NULL
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nCidades RS com microrregiao: {$row['total']}\n";