<?php
$f = __DIR__ . '/../img/manual_do_usuario.pdf';
echo "Arquivo: $f\n";
echo "Existe: " . (file_exists($f) ? 'SIM' : 'NÃO') . "\n";
echo "Tamanho: " . round(filesize($f) / 1024) . " KB\n";

$conteudo = file_get_contents($f);
echo "Header PDF: " . (strpos($conteudo, '%PDF') === 0 ? 'VÁLIDO' : 'INVÁLIDO') . "\n";

// Contar páginas
preg_match_all('/\/Type\s*\/Page[^s]/', $conteudo, $matches);
echo "Páginas detectadas: " . count($matches[0]) . "\n";

// Verificar se imagens estão embutidas
echo "Objetos de imagem (XObject): " . substr_count($conteudo, '/Subtype /Image') . "\n";

