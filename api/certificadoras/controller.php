<?php
// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../../config/error_config.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Certificadora.php';
require_once __DIR__ . '/../../classes/User.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$certificadora = new Certificadora();

switch ($method) {
    case 'GET':
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $data = $certificadora->getById($_GET['id']);
            if ($data) {
                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Não encontrado']);
            }
        } else {
            $data = $certificadora->getAll();
            echo json_encode(['data' => $data]);
        }
        break;

    case 'POST':
        if (!$user->hasPermission(NIVEL_ADMIN)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit();
        }

        // Lidar com upload de imagem
        $dados = $_POST;
        if (isset($_FILES['logo_arquivo']) && $_FILES['logo_arquivo']['error'] === UPLOAD_ERR_OK) {
            $dados['logo_url'] = handleUpload($_FILES['logo_arquivo']);
        }

        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Atualizar
            if ($certificadora->update($dados)) {
                echo json_encode(['success' => true, 'message' => 'Atualizado com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao atualizar']);
            }
        } else {
            // Criar
            if ($certificadora->create($dados)) {
                echo json_encode(['success' => true, 'message' => 'Criado com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar']);
            }
        }
        break;

    case 'DELETE':
        if (!$user->hasPermission(NIVEL_ADMIN)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit();
        }
        $id = $_GET['id'] ?? null;
        if ($id && $certificadora->delete($id)) {
            echo json_encode(['success' => true, 'message' => 'Excluído com sucesso']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao excluir']);
        }
        break;
}

function handleUpload($file) {
    $uploadDir = __DIR__ . '/../../uploads/logos_certificadoras/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($ext, $allowedExts)) {
        return null;
    }

    // Confirmar que o arquivo é uma imagem válida (conteúdo real)
    if (!getimagesize($file['tmp_name'])) {
        return null;
    }

    $fileName = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Retornar URL relativa para ser guardada no DB
        return SITE_URL . '/uploads/logos_certificadoras/' . $fileName;
    }
    return null;
}
