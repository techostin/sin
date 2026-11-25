<?php
/**
 * Migração: vincula usuários às certificadoras e preenche logos
 * - Admin (Edmilson, id 7) -> certificadora 13 (Ecovida)
 * - Gestor (kajiani, id 10) -> certificadora 15 (COCEARGS)
 * - Preenche logo_filename nas certificadoras
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance()->getConnection();

// 1. Vincular usuários às certificadoras
$updates = [
    [7, 13],   // Edmilson (admin) -> Ecovida
    [10, 15],  // kajiani (gestor) -> COCEARGS
];

foreach ($updates as [$userId, $certId]) {
    $stmt = $db->prepare("UPDATE usuarios SET certificadora_id = ? WHERE id = ?");
    $stmt->execute([$certId, $userId]);
    echo "Usuário {$userId} vinculado à certificadora {$certId}\n";
}

// 2. Preencher logo_filename nas certificadoras
$logos = [
    13 => 'logo_ECOVIDA.jpeg',
    14 => 'logo_LITORAL_NORTE.jpeg',
    15 => 'logo_COCEARGS-1.jpeg',
    16 => 'logo_RAMA.jpeg',
];

foreach ($logos as $certId => $logoFile) {
    $stmt = $db->prepare("UPDATE certificadoras SET logo_filename = ?, logo_url = NULL WHERE id = ?");
    $stmt->execute([$logoFile, $certId]);
    echo "Certificadora {$certId} recebeu logo {$logoFile}\n";
}

echo "\nMigração concluída com sucesso!\n";
