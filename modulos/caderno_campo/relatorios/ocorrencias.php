<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/User.php';

use Mpdf\Mpdf;

try {
    $user = new User();

    $severidade = $_GET['severidade'] ?? '';
    $status = $_GET['status'] ?? '';
    $propriedade_id = isset($_GET['propriedade_id']) ? (int)$_GET['propriedade_id'] : 0;
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10
    ]);

    $db = Database::getInstance()->getConnection();

    $params = [];
    $where = ["om.excluido = 0"];

    if (!empty($severidade)) {
        $where[] = "om.severidade = :severidade";
        $params[':severidade'] = $severidade;
    }

    if (!empty($status)) {
        $where[] = "om.status = :status";
        $params[':status'] = $status;
    }

    if ($propriedade_id > 0) {
        $where[] = "om.propriedade_id = :propriedade_id";
        $params[':propriedade_id'] = $propriedade_id;
    }

    if (!empty($data_inicio)) {
        $where[] = "om.data_deteccao >= :data_inicio";
        $params[':data_inicio'] = $data_inicio;
    }

    if (!empty($data_fim)) {
        $where[] = "om.data_deteccao <= :data_fim";
        $params[':data_fim'] = $data_fim;
    }

    if (($_SESSION['user_nivel'] ?? NIVEL_USUARIO) > NIVEL_GESTOR) {
        $assoc_id = $user->getAssociadoId();
        if ($assoc_id) {
            $where[] = "om.associado_id = :associado_id";
            $params[':associado_id'] = $assoc_id;
        }
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT om.*, p.propriedade as propriedade_nome,
            l.nome as lavoura_nome, i.nome as insumo_nome,
            a.nome as associado_nome, a.sobrenome as associado_sobrenome
            FROM ocorrencias_manejo om
            LEFT JOIN propriedades p ON om.propriedade_id = p.id
            LEFT JOIN lavouras l ON om.lavoura_id = l.id
            LEFT JOIN insumos i ON om.insumo_utilizado_id = i.id
            LEFT JOIN associados a ON om.associado_id = a.id
            $where_clause
            ORDER BY om.data_deteccao DESC, om.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $ocorrencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($ocorrencias);
    $abertas = count(array_filter($ocorrencias, function($o) { return ($o['status'] ?? 'Aberta') === 'Aberta'; }));
    $em_correcao = count(array_filter($ocorrencias, function($o) { return ($o['status'] ?? '') === 'Em Correção'; }));
    $resolvidas = count(array_filter($ocorrencias, function($o) { return ($o['status'] ?? '') === 'Resolvida'; }));
    $fechadas = count(array_filter($ocorrencias, function($o) { return ($o['status'] ?? '') === 'Fechada'; }));

    $por_tipo = [];
    foreach ($ocorrencias as $oc) {
        $t = $oc['tipo'] ?? 'Outro';
        $por_tipo[$t] = ($por_tipo[$t] ?? 0) + 1;
    }

    $por_severidade = [];
    foreach ($ocorrencias as $oc) {
        $s = $oc['severidade'] ?? '-';
        $por_severidade[$s] = ($por_severidade[$s] ?? 0) + 1;
    }

    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; color: #333; }
        .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
        .stats-table { width: 100%; margin: 20px 0; border-collapse: separate; border-spacing: 10px; }
        .stat-box {
            width: 25%; text-align: center; padding: 15px 10px;
            border: 1px solid #ddd; border-radius: 8px; background-color: #fcfcfc;
        }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .sev-alta { color: #dc3545; font-weight: bold; }
        .sev-media { color: #ffc107; font-weight: bold; }
        .sev-baixa { color: #28a745; font-weight: bold; }
        .status-aberta { color: #ffc107; font-weight: bold; }
        .status-em-correcao { color: #17a2b8; font-weight: bold; }
        .status-resolvida { color: #28a745; font-weight: bold; }
        .status-fechada { color: #343a40; font-weight: bold; }
        .summary-table { width: 50%; margin: 10px 0; }
        .summary-table td, .summary-table th { padding: 6px 10px; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
    </style>

    <div class="header">
        <img src="' . SINPORG_LOGO_PATH . '" alt="SINPORG - Sistema Integrado da Produção Orgânica" style="height:58px; margin-bottom:6px;" />
        <div class="title">Relatório de Ocorrências de Manejo</div>
        <div class="subtitle">Caderno de Campo - Sistema Integrado da Produção Orgânica</div>
        <div class="subtitle">Gerado em: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <table class="stats-table">
        <tr>
            <td class="stat-box">
                <div class="stat-number">' . $total . '</div>
                <div class="stat-label">Total</div>
            </td>
            <td class="stat-box">
                <div class="stat-number" style="color:#ffc107">' . $abertas . '</div>
                <div class="stat-label">Abertas</div>
            </td>
            <td class="stat-box">
                <div class="stat-number" style="color:#17a2b8">' . $em_correcao . '</div>
                <div class="stat-label">Em Correção</div>
            </td>
            <td class="stat-box">
                <div class="stat-number" style="color:#28a745">' . $resolvidas . '</div>
                <div class="stat-label">Resolvidas</div>
            </td>
        </tr>
    </table>';

    if (!empty($por_tipo)) {
        $html .= '<h3 style="margin-top:20px;">Ocorrências por Tipo</h3>
        <table class="summary-table">
            <thead><tr><th>Tipo</th><th>Quantidade</th></tr></thead><tbody>';
        foreach ($por_tipo as $tipo_nome => $qtd) {
            $html .= '<tr><td>' . htmlspecialchars($tipo_nome) . '</td><td>' . $qtd . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    if (!empty($por_severidade)) {
        $html .= '<h3>Por Severidade</h3>
        <table class="summary-table">
            <thead><tr><th>Severidade</th><th>Quantidade</th></tr></thead><tbody>';
        foreach ($por_severidade as $sev => $qtd) {
            $html .= '<tr><td>' . htmlspecialchars($sev) . '</td><td>' . $qtd . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '
    <h3 style="margin-top:20px;">Detalhamento das Ocorrências</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Data Detecção</th>
                <th>Tipo</th>
                <th>Nome Comum</th>
                <th>Severidade</th>
                <th>Status</th>
                <th>Propriedade</th>
                <th>Lavoura</th>
                <th>Produtor</th>
                <th>Ação de Manejo</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($ocorrencias as $oc) {
        $sev_class = '';
        if ($oc['severidade'] === 'Alta') $sev_class = 'sev-alta';
        elseif ($oc['severidade'] === 'Média') $sev_class = 'sev-media';
        elseif ($oc['severidade'] === 'Baixa') $sev_class = 'sev-baixa';

        $st = $oc['status'] ?? 'Aberta';
        $st_class = 'status-' . strtolower(str_replace(' ', '-', $st));

        $html .= '
        <tr>
            <td>' . $oc['id'] . '</td>
            <td>' . ($oc['data_deteccao'] ? date('d/m/Y', strtotime($oc['data_deteccao'])) : '-') . '</td>
            <td>' . htmlspecialchars($oc['tipo']) . '</td>
            <td>' . htmlspecialchars($oc['nome_comum']) . '</td>
            <td class="' . $sev_class . '">' . htmlspecialchars($oc['severidade']) . '</td>
            <td class="' . $st_class . '">' . htmlspecialchars($st) . '</td>
            <td>' . htmlspecialchars($oc['propriedade_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars($oc['lavoura_nome'] ?? '-') . '</td>
            <td>' . htmlspecialchars(($oc['associado_nome'] ?? '') . ' ' . ($oc['associado_sobrenome'] ?? '')) . '</td>
            <td>' . htmlspecialchars($oc['acao_manejo'] ?? '-') . '</td>
        </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Relatório gerado pelo Sistema Integrado da Produção Orgânica</p>
        <p>Página {PAGENO} de {nbpg}</p>
    </div>';

    $mpdf->SetTitle('Relatório de Ocorrências - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');
    $mpdf->WriteHTML($html);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_ocorrencias.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $mpdf->Output('relatorio_ocorrencias.pdf', 'I');

} catch (Exception $e) {
    echo "Erro ao gerar relatório: " . $e->getMessage();
}
?>
