<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn()) {
    die("Acesso não autorizado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['croqui'])) {
    $plano_id = $_POST['plano_id'];
    $file = $_FILES['croqui'];

    // Validar arquivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($file['type'], $allowedTypes)) {
        die("Tipo de arquivo não permitido. Apenas JPG, PNG, GIF e PDF.");
    }

    // Criar diretório de uploads se não existir
    $uploadDir = __DIR__ . '/../../../uploads/planos_manejo/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'croqui_' . $plano_id . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Atualizar no banco
        $db = Database::getInstance()->getConnection();
        $fileUrl = SITE_URL . '/uploads/planos_manejo/' . $fileName;
        
        $stmt = $db->prepare("UPDATE plano_manejo SET croqui_propriedade_url = ? WHERE id = ?");
        $stmt->execute([$fileUrl, $plano_id]);

        header("Location: " . SITE_URL . "/plano-manejo/" . $plano_id . "/visualizar?success=upload");
    } else {
        die("Erro ao mover o arquivo.");
    }
}
