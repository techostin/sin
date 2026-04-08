<?php
// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

use Mpdf\Mpdf;

try {
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

    // Buscar propriedades
    $sql = "SELECT 
        p.*,
        COUNT(DISTINCT l.id) as total_lavouras,
        COUNT(DISTINCT ad.id) as total_atividades,
        SUM(l.area) as area_total_lavouras
        FROM propriedades p
        LEFT JOIN lavouras l ON p.id = l.propriedade_id
        LEFT JOIN atividades_diarias ad ON p.id = ad.propriedade_id
        GROUP BY p.id, p.propriedade, p.area_total, p.endereco, p.cep, p.bairro, p.cidade, p.estado, p.latitude, p.longitude, p.observacoes, p.created_at
        ORDER BY p.propriedade ASC";
    
    $stmt = $db->query($sql);
    $propriedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_propriedades = count($propriedades);
    $area_total = array_sum(array_column($propriedades, 'area_total'));
    $total_lavouras = array_sum(array_column($propriedades, 'total_lavouras'));
    $total_atividades = array_sum(array_column($propriedades, 'total_atividades'));

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
        .propriedade-section { margin: 30px 0; page-break-inside: avoid; }
        .propriedade-header { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <div class="title">Relatório por Propriedades</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">' . $total_propriedades . '</div>
            <div class="stat-label">Total de Propriedades</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . number_format($area_total, 2) . '</div>
            <div class="stat-label">Área Total (ha)</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $total_lavouras . '</div>
            <div class="stat-label">Total de Lavouras</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $total_atividades . '</div>
            <div class="stat-label">Total de Atividades</div>
        </div>
    </div>';

    foreach ($propriedades as $propriedade) {
        // Buscar lavouras da propriedade
        $stmt = $db->prepare("SELECT * FROM lavouras WHERE propriedade_id = ? ORDER BY nome ASC");
        $stmt->execute([$propriedade['id']]);
        $lavouras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar atividades da propriedade
        $stmt = $db->prepare("SELECT 
            ad.*,
            l.nome as lavoura_nome,
            ca.nome as categoria_nome
            FROM atividades_diarias ad
            LEFT JOIN lavouras l ON ad.lavoura_id = l.id
            LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
            WHERE ad.propriedade_id = ?
            ORDER BY ad.data_atividade DESC
            LIMIT 10");
        $stmt->execute([$propriedade['id']]);
        $atividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '
        <div class="propriedade-section">
            <div class="propriedade-header">
                <h3>' . htmlspecialchars($propriedade['propriedade']) . '</h3>
                <p><strong>Endereço:</strong> ' . htmlspecialchars($propriedade['endereco']) . ', ' . htmlspecialchars($propriedade['bairro']) . ', ' . htmlspecialchars($propriedade['cidade']) . ' - ' . htmlspecialchars($propriedade['estado']) . '</p>
                <p><strong>Área Total:</strong> ' . number_format($propriedade['area_total'], 2) . ' ha | <strong>Lavouras:</strong> ' . $propriedade['total_lavouras'] . ' | <strong>Atividades:</strong> ' . $propriedade['total_atividades'] . '</p>
            </div>

            <h4>Lavouras da Propriedade</h4>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Cultura</th>
                        <th>Área (ha)</th>
                        <th>Data Plantio</th>
                    </tr>
                </thead>
                <tbody>';

        if (!empty($lavouras)) {
            foreach ($lavouras as $lavoura) {
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($lavoura['nome']) . '</td>
                    <td>' . htmlspecialchars($lavoura['cultura']) . '</td>
                    <td>' . number_format($lavoura['area'], 2) . '</td>
                    <td>' . ($lavoura['data_plantio'] ? date('d/m/Y', strtotime($lavoura['data_plantio'])) : '-') . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" style="text-align: center;">Nenhuma lavoura cadastrada</td></tr>';
        }

        $html .= '
                </tbody>
            </table>

            <h4>Últimas Atividades</h4>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Atividade</th>
                        <th>Tipo</th>
                        <th>Lavoura</th>
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
                    <td>' . htmlspecialchars($atividade['lavoura_nome'] ?? '-') . '</td>
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
    $mpdf->SetTitle('Relatório de Propriedades - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para exibição na tela
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_propriedades.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_propriedades.pdf', 'I');

} catch (Exception $e) {
    // Em caso de erro, mostrar mensagem
    echo '<div style="font-family: Arial, sans-serif; padding: 20px; text-align: center;">';
    echo '<h2>Erro ao gerar relatório</h2>';
    echo '<p>Ocorreu um erro ao gerar o relatório: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="../relatorios" style="color: #007bff;">Voltar aos relatórios</a></p>';
    echo '</div>';
}
?> 