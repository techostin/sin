<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Lembrete.php';

echo "<h2>Teste de Eventos</h2>";

try {
    $lembrete = new Lembrete();
    
    echo "<h3>Eventos de Hoje:</h3>";
    $hoje = $lembrete->getEventosHoje();
    echo "<pre>";
    print_r($hoje);
    echo "</pre>";
    
    echo "<h3>Eventos Próximos (30 dias):</h3>";
    $proximos = $lembrete->getEventosProximos(30);
    echo "<pre>";
    print_r($proximos);
    echo "</pre>";
    
    echo "<h3>Data Atual do Servidor:</h3>";
    echo date('Y-m-d H:i:s');
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
