<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Lembrete.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Eventos</h1>";

echo "<h2>1. Sessão Atual</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NÃO DEFINIDO') . "\n";
echo "Mostrar Modal: " . (isset($_SESSION['mostrar_modal_eventos']) ? ($_SESSION['mostrar_modal_eventos'] ? 'SIM' : 'NÃO') : 'NÃO DEFINIDO') . "\n";
echo "</pre>";

echo "<h2>2. Teste da Classe Lembrete</h2>";
try {
    $lembrete = new Lembrete();
    
    echo "<h3>Eventos de Hoje:</h3>";
    $hoje = $lembrete->getEventosHoje();
    echo "Total: " . count($hoje) . "<br>";
    if (count($hoje) > 0) {
        echo "<pre>";
        print_r($hoje);
        echo "</pre>";
    } else {
        echo "<p style='color:orange'>Nenhum evento para hoje</p>";
    }
    
    echo "<h3>Eventos Próximos (30 dias):</h3>";
    $proximos = $lembrete->getEventosProximos(30);
    echo "Total: " . count($proximos) . "<br>";
    if (count($proximos) > 0) {
        echo "<pre>";
        print_r($proximos);
        echo "</pre>";
    } else {
        echo "<p style='color:orange'>Nenhum evento para os próximos 30 dias</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Erro: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Teste Direto do Banco</h2>";
try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT id, nome, data_evento, local, status FROM eventos 
            WHERE status = 'Agendado' 
            AND DATE(data_evento) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY data_evento ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de eventos no banco: " . count($eventos) . "<br>";
    if (count($eventos) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Data</th><th>Local</th><th>Status</th></tr>";
        foreach ($eventos as $evt) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($evt['id']) . "</td>";
            echo "<td>" . htmlspecialchars($evt['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($evt['data_evento']) . "</td>";
            echo "<td>" . htmlspecialchars($evt['local']) . "</td>";
            echo "<td>" . htmlspecialchars($evt['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>Nenhum evento encontrado no banco!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Erro no banco: " . $e->getMessage() . "</p>";
}

echo "<h2>4. Data/Hora do Servidor</h2>";
echo "Data atual: " . date('Y-m-d') . "<br>";
echo "Hora atual: " . date('H:i:s') . "<br>";
echo "Data/hora completa: " . date('Y-m-d H:i:s') . "<br>";

echo "<h2>5. Teste da API</h2>";
echo "<a href='api/get_eventos_pendentes_new.php' target='_blank'>Clique aqui para testar a API diretamente</a><br>";
echo "<a href='teste_api_eventos.php' target='_blank'>Teste da classe Lembrete</a><br>";

echo "<hr>";
echo "<h2>Instruções</h2>";
echo "<ol>";
echo "<li>Verifique se há eventos na tabela acima</li>";
echo "<li>Clique no link da API para ver o JSON retornado</li>";
echo "<li>Abra o console do navegador (F12) e verifique erros</li>";
echo "<li>Faça logout e login novamente</li>";
echo "</ol>";
