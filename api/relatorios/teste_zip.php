<?php
// Verificar se a extensão ZIP está carregada
if (extension_loaded('zip')) {
    echo "Extensão ZIP está carregada com sucesso!\n";
    
    // Testar funcionalidade básica do ZIP
    $zip = new ZipArchive();
    $testFile = __DIR__ . '/teste_arquivo.txt';
    
    // Criar um arquivo de teste
    file_put_contents($testFile, 'Este é um arquivo de teste para verificar a funcionalidade ZIP.');
    
    // Criar um arquivo ZIP
    $zipFile = __DIR__ . '/teste_arquivo.zip';
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($testFile, 'teste_arquivo.txt');
        $zip->close();
        echo "Arquivo ZIP criado com sucesso em: $zipFile\n";
        
        // Verificar se o arquivo ZIP foi criado
        if (file_exists($zipFile)) {
            echo "Tamanho do arquivo ZIP: " . filesize($zipFile) . " bytes\n";
        }
        
        // Limpar arquivos de teste
        unlink($testFile);
        unlink($zipFile);
    } else {
        echo "Erro ao criar o arquivo ZIP\n";
    }
} else {
    echo "A extensão ZIP NÃO está carregada.\n";
    
    // Mostrar extensões carregadas para diagnóstico
    echo "\nExtensões carregadas:\n";
    $extensoes = get_loaded_extensions();
    sort($extensoes);
    echo implode("\n", $extensoes);
}
