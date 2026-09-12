<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Propriedade.php';

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da propriedade não fornecido']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar dados detalhados da propriedade com JOINs
    $sql = "
        SELECT 
            p.*,
            c.nome AS cidade_nome,
            e.uf AS estado_sigla,
            e.nome AS estado_nome,
            COUNT(DISTINCT l.id) as total_lavouras,
            COUNT(DISTINCT ad.id) as total_atividades,
            SUM(l.area) as area_total_lavouras
        FROM propriedades p
        LEFT JOIN cidades c ON p.cidade = c.id
        LEFT JOIN estados e ON p.estado = e.uf
        LEFT JOIN lavouras l ON p.id = l.propriedade_id
        LEFT JOIN atividades_diarias ad ON p.id = ad.propriedade_id
        WHERE p.id = :id
        GROUP BY p.id, p.propriedade, p.area_total, p.endereco, p.cep, p.bairro, 
                 p.cidade, p.estado, p.latitude, p.longitude, p.observacoes, 
                 p.created_at, c.nome, e.uf, e.nome
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $propriedade = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$propriedade) {
        http_response_code(404);
        echo json_encode(['error' => 'Propriedade não encontrada']);
        exit;
    }
    
    // Check IDOR for API
    require_once __DIR__ . '/../classes/User.php';
    $userAPI = new User();
    if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == NIVEL_USUARIO) {
        if ($propriedade['associado_id'] != $userAPI->getAssociadoId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado: propriedade de outro produtor']);
            exit;
        }
    }
    
    // Buscar lavouras da propriedade
    $sql_lavouras = "
        SELECT 
            l.id,
            l.nome,
            l.tipo_cultura as cultura,
            l.area,
            l.data_plantio,
            DATE_FORMAT(l.data_plantio, '%d/%m/%Y') as data_plantio_formatada
        FROM lavouras l
        WHERE l.propriedade_id = :propriedade_id
        ORDER BY l.data_plantio DESC
    ";
    
    $stmt_lavouras = $db->prepare($sql_lavouras);
    $stmt_lavouras->bindParam(':propriedade_id', $id, PDO::PARAM_INT);
    $stmt_lavouras->execute();
    $lavouras = $stmt_lavouras->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar atividades recentes da propriedade
    $sql_atividades = "
        SELECT 
            ad.id,
            ad.nome,
            ad.descricao,
            ad.data_atividade,
            ad.hora_inicio,
            ad.hora_fim,
            ad.status,
            ad.tipo_atividade,
            ca.nome AS categoria_nome,
            ca.cor AS categoria_cor,
            DATE_FORMAT(ad.data_atividade, '%d/%m/%Y') as data_formatada
        FROM atividades_diarias ad
        LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
        WHERE ad.propriedade_id = :propriedade_id AND ad.excluido = 0
        ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC
        LIMIT 10
    ";
    
    $stmt_atividades = $db->prepare($sql_atividades);
    $stmt_atividades->bindParam(':propriedade_id', $id, PDO::PARAM_INT);
    $stmt_atividades->execute();
    $atividades = $stmt_atividades->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para resposta
    $response = [
        'propriedade' => [
            'id' => $propriedade['id'],
            'nome' => $propriedade['propriedade'],
            'area_total' => $propriedade['area_total'] ? number_format($propriedade['area_total'], 2) . ' ha' : 'Não informado',
            'endereco' => $propriedade['endereco'] ?: 'Não informado',
            'cep' => $propriedade['cep'] ?: 'Não informado',
            'bairro' => $propriedade['bairro'] ?: 'Não informado',
            'cidade' => $propriedade['cidade_nome'] ?: 'Não informado',
            'estado' => $propriedade['estado_sigla'] ?: 'Não informado',
            'estado_nome' => $propriedade['estado_nome'] ?: 'Não informado',
            'latitude' => $propriedade['latitude'] ?: 'Não informado',
            'longitude' => $propriedade['longitude'] ?: 'Não informado',
            'observacoes' => $propriedade['observacoes'] ?: 'Não informado',
            'data_cadastro' => date('d/m/Y', strtotime($propriedade['created_at'])),
            'imagem_principal' => $propriedade['imagem_principal'] ?: null,
            'imagem_secundaria' => $propriedade['imagem_secundaria'] ?: null
        ],
        'estatisticas' => [
            'total_lavouras' => (int)$propriedade['total_lavouras'],
            'total_atividades' => (int)$propriedade['total_atividades'],
            'area_total_lavouras' => $propriedade['area_total_lavouras'] ? number_format($propriedade['area_total_lavouras'], 2) . ' ha' : '0 ha'
        ],
        'lavouras' => $lavouras,
        'atividades_recentes' => $atividades
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?> 