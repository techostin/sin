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

    // Filtro de escopo: não-gestores só veem lotes das próprias propriedades
    $params = [];
    $where = ["lp.excluido = 0"];
    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "prop.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }
    $where_clause = implode(' AND ', $where);

    // Buscar dados dos lotes com rastreabilidade completa
    $sql = "SELECT 
        lp.*,
        p.nome as produto_nome,
        p.tipo as produto_tipo,
        p.unidade_medida as produto_unidade,
        p.data_colheita,
        l.nome as lavoura_nome,
        l.tipo_cultura as cultura,
        prop.propriedade,
        a.nome as associado_nome,
        CONCAT('LOTE-', LPAD(lp.id, 6, '0')) as codigo_rastreabilidade
        FROM lotes_producao lp
        LEFT JOIN produtos p ON lp.produto_id = p.id
        LEFT JOIN lavouras l ON p.lavoura_id = l.id
        LEFT JOIN propriedades prop ON l.propriedade_id = prop.id
        LEFT JOIN associados a ON prop.associado_id = a.id
        WHERE $where_clause
        ORDER BY lp.data_producao DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_lotes = count($lotes);
    $total_produzido = array_sum(array_column($lotes, 'quantidade'));
    $lotes_este_mes = count(array_filter($lotes, function($l) { 
        return date('Y-m', strtotime($l['data_producao'])) === date('Y-m'); 
    }));
    $produtos_unicos = count(array_unique(array_column($lotes, 'produto_nome')));

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
        .lote-section { margin: 30px 0; page-break-inside: avoid; }
        .lote-header { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .codigo-rastreabilidade { font-family: monospace; font-size: 14px; font-weight: bold; color: #007bff; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <img src="' . SINPORG_LOGO_PATH . '" alt="SINPORG - Sistema Integrado da Produção Orgânica" style="height:58px; margin-bottom:6px;" />
        <div class="title">Relatório de Lotes de Produção</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">' . $total_lotes . '</div>
            <div class="stat-label">Total de Lotes</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . number_format($total_produzido, 2) . '</div>
            <div class="stat-label">Total Produzido</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $lotes_este_mes . '</div>
            <div class="stat-label">Este Mês</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $produtos_unicos . '</div>
            <div class="stat-label">Tipos de Produtos</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código de Rastreabilidade</th>
                <th>Nome do Lote</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Unidade</th>
                <th>Data Produção</th>
                <th>Propriedade</th>
                <th>Lavoura</th>
                <th>Associado</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($lotes as $lote) {
        $html .= '
        <tr>
            <td class="codigo-rastreabilidade">' . $lote['codigo_rastreabilidade'] . '</td>
            <td>' . htmlspecialchars($lote['nome_lote']) . '</td>
            <td>' . htmlspecialchars($lote['produto_nome']) . '</td>
            <td>' . number_format($lote['quantidade'], 2) . '</td>
            <td>' . htmlspecialchars($lote['unidade_medida']) . '</td>
            <td>' . date('d/m/Y', strtotime($lote['data_producao'])) . '</td>
            <td>' . htmlspecialchars($lote['propriedade'] ?? '-') . '</td>
            <td>' . htmlspecialchars($lote['lavoura_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars($lote['associado_nome'] ?? '-') . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <h3>Detalhes de Rastreabilidade</h3>';

    foreach ($lotes as $lote) {
        // Buscar atividades relacionadas ao produto/lavoura
        $stmt = $db->prepare("SELECT 
            ad.*,
            ca.nome as categoria_nome
            FROM atividades_diarias ad
            LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
            WHERE ad.lavoura_id = (SELECT lavoura_id FROM produtos WHERE id = ?)
            ORDER BY ad.data_atividade DESC
            LIMIT 5");
        $stmt->execute([$lote['produto_id']]);
        $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '
        <div class="lote-section">
            <div class="lote-header">
                <h4>' . $lote['codigo_rastreabilidade'] . ' - ' . htmlspecialchars($lote['nome_lote']) . '</h4>
                <p><strong>Produto:</strong> ' . htmlspecialchars($lote['produto_nome']) . ' (' . htmlspecialchars($lote['produto_tipo']) . ')</p>
                <p><strong>Produção:</strong> ' . number_format($lote['quantidade'], 2) . ' ' . htmlspecialchars($lote['unidade_medida']) . ' em ' . date('d/m/Y', strtotime($lote['data_producao'])) . '</p>
                <p><strong>Origem:</strong> ' . htmlspecialchars($lote['propriedade'] ?? '-') . ' > ' . htmlspecialchars($lote['lavoura_nome'] ?? '-') . ' (' . htmlspecialchars($lote['cultura'] ?? '-') . ')</p>
                <p><strong>Responsável:</strong> ' . htmlspecialchars($lote['associado_nome'] ?? '-') . '</p>
            </div>

            <h5>Atividades Relacionadas</h5>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Atividade</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($atividades)) {
            foreach ($atividades as $atividade) {
                $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($atividade['data_atividade'])) . '</td>
                    <td>' . htmlspecialchars($atividade['nome']) . '</td>
                    <td>' . htmlspecialchars($atividade['tipo_atividade']) . '</td>
                    <td>' . htmlspecialchars($atividade['categoria_nome'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($atividade['status']) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" style="text-align: center;">Nenhuma atividade registrada</td></tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>';
    }

    $html .= '
    <div class="footer">
        <p>Relatório gerado pelo Sistema Integrado da Produção Orgânica</p>
        <p>Página {PAGENO} de {nbpg}</p>
    </div>';

    // Definir metadados do PDF
    $mpdf->SetTitle('Relatório de Lotes - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para exibição na tela
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_lotes.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_lotes.pdf', 'I');

} catch (Exception $e) {
    // Em caso de erro, mostrar mensagem
    echo '<div style="font-family: Arial, sans-serif; padding: 20px; text-align: center;">';
    echo '<h2>Erro ao gerar relatório</h2>';
    echo '<p>Ocorreu um erro ao gerar o relatório: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="../relatorios" style="color: #007bff;">Voltar aos relatórios</a></p>';
    echo '</div>';
}
?> 