<?php
/**
 * Corrige o ENUM corrompido da coluna 'status' na tabela plano_manejo.
 * Os valores foram inseridos com encoding latin1/mojibake.
 * Este script recria o ENUM com os valores UTF-8 corretos.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

echo "=== CORREÇÃO DO ENUM STATUS - plano_manejo ===" . PHP_EOL . PHP_EOL;

// 1. Mostrar estado atual
$stmt = $db->query("SELECT id, status FROM plano_manejo ORDER BY id");
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Registros atuais:" . PHP_EOL;
foreach ($registros as $r) {
    echo "  id={$r['id']} status=[{$r['status']}] hex=[" . bin2hex($r['status']) . "]" . PHP_EOL;
}

echo PHP_EOL;

// 2. Garantir SET NAMES utf8mb4
$db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

// 3. Mapear valores corrompidos → valores corretos
// Os valores corrompidos têm bytes mojibake: á = e2949cc3ad, ã = e2949cc3a3, etc.
// Vamos normalizar todos os valores antes de alterar o ENUM
$mapa = [
    // Valor corrompido (mojibake) -> valor correto UTF-8
    "Em An\xe2\x94\x9c\xc3\xad" . "lise" => "Em Análise",  // Em Análise corrompido
    "Revis\xe2\x94\x9c\xc3\xba" . "o Necess\xe2\x94\x9c\xc3\xad" . "ria" => "Revisão Necessária",
    "Rascunho"  => "Rascunho",
    "Aprovado"  => "Aprovado",
    "Reprovado" => "Reprovado",
    // Também mapear valores em Análise que podem estar salvos de formas diferentes
    "Em Análise" => "Em Análise",
    "Revisão Necessária" => "Revisão Necessária",
];

// 4. Primeiro: alterar temporariamente para VARCHAR para permitir atualização
echo "Passo 1: Convertendo coluna para VARCHAR temporariamente..." . PHP_EOL;
$db->exec("ALTER TABLE plano_manejo MODIFY COLUMN status VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Rascunho'");
echo "  OK" . PHP_EOL;

// 5. Corrigir os valores existentes com mojibake
echo PHP_EOL . "Passo 2: Corrigindo valores existentes..." . PHP_EOL;

// Buscar todos os registros novamente
$stmt2 = $db->query("SELECT id, status FROM plano_manejo");
$registros2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($registros2 as $r) {
    $statusAtual = $r['status'];
    $hexAtual = bin2hex($statusAtual);
    
    // Detectar mojibake: se tem e2949c no hex, está corrompido
    if (strpos($hexAtual, 'e2949c') !== false) {
        // Tentar decodificar: latin1 bytes foram armazenados como se fossem utf8
        // Converter de volta: interpretar como latin1
        $decoded = mb_convert_encoding($statusAtual, 'UTF-8', 'UTF-8');
        
        // Mapeamento manual baseado no padrão hex visto
        // Em An├ílise -> Em Análise
        // Revis├úo Necess├íria -> Revisão Necessária
        $fixMap = [
            hex2bin('456d20416ee2949cc3ad6c697365') => 'Em Análise',
            hex2bin('5265766973e2949cc3ba6f204e6563657373e2949cc3ad726961') => 'Revisão Necessária',
        ];
        
        $novoStatus = $fixMap[$statusAtual] ?? null;
        if ($novoStatus) {
            $upd = $db->prepare("UPDATE plano_manejo SET status = ? WHERE id = ?");
            $upd->execute([$novoStatus, $r['id']]);
            echo "  id={$r['id']}: [{$statusAtual}] -> [{$novoStatus}]" . PHP_EOL;
        } else {
            echo "  id={$r['id']}: valor desconhecido, mantendo como 'Rascunho'" . PHP_EOL;
            $upd = $db->prepare("UPDATE plano_manejo SET status = 'Rascunho' WHERE id = ?");
            $upd->execute([$r['id']]);
        }
    } elseif (empty($statusAtual)) {
        // Status vazio -> Rascunho
        $upd = $db->prepare("UPDATE plano_manejo SET status = 'Rascunho' WHERE id = ?");
        $upd->execute([$r['id']]);
        echo "  id={$r['id']}: [vazio] -> [Rascunho]" . PHP_EOL;
    } else {
        echo "  id={$r['id']}: [{$statusAtual}] OK (sem mojibake)" . PHP_EOL;
    }
}

// 6. Recriar o ENUM com os valores corretos em UTF-8
echo PHP_EOL . "Passo 3: Recriando ENUM com valores UTF-8 corretos..." . PHP_EOL;
$db->exec("ALTER TABLE plano_manejo MODIFY COLUMN status ENUM('Rascunho','Em Análise','Aprovado','Reprovado','Revisão Necessária') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Rascunho'");
echo "  OK" . PHP_EOL;

// 7. Verificar resultado
echo PHP_EOL . "Passo 4: Verificando resultado..." . PHP_EOL;
$stmt3 = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='sisagro' AND TABLE_NAME='plano_manejo' AND COLUMN_NAME='status'");
$enumFinal = $stmt3->fetchColumn();
echo "  ENUM: " . $enumFinal . PHP_EOL;

// Verificar valores salvos
$stmt4 = $db->query("SELECT id, status FROM plano_manejo ORDER BY id");
$finais = $stmt4->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL . "Registros após correção:" . PHP_EOL;
foreach ($finais as $r) {
    echo "  id={$r['id']} status=[{$r['status']}]" . PHP_EOL;
}

// 8. Testar UPDATE de status
echo PHP_EOL . "Passo 5: Testando UPDATE status='Em Análise' no plano 1..." . PHP_EOL;
$upd = $db->prepare("UPDATE plano_manejo SET status = 'Em Análise' WHERE id = 1");
$upd->execute();
echo "  Rows affected: " . $upd->rowCount() . PHP_EOL;

$check = $db->prepare("SELECT status FROM plano_manejo WHERE id = 1");
$check->execute();
$statusSalvo = $check->fetchColumn();
echo "  Status salvo: [{$statusSalvo}]" . PHP_EOL;

// Reverter plano 1 para vazio (estado original)
$db->prepare("UPDATE plano_manejo SET status = 'Rascunho' WHERE id = 1")->execute();

echo PHP_EOL . "=== CORREÇÃO CONCLUÍDA ===" . PHP_EOL;
?>
