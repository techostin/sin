<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado.');
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/ReportManager.php';

use Mpdf\Mpdf;

try {
    $reportManager = new ReportManager();
    $filtros = $_GET;
    $dados = $reportManager->getAssociadosReport($filtros);

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 20,
        'margin_bottom' => 20
    ]);

    $html = '
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #2e7d32; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 16pt; font-weight: bold; color: #2e7d32; margin-bottom: 5px; }
        .subtitle { font-size: 10pt; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; font-size: 9pt; }
        td { border: 1px solid #ddd; padding: 8px; font-size: 9pt; }
        .footer { text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 8pt; color: white; }
        .bg-success { background-color: #2e7d32; }
        .bg-secondary { background-color: #757575; }
    </style>

    <div class="header">
        <div class="title">Relatório de Associados / Produtores</div>
        <div class="subtitle">Emissão: ' . date('d/m/Y H:i') . '</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome Completo</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>RG</th>
                <th>Cidade</th>
                <th>UF</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($dados)) {
        $html .= '<tr><td colspan="8" style="text-align:center">Nenhum registro encontrado.</td></tr>';
    } else {
        foreach ($dados as $item) {
            $statusClass = ($item['status'] === 'ativo') ? 'bg-success' : 'bg-secondary';
            $statusText = ucfirst($item['status']);
            
            $html .= '<tr>
                <td>' . $item['id'] . '</td>
                <td>' . htmlspecialchars($item['nome'] . ' ' . $item['sobrenome']) . '</td>
                <td>' . htmlspecialchars($item['email'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['telefone'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['rg'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['cidade_nome'] ?? '-') . '</td>
                <td>' . htmlspecialchars($item['estado_uf'] ?? '-') . '</td>
                <td><span class="badge ' . $statusClass . '">' . $statusText . '</span></td>
            </tr>';
        }
    }

    $html .= '
        </tbody>
    </table>

    <div class="footer">
        Relatório gerado pelo Sistema Integrado de Produção Orgânica - Página {PAGENO} de {nb}
    </div>';

    $mpdf->WriteHTML($html);
    $mpdf->Output('Relatorio_Associados_' . date('Ymd_His') . '.pdf', 'I');

} catch (Exception $e) {
    die('Erro ao gerar relatório: ' . $e->getMessage());
}
