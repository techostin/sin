<?php
/**
 * API Controller - Certificação Participativa
 * 
 * Gerencia operações CRUD para certificações SPG/OCS
 * Base: Portaria MAPA 52/2021
 */

// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../../config/error_config.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

// Obter método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

// Proteção CSRF para métodos de escrita
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
    require_once __DIR__ . '/../../classes/CSRFProtection.php';
    if (!CSRFProtection::validateToken(CSRFProtection::tokenFromRequest())) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido ou ausente.']);
        exit();
    }
}

// Roteador de métodos
switch ($method) {
    case 'GET':
        handleGet($db, $user);
        break;
    
    case 'POST':
        if (!$user->hasPermission(NIVEL_GESTOR)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores e gestores podem emitir certificações.']);
            exit();
        }
        // Detect if it's multipart form (file upload) or JSON
        if (!empty($_FILES) || isset($_POST['action'])) {
            handleUploadAssinatura($db);
        } else {
            handlePost($db);
        }
        break;
    
    case 'PUT':
        if (!$user->hasPermission(NIVEL_GESTOR)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores e gestores podem editar certificações.']);
            exit();
        }
        handlePut($db);
        break;
    
    case 'DELETE':
        if (!$user->hasPermission(NIVEL_GESTOR)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Apenas administradores e gestores podem excluir certificações.']);
            exit();
        }
        handleDelete($db);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}

/**
 * GET - Listar certificações ou obter uma específica
 */
function handleGet($db, $user) {
    // Produtos do associado (para formulário de certificação)
    if (isset($_GET['action']) && $_GET['action'] === 'produtos' && isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
        getProdutosAssociado($db, (int) $_GET['associado_id']);
        return;
    }

    // Se tem ID, busca uma certificação específica
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        getCertificacaoById($db, $user, $_GET['id']);
        return;
    }
    
    // Caso contrário, lista todas (com filtros opcionais)
    listCertificacoes($db, $user);
}

/**
 * Lista produtos do associado para seleção no certificado
 */
function getProdutosAssociado($db, $associadoId) {
    require_once __DIR__ . '/../../classes/Produto.php';
    $produto = new Produto();
    $produtos = $produto->getByAssociadoId($associadoId);

    http_response_code(200);
    echo json_encode(['data' => $produtos]);
}

/**
 * Buscar certificação por ID
 */
function getCertificacaoById($db, $user, $id) {
    $sql = "SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM certificacao_participativa c
            LEFT JOIN associados a ON c.associado_id = a.id
            WHERE c.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    $certificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificacao) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }

    // Restrição para usuários comuns
    if ($_SESSION['user_nivel'] == NIVEL_USUARIO) {
        $associadoId = $user->getAssociadoId();
        if ($certificacao['associado_id'] != $associadoId) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado. Você só pode visualizar seus próprios certificados.']);
            return;
        }
    }
    
    // Decodificar JSON
    if (!empty($certificacao['dados_adicionais'])) {
        $certificacao['dados_adicionais'] = json_decode($certificacao['dados_adicionais'], true);
    }
    
    http_response_code(200);
    echo json_encode($certificacao);
}

/**
 * Listar certificações com filtros
 */
function listCertificacoes($db, $user) {
    $sql = "SELECT c.*, a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM certificacao_participativa c
            LEFT JOIN associados a ON c.associado_id = a.id
            WHERE 1=1";
    
    $params = [];

    // Restrição para usuários comuns: ver apenas o seu próprio histórico
    if ($_SESSION['user_nivel'] == NIVEL_USUARIO) {
        $associadoId = $user->getAssociadoId();
        $sql .= " AND c.associado_id = ?";
        $params[] = $associadoId;
    } elseif (isset($_GET['associado_id']) && is_numeric($_GET['associado_id'])) {
        $sql .= " AND c.associado_id = ?";
        $params[] = $_GET['associado_id'];
    }
    
    // Filtro para certificações vencidas/a vencer
    if (isset($_GET['validade']) && $_GET['validade'] === 'vencidas') {
        $sql .= " AND c.data_validade < CURDATE()";
    } elseif (isset($_GET['validade']) && $_GET['validade'] === 'proximas') {
        // Vence nos próximos 30 dias
        $sql .= " AND c.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    }
    
    // Ordenação
    $sql .= " ORDER BY c.data_validade DESC, c.created_at DESC";
    
    // Paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    // Contar total de registros
    $countSql = "SELECT COUNT(*) as total FROM certificacao_participativa c WHERE 1=1 ";
    if ($_SESSION['user_nivel'] == NIVEL_USUARIO) {
        $countSql .= " AND c.associado_id = ?";
    } else {
        $countSql .= (isset($_GET['tipo_certificacao']) && !empty($_GET['tipo_certificacao']) ? " AND c.tipo_certificacao = ?" : "") .
                     (isset($_GET['status']) && !empty($_GET['status']) ? " AND c.status = ?" : "") .
                     (isset($_GET['associado_id']) && is_numeric($_GET['associado_id']) ? " AND c.associado_id = ?" : "");
    }
    $countSql .= (isset($_GET['validade']) && $_GET['validade'] === 'vencidas' ? " AND c.data_validade < CURDATE()" : "") .
                 (isset($_GET['validade']) && $_GET['validade'] === 'proximas' ? " AND c.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)" : "");

    $stmtCount = $db->prepare($countSql);
    
    // Filtrar os parâmetros para o count (apenas os filtros, sem limit/offset)
    $stmtCount->execute($params);
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Buscar registros com paginação
    // Importante: No MySQL, LIMIT e OFFSET não podem ser passados como strings entre aspas
    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $certificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON de dados adicionais
    // Enriquecer resposta
    foreach ($certificacoes as &$cert) {
        if (!empty($cert['dados_adicionais'])) {
            $cert['dados_adicionais'] = json_decode($cert['dados_adicionais'], true);
        }
        
        // Flag para indicar se o documento está disponível (assinado)
        // Verifica se tem assinatura_arquivo (upload do PDF assinado) OU documento_url
        $cert['is_signed'] = !empty($cert['assinatura_arquivo']) || !empty($cert['documento_url']);
        
        // Incluir informações de assinatura na listagem
        if (!empty($cert['assinatura_data'])) {
            $cert['assinatura_data_formatada'] = date('d/m/Y H:i', strtotime($cert['assinatura_data']));
        } else {
            $cert['assinatura_data_formatada'] = null;
        }
        $cert['data_renovacao_formatada'] = !empty($cert['data_renovacao']) 
            ? date('d/m/Y', strtotime($cert['data_renovacao'])) 
            : null;
    }
    
    http_response_code(200);
    echo json_encode([
        'data' => $certificacoes,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * POST - Criar nova certificação
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validações
    if (empty($input['associado_id']) || !is_numeric($input['associado_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'associado_id é obrigatório']);
        return;
    }
    
    if (empty($input['tipo_certificacao'])) {
        $input['tipo_certificacao'] = 'SPG'; // Default
    }
    
    // Validar tipo de certificação
    $tiposPermitidos = ['SPG', 'OCS', 'Rede Ecovida', 'Outro'];
    if (!in_array($input['tipo_certificacao'], $tiposPermitidos)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de certificação inválido']);
        return;
    }
    
    // Campos opcionais
    $numero_certificado = $input['numero_certificado'] ?? null;
    $data_emissao = $input['data_emissao'] ?? null;
    $data_validade = $input['data_validade'] ?? null;
    $status = $input['status'] ?? 'Em Conversão';
    $organismo_certificador = $input['organismo_certificador'] ?? null;
    $nome_grupo = $input['nome_grupo'] ?? null;
    $documento_url = $input['documento_url'] ?? null;
    $declaracao_compromisso_url = $input['declaracao_compromisso_url'] ?? null;
    $observacoes = $input['observacoes'] ?? null;
    
    // Dados adicionais (JSON) — escopo e produtos do certificado
    $dadosAdicionaisArr = montarDadosAdicionais($db, $input);
    $dados_adicionais = json_encode($dadosAdicionaisArr);
    
    // Inserir no banco
    $sql = "INSERT INTO certificacao_participativa 
            (associado_id, tipo_certificacao, numero_certificado, data_emissao, data_validade, 
             status, organismo_certificador, nome_grupo, documento_url, declaracao_compromisso_url, 
             dados_adicionais, observacoes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $input['associado_id'],
            $input['tipo_certificacao'],
            $numero_certificado,
            $data_emissao,
            $data_validade,
            $status,
            $organismo_certificador,
            $nome_grupo,
            $documento_url,
            $declaracao_compromisso_url,
            $dados_adicionais,
            $observacoes
        ]);
        
        $id = $db->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação criada com sucesso',
            'id' => $id
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao criar certificação',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * PUT - Atualizar certificação existente
 */
function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id']) || !is_numeric($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID é obrigatório']);
        return;
    }
    
    // Verificar se a certificação existe
    $checkSql = "SELECT id, associado_id FROM certificacao_participativa WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$input['id']]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }

    if (empty($input['associado_id'])) {
        $input['associado_id'] = $existing['associado_id'];
    }
    
    // Construir SQL dinâmico apenas para campos fornecidos
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'tipo_certificacao', 'numero_certificado', 'data_emissao', 'data_validade',
        'status', 'organismo_certificador', 'nome_grupo', 'documento_url',
        'declaracao_compromisso_url', 'observacoes'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    // Dados adicionais (JSON)
    if (isset($input['dados_adicionais']) || isset($input['escopo']) || isset($input['produtos'])) {
        $fields[] = "dados_adicionais = ?";
        $values[] = json_encode(montarDadosAdicionais($db, $input));
    }
    
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum campo para atualizar']);
        return;
    }
    
    // Adicionar updated_at
    $fields[] = "updated_at = NOW()";
    
    // Adicionar ID ao final
    $values[] = $input['id'];
    
    $sql = "UPDATE certificacao_participativa SET " . implode(', ', $fields) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação atualizada com sucesso'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao atualizar certificação',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * POST (multipart) - Upload de assinatura digital (PDF do certificado assinado)
 * 
 * Recebe o arquivo PDF assinado e salva no servidor, registrando a assinatura
 */
function handleUploadAssinatura($db) {
    $certificacaoId = $_POST['id'] ?? ($_GET['id'] ?? null);
    
    if (!$certificacaoId || !is_numeric($certificacaoId)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da certificação é obrigatório']);
        return;
    }
    
    // Verificar se a certificação existe
    $checkSql = "SELECT id, numero_certificado, assinatura_arquivo FROM certificacao_participativa WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$certificacaoId]);
    $cert = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cert) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }
    
    // Verificar upload do arquivo
    if (!isset($_FILES['arquivo_assinatura']) || $_FILES['arquivo_assinatura']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['arquivo_assinatura']['error'] ?? -1;
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo definido no formulário.',
            UPLOAD_ERR_PARTIAL => 'O upload foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão.',
        ];
        $msg = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Erro desconhecido no upload.';
        http_response_code(400);
        echo json_encode(['error' => $msg]);
        return;
    }
    
    $arquivo = $_FILES['arquivo_assinatura'];
    
    // Validar tipo de arquivo (apenas PDF)
    $allowedTypes = ['application/pdf', 'image/tiff', 'image/tif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    // Fallback: verificar extensão
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf'];
    
    if (!in_array($ext, $allowedExts)) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato inválido. Apenas arquivos PDF são aceitos para assinatura digital.']);
        return;
    }
    
    // Criar diretório de upload se não existir
    $uploadDir = __DIR__ . '/../../uploads/certificados/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $numeroCert = !empty($cert['numero_certificado']) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert['numero_certificado']) : 'cert';
    $timestamp = date('Ymd_His');
    $nomeArquivo = "cert_{$certificacaoId}_{$numeroCert}_{$timestamp}.pdf";
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    // Mover arquivo para o diretório de destino
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar o arquivo de assinatura.']);
        return;
    }
    
    // Construir URL pública do arquivo
    $documentoUrl = SITE_URL . '/uploads/certificados/' . $nomeArquivo;
    
    // Registrar assinatura no banco
    $usuarioNome = $_SESSION['user_nome'] ?? 'Administrador';
    
    $updateSql = "UPDATE certificacao_participativa SET 
                  assinatura_arquivo = ?,
                  assinatura_data = NOW(),
                  assinatura_por = ?,
                  documento_url = ?,
                  updated_at = NOW()
                  WHERE id = ?";
    
    try {
        $stmt = $db->prepare($updateSql);
        $stmt->execute([$nomeArquivo, $usuarioNome, $documentoUrl, $certificacaoId]);
        
        // Se veio da renovação, também atualizar status e data de renovação
        if (isset($_POST['status'])) {
            $renovSql = "UPDATE certificacao_participativa SET 
                         status = ?,
                         data_emissao = ?,
                         data_validade = ?,
                         numero_certificado = ?,
                         observacoes = CONCAT(IFNULL(observacoes, ''), ?),
                         data_renovacao = CURDATE(),
                         updated_at = NOW()
                         WHERE id = ?";
            $obsAdicional = "\n\n[Renovado em " . date('d/m/Y H:i') . "] " . ($_POST['observacoes'] ?? '');
            $renovStmt = $db->prepare($renovSql);
            $renovStmt->execute([
                $_POST['status'] ?? 'Ativa',
                $_POST['data_emissao'] ?? date('Y-m-d'),
                $_POST['data_validade'] ?? date('Y-m-d', strtotime('+1 year')),
                $_POST['numero_certificado'] ?? $cert['numero_certificado'],
                $obsAdicional,
                $certificacaoId
            ]);
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Certificado assinado e salvo com sucesso!',
            'documento_url' => $documentoUrl,
            'arquivo' => $nomeArquivo,
            'assinado_por' => $usuarioNome,
            'assinado_em' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        // Remover arquivo se falhou no banco
        if (file_exists($caminhoCompleto)) {
            unlink($caminhoCompleto);
        }
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao registrar assinatura',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Monta JSON de dados adicionais com escopo e produtos resolvidos
 */
function montarDadosAdicionais($db, array $input): array {
    require_once __DIR__ . '/../../classes/Produto.php';

    $adicionais = $input['dados_adicionais'] ?? [];
    if (!is_array($adicionais)) {
        $adicionais = [];
    }

    $escopo = $input['escopo'] ?? ($adicionais['escopo'] ?? '');
    $produtoIds = $input['produtos'] ?? ($adicionais['produtos'] ?? []);
    $associadoId = (int) ($input['associado_id'] ?? 0);

    // Se produtos já vieram como objetos completos, manter
    if (!empty($produtoIds) && is_array($produtoIds[0] ?? null) && isset($produtoIds[0]['nome'])) {
        return ['escopo' => $escopo, 'produtos' => $produtoIds];
    }

    $produtos = [];
    if ($associadoId > 0) {
        $produtoModel = new Produto();
        $todos = $produtoModel->getByAssociadoId($associadoId);
        $ids = array_map('intval', (array) $produtoIds);

        if (!empty($ids)) {
            $produtos = array_values(array_filter($todos, fn($p) => in_array((int) $p['id'], $ids, true)));
        } else {
            $produtos = $todos;
        }
    }

    return ['escopo' => $escopo, 'produtos' => $produtos];
}

/**
 * DELETE - Excluir certificação
 */
function handleDelete($db) {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID é obrigatório']);
        return;
    }
    
    $id = $_GET['id'];
    
    // Verificar se existe
    $checkSql = "SELECT id FROM certificacao_participativa WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Certificação não encontrada']);
        return;
    }
    
    // Excluir
    $sql = "DELETE FROM certificacao_participativa WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Certificação excluída com sucesso'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro ao excluir certificação',
            'message' => $e->getMessage()
        ]);
    }
}
