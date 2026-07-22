<?php
// Verificar se o servidor está respondendo
echo "Teste de conexão bem-sucedido!\n";
echo "Versão do PHP: " . phpversion() . "\n";

// Verificar extensões necessárias
$extensoes = ['pdo', 'pdo_mysql', 'gd', 'zip', 'mbstring'];
echo "\nExtensões carregadas:\n";
foreach ($extensoes as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? 'OK' : 'NÃO CARREGADA') . "\n";}

// Verificar permissões de diretório
$diretorios = [
    __DIR__ . '/../../../uploads',
    __DIR__ . '/../../../cache'
];

echo "\nVerificação de permissões:\n";
foreach ($diretorios as $dir) {
    $status = is_writable($dir) ? 'gravável' : 'não gravável';
    echo "$dir: $status\n";}
