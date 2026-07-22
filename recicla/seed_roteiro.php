<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se já existe
    $count = $db->query("SELECT COUNT(*) FROM roteiros_inspecao")->fetchColumn();
    if ($count == 0) {
        $checklist = [
            ['id' => 1, 'pergunta' => 'A unidade de produção respeita o período de conversão?'],
            ['id' => 2, 'pergunta' => 'Existe barreira física contra contaminação externa?'],
            ['id' => 3, 'pergunta' => 'As sementes utilizadas são orgânicas ou autorizadas?'],
            ['id' => 4, 'pergunta' => 'O controle de pragas segue as normas da produção orgânica?'],
            ['id' => 5, 'pergunta' => 'O armazenamento de insumos é feito separadamente?']
        ];
        
        $sql = "INSERT INTO roteiros_inspecao (nome, tipo, descricao, checklist, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'Checklist de Inspeção Orgânica - Vegetal',
            'Visita Regular',
            'Roteiro padrão para verificação de conformidade de produção vegetal orgânica.',
            json_encode($checklist)
        ]);
        echo "Template padrão criado com sucesso!\n";
    } else {
        echo "Templates já existem.\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
