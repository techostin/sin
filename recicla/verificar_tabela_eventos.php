<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

// Ativar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar se a tabela eventos existe
    $stmt = $db->query("SHOW TABLES LIKE 'eventos'");
    $tabelaExiste = $stmt->rowCount() > 0;
    
    if (!$tabelaExiste) {
        die("<h2>A tabela 'eventos' não existe no banco de dados.</h2>");
    }
    
    // Obter estrutura da tabela
    $stmt = $db->query("DESCRIBE eventos");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar colunas obrigatórias
    $colunasObrigatorias = ['id', 'nome', 'descricao', 'data_evento', 'status'];
    $colunasFaltando = [];
    
    foreach ($colunasObrigatorias as $coluna) {
        $encontrada = false;
        foreach ($colunas as $col) {
            if ($col['Field'] === $coluna) {
                $encontrada = true;
                break;
            }
        }
        if (!$encontrada) {
            $colunasFaltando[] = $coluna;
        }
    }
    
    // Verificar se há eventos na tabela
    $stmt = $db->query("SELECT COUNT(*) as total FROM eventos");
    $totalEventos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Exibir resultados
    echo "<h2>Verificação da Tabela de Eventos</h2>";
    echo "<p><strong>Tabela 'eventos' existe:</strong> Sim</p>";
    
    echo "<h3>Estrutura da Tabela:</h3>";
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
    foreach ($colunas as $coluna) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($coluna['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($coluna['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (!empty($colunasFaltando)) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>Colunas obrigatórias faltando:</h4>";
        echo "<ul>";
        foreach ($colunasFaltando as $coluna) {
            echo "<li>$coluna</li>";
        }
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>Todas as colunas obrigatórias estão presentes.</div>";
    }
    
    echo "<h3>Total de Eventos na Tabela:</h3>";
    echo "<p>$totalEventos eventos encontrados.</p>";
    
    // Mostrar exemplos de eventos
    if ($totalEventos > 0) {
        $stmt = $db->query("SELECT * FROM eventos ORDER BY data_evento DESC LIMIT 5");
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Últimos Eventos:</h3>";
        echo "<table class='table table-bordered'>";
        echo "<tr>";
        foreach (array_keys($eventos[0] ?? []) as $coluna) {
            echo "<th>" . htmlspecialchars($coluna) . "</th>";
        }
        echo "</tr>";
        
        foreach ($eventos as $evento) {
            echo "<tr>";
            foreach ($evento as $valor) {
                echo "<td>" . htmlspecialchars($valor) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>Erro ao acessar o banco de dados:</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Linha:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
    echo "</div>";
}
?>

<style>
    body { padding: 20px; font-family: Arial, sans-serif; }
    h2 { color: #333; margin-top: 30px; }
    h3 { color: #555; margin-top: 20px; }
    .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
    table { width: 100%; margin: 15px 0; border-collapse: collapse; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    th { background-color: #f5f5f5; }
</style>
