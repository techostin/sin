<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== Migração: Adicionar colunas status e correcoes_realizadas ===\n\n";

// Verificar coluna status
$check = $db->query("SHOW COLUMNS FROM ocorrencias_manejo LIKE 'status'");
if (!$check->fetch()) {
    $db->exec("ALTER TABLE ocorrencias_manejo ADD COLUMN status ENUM('Aberta','Em Correção','Resolvida','Fechada') NOT NULL DEFAULT 'Aberta' AFTER severidade");
    echo "  [OK] Coluna 'status' adicionada\n";
} else {
    echo "  [OK] Coluna 'status' já existe\n";
}

// Verificar coluna correcoes_realizadas
$check2 = $db->query("SHOW COLUMNS FROM ocorrencias_manejo LIKE 'correcoes_realizadas'");
if (!$check2->fetch()) {
    $db->exec("ALTER TABLE ocorrencias_manejo ADD COLUMN correcoes_realizadas TEXT DEFAULT NULL AFTER foto_url");
    echo "  [OK] Coluna 'correcoes_realizadas' adicionada\n";
} else {
    echo "  [OK] Coluna 'correcoes_realizadas' já existe\n";
}

echo "\n=== Estrutura atualizada! ===\n";

