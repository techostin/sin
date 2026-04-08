<?php
// Iniciar a sessão
session_start();

// Incluir configurações e classes necessárias
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Lembrete.php';

// Forçar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simular usuário logado para teste
$_SESSION['user_id'] = 1;

// Instanciar a classe Lembrete
$lembrete = new Lembrete();

// Buscar eventos
echo "<h3>Teste de Eventos</h3>";

echo "<h4>Eventos de Hoje:</h4>";
$eventos_hoje = $lembrete->getEventosHoje();
echo "<pre>" . print_r($eventos_hoje, true) . "</pre>";

echo "<h4>Próximos Eventos (14 dias):</h4>";
$eventos_proximos = $lembrete->getEventosProximos(14);
echo "<pre>" . print_r($eventos_proximos, true) . "</pre>";

echo "<h4>Eventos Atrasados:</h4>";
$eventos_atrasados = $lembrete->getEventosAtrasados();
echo "<pre>" . print_r($eventos_atrasados, true) . "</pre>";

echo "<h4>Verificar Lembretes:</h4>";
$lembretes = $lembrete->verificarLembretes();
echo "<pre>" . print_r($lembretes, true) . "</pre>";

// Verificar estrutura da tabela de eventos
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW COLUMNS FROM eventos");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Estrutura da Tabela 'eventos':</h4>";
    echo "<pre>" . print_r($colunas, true) . "</pre>";
    
    // Contar eventos na tabela
    $stmt = $db->query("SELECT COUNT(*) as total FROM eventos");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h4>Total de Eventos no Banco de Dados: " . $total['total'] . "</h4>";
    
    // Mostrar alguns eventos de exemplo
    $stmt = $db->query("SELECT * FROM eventos ORDER BY data_evento ASC LIMIT 5");
    $exemplos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Exemplos de Eventos:</h4>";
    echo "<pre>" . print_r($exemplos, true) . "</pre>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>Erro ao acessar o banco de dados: " . $e->getMessage() . "</div>";
}

// Verificar se o arquivo de log de erros está acessível
$log_file = ini_get('error_log');
echo "<h4>Arquivo de Log de Erros:</h4>";
echo "Localização: " . ($log_file ? $log_file : 'Não configurado') . "<br>";

if ($log_file && file_exists($log_file)) {
    echo "Tamanho do arquivo: " . filesize($log_file) . " bytes<br>";
    echo "Última modificação: " . date('d/m/Y H:i:s', filemtime($log_file)) . "<br>";
    
    // Mostrar as últimas 20 linhas do log
    $log_content = `tail -n 20 "$log_file"`;
    echo "<h5>Últimas linhas do log:</h5>";
    echo "<pre>" . htmlspecialchars($log_content) . "</pre>";
} else {
    echo "Arquivo de log não encontrado ou não acessível.<br>";
}
?>
