<?php
// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';

use Mpdf\Mpdf;

try {
    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Buscar associados com dados da associação
    $sql = "
        SELECT 
            a.id,
            CONCAT(a.nome, ' ', a.sobrenome) AS nome_completo,
            a.cpf,
            a.email,
            a.telefone,
            ass.nome AS nome_associacao
        FROM associados a
        LEFT JOIN associacoes ass ON a.vinculo = ass.id
        ORDER BY a.nome, a.sobrenome ASC
    ";
    
    $stmt = $db->query($sql);
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estatísticas
    $total_associados = count($associados);
    $associados_com_vinculo = count(array_filter($associados, function($a) {
        return !empty($a['nome_associacao']);
    }));
    $associados_sem_vinculo = $total_associados - $associados_com_vinculo;

    // Gerar HTML do relatório
    $html = '
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .stats-info { margin-top: 10px; font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 12px; text-align: left; font-weight: bold; }
        td { border: 1px solid #dee2e6; padding: 8px; }
        .no-data { text-align: center; color: #666; font-style: italic; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #dee2e6; padding-top: 20px; }
    </style>

    <div class="header">
        <div class="title">Relatório Geral de Associados</div>
        <div>Gerado em: ' . date('d/m/Y H:i:s') . '</div>
        <div class="stats-info">
            Total: ' . $total_associados . ' | Com Associação: ' . $associados_com_vinculo . ' | Sem Associação: ' . $associados_sem_vinculo . '
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nome Completo</th>
                <th>CPF</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Associação</th>
            </tr>
        </thead>
        <tbody>';

    if (count($associados) > 0) {
        foreach ($associados as $associado) {
            $html .= '
            <tr>
                <td>' . htmlspecialchars($associado['nome_completo']) . '</td>
                <td>' . htmlspecialchars($associado['cpf'] ?: '-') . '</td>
                <td>' . htmlspecialchars($associado['email'] ?: '-') . '</td>
                <td>' . htmlspecialchars($associado['telefone'] ?: '-') . '</td>
                <td>' . htmlspecialchars($associado['nome_associacao'] ?: 'Não informado') . '</td>
            </tr>';
        }
    } else {
        $html .= '
        <tr>
            <td colspan="5" class="no-data">Nenhum associado encontrado</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Relatório gerado pelo Sistema Integrado da Produção Orgânica</p>
        <p>Página {PAGENO} de {nbpg}</p>
    </div>';

    // Configurar metadados do PDF
    $mpdf->SetTitle('Relatório Geral de Associados');
    $mpdf->SetAuthor('SINPORG');
    $mpdf->SetCreator('SINPORG - Sistema Integrado da Produção Orgânica');
    $mpdf->SetSubject('Relatório de Associados');

    // Escrever HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos HTTP para download
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_associados_' . date('Y-m-d_H-i-s') . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Output do PDF
    $mpdf->Output();

} catch (Exception $e) {
    // Em caso de erro, mostrar mensagem amigável
    header('Content-Type: text/html; charset=utf-8');
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Erro no Relatório</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; }
            .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>Erro ao Gerar Relatório</h2>
            <p>Ocorreu um erro ao gerar o relatório de associados.</p>
            <p>Detalhes: ' . htmlspecialchars($e->getMessage()) . '</p>
            <a href="' . SITE_URL . '" class="btn">Voltar ao Sistema</a>
        </div>
    </body>
    </html>';
}
?> 