<?php
// Habilitar exibição de erros para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Limpar qualquer saída anterior (prevenir corrupção de binários)
if (ob_get_level()) ob_end_clean();
ob_start();

// Incluir arquivos necessários
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Associado.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

// Inicializar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Parâmetros do Relatório
$tipo = $_GET['tipo'] ?? 'pdf';
$nomeEntidade = trim($_GET['nome_entidade'] ?? '');
$nomeResponsavel = trim($_GET['nome_responsavel'] ?? '');

// Se não informar entidade, usa o padrão do sistema ou "Associação"
if (empty($nomeEntidade)) {
    $nomeEntidade = defined('SITE_TITLE') ? SITE_TITLE : 'Associação';
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Consulta idêntica à do listar.php para manter consistência
    $query = "SELECT 
                a.id,
                a.foto_url,
                CONCAT(a.nome, ' ', COALESCE(a.sobrenome, '')) as nome_completo,
                a.email,
                a.telefone,
                c.nome as cidade_nome,
                a.estado,
                a.status,
                a.tipo as nivel
              FROM associados a
              LEFT JOIN cidades c ON a.cidade = c.id
              ORDER BY a.nome ASC, a.sobrenome ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── GERAÇÃO DO HTML (Para PDF e Excel) ──────────────────────────────────────
    
    $html = '
    <style>
        body { font-family: "Helvetica", sans-serif; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2e7d32; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #1b5e20; font-size: 22px; text-transform: uppercase; }
        .header h2 { margin: 5px 0 0; color: #2e7d32; font-size: 16px; font-weight: normal; }
        .header .info { font-size: 11px; color: #666; margin-top: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2e7d32; color: #ffffff; padding: 10px 5px; text-align: left; font-size: 11px; text-transform: uppercase; border: 1px solid #1b5e20; }
        td { padding: 8px 5px; border: 1px solid #ddd; font-size: 10px; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        
        .status-ativo { color: #2e7d32; font-weight: bold; }
        .status-inativo { color: #d32f2f; font-weight: bold; }
        .badge-tipo { background-color: #e8f5e9; color: #2e7d32; padding: 2px 5px; border-radius: 3px; font-size: 9px; }
        
        .footer { margin-top: 50px; text-align: center; }
        .signature-line { border-top: 1px solid #333; width: 250px; margin: 40px auto 5px; }
        .resp-name { font-weight: bold; font-size: 12px; }
        .resp-cargo { font-size: 10px; color: #666; }
        
        .foto-placeholder { width: 30px; height: 30px; border-radius: 15px; background-color: #eee; display: inline-block; }
    </style>

    <div class="header">
        <h1>' . htmlspecialchars($nomeEntidade) . '</h1>
        <h2>RELATÓRIO DE ASSOCIADOS</h2>
        <div style="font-size: 12px; margin-top: 5px; color: #444; font-weight: bold;">Sistema Integrado da Produção Orgânica</div>
        <div class="info">Gerado em: ' . date('d/m/Y H:i') . ' | Total: ' . count($associados) . ' associados</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 25%;">Nome Completo</th>
                <th style="width: 20%;">E-mail</th>
                <th style="width: 15%;">Telefone</th>
                <th style="width: 15%;">Cidade/UF</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 10%;">Nível</th>
            </tr>
        </thead>
        <tbody>';

    if (empty($associados)) {
        $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Nenhum associado encontrado.</td></tr>';
    } else {
        foreach ($associados as $row) {
            $cidade_uf = !empty($row['cidade_nome']) ? $row['cidade_nome'] : '';
            if (!empty($row['estado'])) $cidade_uf .= '/' . $row['estado'];
            
            $statusTexto = ($row['status'] == 'ativo' || $row['status'] == 1) ? 'Ativo' : 'Inativo';
            $statusClasse = ($row['status'] == 'ativo' || $row['status'] == 1) ? 'status-ativo' : 'status-inativo';

            $html .= '<tr>
                <td style="text-align: center;">' . $row['id'] . '</td>
                <td style="font-weight: bold;">' . htmlspecialchars($row['nome_completo']) . '</td>
                <td>' . htmlspecialchars($row['email'] ?: '---') . '</td>
                <td>' . htmlspecialchars($row['telefone'] ?: '---') . '</td>
                <td>' . htmlspecialchars($cidade_uf ?: '---') . '</td>
                <td class="' . $statusClasse . '" style="text-align: center;">' . $statusTexto . '</td>
                <td style="text-align: center;"><span class="badge-tipo">' . ucfirst(htmlspecialchars($row['nivel'] ?: '---')) . '</span></td>
            </tr>';
        }
    }

    $html .= '</tbody></table>';

    if (!empty($nomeResponsavel)) {
        $html .= '
        <div class="footer">
            <div class="signature-line"></div>
            <div class="resp-name">' . htmlspecialchars($nomeResponsavel) . '</div>
            <div class="resp-cargo">Responsável / Coordenador</div>
        </div>';
    }

    // ─── SAÍDA DO RELATÓRIO ──────────────────────────────────────────────────────

    if ($tipo === 'excel') {
        // Limpar buffer antes de enviar headers
        ob_end_clean();
        
        $filename = "relatorio_associados_" . date('Ymd_His') . ".xls";
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo '
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8"></head>
        <body>' . $html . '</body>
        </html>';
        exit;
        
    } else {
        // PDF usando mPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);

        $mpdf->SetTitle('Relatório de Associados');
        $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
        $mpdf->SetFooter('Página {PAGENO} de {nbpg} | Gerado em ' . date('d/m/Y H:i'));

        $mpdf->WriteHTML($html);
        
        // Limpar buffer antes de enviar o PDF
        ob_end_clean();
        $mpdf->Output("relatorio_associados_" . date('Ymd_His') . ".pdf", 'I');
        exit;
    }

} catch (Exception $e) {
    ob_end_clean();
    die("<div style='padding: 20px; font-family: sans-serif; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>
            <strong>Erro ao gerar relatório:</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>");
}
