<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

use Mpdf\Mpdf;

try {
    // Obter parâmetros de filtro
    $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
    $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-t');
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $categoria_id = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
    $propriedade_id = isset($_GET['propriedade_id']) ? (int)$_GET['propriedade_id'] : 0;
    $associado_id = isset($_GET['associado_id']) ? (int)$_GET['associado_id'] : 0;

    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // Formato paisagem
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Construir a consulta SQL com filtros
    $params = [];
    $where = ["ad.excluido = 0"];
    
    // Filtro de data
    $where[] = "ad.data_atividade BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;
    
    // Filtro de status
    if (!empty($status)) {
        $where[] = "ad.status = :status";
        $params[':status'] = $status;
    }
    
    // Filtro de categoria
    if ($categoria_id > 0) {
        $where[] = "ad.categoria_id = :categoria_id";
        $params[':categoria_id'] = $categoria_id;
    }
    
    // Filtro de propriedade
    if ($propriedade_id > 0) {
        $where[] = "ad.propriedade_id = :propriedade_id";
        $params[':propriedade_id'] = $propriedade_id;
    }
    
    // Filtro de associado (se não for admin, só mostra as próprias atividades)
    if ($_SESSION['user_nivel'] != 1 && $_SESSION['user_nivel'] != 2) { // Se não for admin nem gestor
        $userFiltro = new User();
        $assoc_id = $userFiltro->getAssociadoId();
        if ($assoc_id) {
            $where[] = "ad.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    } elseif ($associado_id > 0) {
        $where[] = "ad.associado_id = :associado_id";
        $params[':associado_id'] = $associado_id;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Consulta para buscar as atividades
    $sql = "SELECT 
        ad.*,
        p.propriedade,
        l.nome as lavoura,
        l.tipo_cultura as cultura,
        a.nome as associado_nome,
        a.sobrenome as associado_sobrenome,
        ca.nome as categoria_nome,
        ca.cor as categoria_cor,
        lo.nome_lote as lote_nome
        FROM atividades_diarias ad
        LEFT JOIN propriedades p ON ad.propriedade_id = p.id
        LEFT JOIN lavouras l ON ad.lavoura_id = l.id
        LEFT JOIN associados a ON ad.associado_id = a.id
        LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
        LEFT JOIN lotes_producao lo ON ad.lote_id = lo.id
        $where_clause
        ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_atividades = count($atividades);
    $concluidas = count(array_filter($atividades, function($a) { return $a['status'] === 'Concluído'; }));
    $pendentes = count(array_filter($atividades, function($a) { return $a['status'] === 'Pendente'; }));
    $em_andamento = count(array_filter($atividades, function($a) { return $a['status'] === 'Em andamento'; }));
    
    // Calcular totais por categoria
    $categorias = [];
    foreach ($atividades as $atividade) {
        $categoria = $atividade['categoria_nome'] ?? 'Sem categoria';
        if (!isset($categorias[$categoria])) {
            $categorias[$categoria] = 0;
        }
        $categorias[$categoria]++;
    }

    // HTML do relatório
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; color: #333; }
        .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
        .stats-table { width: 100%; margin: 20px 0; border-collapse: separate; border-spacing: 10px; border: none; }
        .stat-box { 
            width: 25%; 
            text-align: center; 
            padding: 15px 10px; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            background-color: #fcfcfc;
        }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .status-concluido { color: #28a745; font-weight: bold; }
        .status-pendente { color: #ffc107; font-weight: bold; }
        .status-andamento { color: #007bff; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <img src="' . SINPORG_LOGO_PATH . '" alt="SINPORG - Sistema Integrado da Produção Orgânica" style="height:58px; margin-bottom:6px;" />
        <div class="title">Relatório de Atividades Diárias</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-box">
                <div class="stat-number">' . $total_atividades . '</div>
                <div class="stat-label">Total de Atividades</div>
            </td>
            <td class="stat-box">
                <div class="stat-number">' . $concluidas . '</div>
                <div class="stat-label">Concluídas</div>
            </td>
            <td class="stat-box">
                <div class="stat-number">' . $pendentes . '</div>
                <div class="stat-label">Pendentes</div>
            </td>
            <td class="stat-box">
                <div class="stat-number">' . $em_andamento . '</div>
                <div class="stat-label">Em Andamento</div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Atividade</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Propriedade</th>
                <th>Lavoura</th>
                <th>Associado</th>
                <th>Insumo</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($atividades as $atividade) {
        $status_class = 'status-' . strtolower(str_replace(' ', '-', $atividade['status']));
        $html .= '
        <tr>
            <td>' . date('d/m/Y', strtotime($atividade['data_atividade'])) . '</td>
            <td>' . htmlspecialchars($atividade['nome']) . '</td>
            <td>' . htmlspecialchars($atividade['tipo_atividade']) . '</td>
            <td class="' . $status_class . '">' . htmlspecialchars($atividade['status']) . '</td>
            <td>' . htmlspecialchars($atividade['propriedade'] ?? '-') . '</td>
            <td>' . htmlspecialchars($atividade['lavoura'] ?? '-') . '</td>
            <td>' . htmlspecialchars($atividade['associado_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars($atividade['insumo_utilisado'] ?? '-') . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Relatório gerado pelo Sistema Integrado da Produção Orgânica</p>
        <p>Página {PAGENO} de {nbpg}</p>
    </div>';

    // Definir metadados do PDF
    $mpdf->SetTitle('Relatório de Atividades - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para exibição na tela
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_atividades.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_atividades.pdf', 'I');

} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?> 