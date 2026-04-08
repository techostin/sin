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

    // Buscar dados dos insumos
    $sql = "SELECT 
        i.*,
        a.nome as associado_nome,
        f.nome as fornecedor_nome,
        f.telefone as fornecedor_telefone,
        f.email as fornecedor_email
        FROM insumos i
        LEFT JOIN associados a ON i.associado_id = a.id
        LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
        ORDER BY i.nome ASC";
    
    $stmt = $db->query($sql);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_insumos = count($insumos);
    $total_estoque = array_sum(array_column($insumos, 'quantidade_estoque'));
    $insumos_baixo_estoque = count(array_filter($insumos, function($i) { return $i['quantidade_estoque'] < 10; }));
    $insumos_sem_estoque = count(array_filter($insumos, function($i) { return $i['quantidade_estoque'] <= 0; }));

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
        .estoque-baixo { color: #ffc107; font-weight: bold; }
        .estoque-zero { color: #dc3545; font-weight: bold; }
        .estoque-ok { color: #28a745; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <div class="title">Relatório de Insumos</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">' . $total_insumos . '</div>
            <div class="stat-label">Total de Insumos</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . number_format($total_estoque, 2) . '</div>
            <div class="stat-label">Total em Estoque</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $insumos_baixo_estoque . '</div>
            <div class="stat-label">Baixo Estoque</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $insumos_sem_estoque . '</div>
            <div class="stat-label">Sem Estoque</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Insumo</th>
                <th>Descrição</th>
                <th>Associado</th>
                <th>Fornecedor</th>
                <th>Estoque Anterior</th>
                <th>Recebido</th>
                <th>Estoque Atual</th>
                <th>Validade</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($insumos as $insumo) {
        $estoque_class = 'estoque-ok';
        if ($insumo['quantidade_estoque'] <= 0) {
            $estoque_class = 'estoque-zero';
        } elseif ($insumo['quantidade_estoque'] < 10) {
            $estoque_class = 'estoque-baixo';
        }

        $html .= '
        <tr>
            <td>' . htmlspecialchars($insumo['nome']) . '</td>
            <td>' . htmlspecialchars($insumo['descricao'] ?? '-') . '</td>
            <td>' . htmlspecialchars($insumo['associado_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars($insumo['fornecedor_nome'] ?? '-') . '</td>
            <td>' . number_format($insumo['estoq_anterior'] ?? 0, 2) . '</td>
            <td>' . number_format($insumo['quant_recebida'] ?? 0, 2) . '</td>
            <td class="' . $estoque_class . '">' . number_format($insumo['quantidade_estoque'] ?? 0, 2) . '</td>
            <td>' . ($insumo['data_validade'] ? date('d/m/Y', strtotime($insumo['data_validade'])) : '-') . '</td>
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
    $mpdf->SetTitle('Relatório de Insumos - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para exibição na tela
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_insumos.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_insumos.pdf', 'I');

} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?> 