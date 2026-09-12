<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $user = new User();

    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // Formato paisagem
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Filtro de escopo: não-gestores só veem produção das próprias propriedades
    $params = [];
    $where = [];
    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "prop.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Buscar dados de produção
    $sql = "SELECT 
        p.*,
        l.nome as lavoura_nome,
        l.tipo_cultura as cultura,
        l.area as area_lavoura,
        l.data_plantio,
        prop.propriedade,
        a.nome as associado_nome,
        lp.nome_lote,
        lp.data_producao,
        lp.quantidade as quantidade_lote,
        lp.unidade_medida as unidade_lote,
        CONCAT('PROD-', LPAD(p.id, 6, '0')) as codigo_rastreabilidade_produto,
        CONCAT('LOTE-', LPAD(lp.id, 6, '0')) as codigo_rastreabilidade_lote
        FROM produtos p
        LEFT JOIN lavouras l ON p.lavoura_id = l.id
        LEFT JOIN propriedades prop ON l.propriedade_id = prop.id
        LEFT JOIN associados a ON prop.associado_id = a.id
        LEFT JOIN lotes_producao lp ON p.id = lp.produto_id
        $where_clause
        ORDER BY p.data_colheita DESC, lp.data_producao DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_produtos = count($produtos);
    $total_produzido = array_sum(array_column($produtos, 'quantidade_produzida'));
    $produtos_este_mes = count(array_filter($produtos, function($p) { 
        return date('Y-m', strtotime($p['data_colheita'])) === date('Y-m'); 
    }));
    $lotes_producao = count(array_unique(array_column($produtos, 'nome_lote')));

    // HTML do relatório
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; color: #333; }
        .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
        .stats { margin: 20px 0; }
        .stat-box { 
            display: inline-block; 
            width: 23%; 
            text-align: center; 
            padding: 10px; 
            margin: 5px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
        }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .stat-label { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <img src="' . SINPORG_LOGO_PATH . '" alt="SINPORG - Sistema Integrado da Produção Orgânica" style="height:58px; margin-bottom:6px;" />
        <div class="title">Relatório de Produção</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">' . $total_produtos . '</div>
            <div class="stat-label">Total de Produtos</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . number_format($total_produzido, 2) . '</div>
            <div class="stat-label">Total Produzido</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $produtos_este_mes . '</div>
            <div class="stat-label">Este Mês</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $lotes_producao . '</div>
            <div class="stat-label">Lotes de Produção</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código Produto</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Lavoura</th>
                <th>Cultura</th>
                <th>Quantidade</th>
                <th>Unidade</th>
                <th>Data Colheita</th>
                <th>Lote</th>
                <th>Associado</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($produtos as $produto) {
        $html .= '
        <tr>
            <td>' . $produto['codigo_rastreabilidade_produto'] . '</td>
            <td>' . htmlspecialchars($produto['nome']) . '</td>
            <td>' . htmlspecialchars($produto['tipo']) . '</td>
            <td>' . htmlspecialchars($produto['lavoura_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars($produto['cultura'] ?? '-') . '</td>
            <td>' . number_format($produto['quantidade_produzida'] ?? 0, 2) . '</td>
            <td>' . htmlspecialchars($produto['unidade_medida'] ?? '-') . '</td>
            <td>' . ($produto['data_colheita'] ? date('d/m/Y', strtotime($produto['data_colheita'])) : '-') . '</td>
            <td>' . htmlspecialchars($produto['nome_lote'] ?? '-') . '</td>
            <td>' . htmlspecialchars($produto['associado_nome'] ?? '-') . '</td>
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
    $mpdf->SetTitle('Relatório de Produção - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para exibição na tela
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_producao.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_producao.pdf', 'I');

} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?> 