<?php
// Script stub para coleta de certificadoras (modo seguro)
// Não roda automaticamente — use manualmente para testes e só execute se souber o que faz.
// Este script demonstra como importar registros via CSV ou via uma lista manual de seeds.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// Exemplo: importar de CSV (cabecalho: nome,cnpj,cidade,uf,site,logo_url)
$csvPath = __DIR__ . '/seeds/certificadoras_sample.csv';
if (file_exists($csvPath)) {
    $handle = fopen($csvPath, 'r');
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $data = array_combine($header, $row);
        // Inserir de forma segura (verificar duplicatas por nome/cnpj)
        $stmt = $db->prepare('SELECT id FROM certificadoras WHERE nome = ? LIMIT 1');
        $stmt->execute([$data['nome']]);
        if ($stmt->fetch()) continue; // pular duplicata

        $ins = $db->prepare('INSERT INTO certificadoras (nome, cnpj, cidade, uf, site, logo_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $ins->execute([$data['nome'], $data['cnpj'] ?? null, $data['cidade'] ?? null, $data['uf'] ?? null, $data['site'] ?? null, $data['logo_url'] ?? null]);
        echo "Importado: " . $data['nome'] . "\n";
    }
    fclose($handle);
} else {
    echo "Nenhum arquivo de seeds encontrado em $csvPath.\n";
}

// Para coletar logos: baixar manualmente e salvar em uploads/certificadoras/ com nomes seguros.

?>