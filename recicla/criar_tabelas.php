<?php
// Script para criar as tabelas faltantes no banco de dados
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "<h2>Criando tabelas faltantes...</h2>";
    
    // Criar tabela produtos
    $sql_produtos = "CREATE TABLE IF NOT EXISTS `produtos` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome` varchar(255) NOT NULL,
        `tipo` varchar(100) DEFAULT NULL,
        `unidade_medida` varchar(50) DEFAULT NULL,
        `quantidade_produzida` decimal(10,2) DEFAULT NULL,
        `data_colheita` date DEFAULT NULL,
        `lavoura_id` int(11) DEFAULT NULL,
        `observacoes` text DEFAULT NULL,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `lavoura_id` (`lavoura_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC";
    
    $db->exec($sql_produtos);
    echo "<p>✅ Tabela 'produtos' criada com sucesso!</p>";
    
    // Criar tabela lotes_producao
    $sql_lotes = "CREATE TABLE IF NOT EXISTS `lotes_producao` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nome_lote` varchar(255) NOT NULL,
        `produto_id` int(11) DEFAULT NULL,
        `data_producao` date NOT NULL,
        `quantidade` decimal(10,2) NOT NULL,
        `unidade_medida` varchar(50) NOT NULL,
        `observacoes` text DEFAULT NULL,
        `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `produto_id` (`produto_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC";
    
    $db->exec($sql_lotes);
    echo "<p>✅ Tabela 'lotes_producao' criada com sucesso!</p>";
    
    // Verificar se já existem dados
    $stmt = $db->query("SELECT COUNT(*) as total FROM produtos");
    $total_produtos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($total_produtos == 0) {
        echo "<p>Inserindo dados de exemplo...</p>";
        
        // Inserir dados de exemplo para produtos
        $produtos_exemplo = [
            ['Milho Verde', 'Grão', 'kg', 1500.00, '2024-03-15', 'Milho de alta qualidade'],
            ['Feijão Carioca', 'Grão', 'kg', 800.00, '2024-02-20', 'Feijão orgânico'],
            ['Tomate', 'Hortaliça', 'kg', 2000.00, '2024-01-10', 'Tomate para mesa'],
            ['Alface', 'Hortaliça', 'un', 500.00, '2024-01-05', 'Alface crespa']
        ];
        
        $stmt = $db->prepare("INSERT INTO produtos (nome, tipo, unidade_medida, quantidade_produzida, data_colheita, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($produtos_exemplo as $produto) {
            $stmt->execute($produto);
        }
        
        echo "<p>✅ Dados de exemplo inseridos na tabela 'produtos'!</p>";
        
        // Inserir dados de exemplo para lotes
        $lotes_exemplo = [
            ['LOTE-001-2024', 1, '2024-03-15', 1500.00, 'kg', 'Lote de milho da safra 2024'],
            ['LOTE-002-2024', 2, '2024-02-20', 800.00, 'kg', 'Lote de feijão orgânico'],
            ['LOTE-003-2024', 3, '2024-01-10', 2000.00, 'kg', 'Lote de tomate para mesa'],
            ['LOTE-004-2024', 4, '2024-01-05', 500.00, 'un', 'Lote de alface crespa']
        ];
        
        $stmt = $db->prepare("INSERT INTO lotes_producao (nome_lote, produto_id, data_producao, quantidade, unidade_medida, observacoes) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($lotes_exemplo as $lote) {
            $stmt->execute($lote);
        }
        
        echo "<p>✅ Dados de exemplo inseridos na tabela 'lotes_producao'!</p>";
    } else {
        echo "<p>ℹ️ Dados já existem nas tabelas.</p>";
    }
    
    // Verificar se as tabelas foram criadas
    $tabelas = ['produtos', 'lotes_producao'];
    foreach ($tabelas as $tabela) {
        $stmt = $db->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Tabela '$tabela' existe no banco de dados.</p>";
        } else {
            echo "<p>❌ Erro: Tabela '$tabela' não foi criada.</p>";
        }
    }
    
    echo "<h3>🎉 Processo concluído com sucesso!</h3>";
    echo "<p>Agora você pode acessar o relatório de produção:</p>";
    echo "<p><a href='caderno-campo/relatorios/producao' target='_blank'>Relatório de Produção</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Erro durante o processo:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 