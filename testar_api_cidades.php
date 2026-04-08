<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "=== TESTANDO API DE CIDADES ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Teste 1: Verificar se a tabela cidades existe
    echo "1. Verificando tabela cidades...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'cidades'");
    $tabela_existe = $stmt->rowCount() > 0;
    echo "Tabela cidades existe: " . ($tabela_existe ? "✅ SIM" : "❌ NÃO") . "\n\n";
    
    if (!$tabela_existe) {
        echo "❌ ERRO: Tabela cidades não existe!\n";
        exit;
    }
    
    // Teste 2: Verificar se a tabela estados existe
    echo "2. Verificando tabela estados...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'estados'");
    $estados_existe = $stmt->rowCount() > 0;
    echo "Tabela estados existe: " . ($estados_existe ? "✅ SIM" : "❌ NÃO") . "\n\n";
    
    if (!$estados_existe) {
        echo "❌ ERRO: Tabela estados não existe!\n";
        exit;
    }
    
    // Teste 3: Contar cidades
    echo "3. Contando cidades...\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM cidades");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total de cidades: " . $result['total'] . "\n\n";
    
    // Teste 4: Contar estados
    echo "4. Contando estados...\n";
    $stmt = $db->query("SELECT COUNT(*) as total FROM estados");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total de estados: " . $result['total'] . "\n\n";
    
    // Teste 5: Verificar estrutura da tabela cidades
    echo "5. Estrutura da tabela cidades:\n";
    $stmt = $db->query("DESCRIBE cidades");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($colunas as $coluna) {
        echo "  - " . $coluna['Field'] . " (" . $coluna['Type'] . ")\n";
    }
    echo "\n";
    
    // Teste 6: Verificar estrutura da tabela estados
    echo "6. Estrutura da tabela estados:\n";
    $stmt = $db->query("DESCRIBE estados");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($colunas as $coluna) {
        echo "  - " . $coluna['Field'] . " (" . $coluna['Type'] . ")\n";
    }
    echo "\n";
    
    // Teste 7: Testar a query da API
    echo "7. Testando query da API...\n";
    $stmt = $db->prepare("
        SELECT c.id, c.nome, c.estado as estado_id, e.sigla as estado_sigla 
        FROM cidades c 
        INNER JOIN estados e ON c.estado = e.id 
        ORDER BY c.nome ASC 
        LIMIT 5
    ");
    $stmt->execute();
    $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Primeiras 5 cidades com estados:\n";
    foreach ($cidades as $cidade) {
        echo "  - " . $cidade['nome'] . " (" . $cidade['estado_sigla'] . ")\n";
    }
    echo "\n";
    
    // Teste 8: Simular a API
    echo "8. Simulando resposta da API...\n";
    $json = json_encode($cidades);
    echo "JSON gerado: " . substr($json, 0, 200) . "...\n";
    echo "Tamanho do JSON: " . strlen($json) . " bytes\n\n";
    
    echo "✅ Todos os testes passaram!\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ ERRO GERAL: " . $e->getMessage() . "\n";
}
?> 